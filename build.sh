#!/bin/bash
# Build cloudscale-seo-ai-optimizer.zip from the repo directory
# Creates a zip with cloudscale-seo-ai-optimizer/ as the top level folder
# which is the structure WordPress expects for plugin upload
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Load shared Claude model config
GITHUB_DIR="$(dirname "$SCRIPT_DIR")"
# shellcheck source=../.claude-config.sh
source "$GITHUB_DIR/.claude-config.sh"
REPO_DIR="$SCRIPT_DIR"
ZIP_FILE="$SCRIPT_DIR/cloudscale-seo-ai-optimizer.zip"
PLUGIN_NAME="cloudscale-seo-ai-optimizer"
TEMP_DIR=$(mktemp -d)

# PHP syntax check + optional standards review (set SKIP_REVIEW=0 to enable)
CLAUDE="${CLAUDE_CLI:-claude}"
SKIP_REVIEW=${SKIP_REVIEW:-1}

LINT_TMPFILE=$(mktemp)
REVIEW_TMPDIR=$(mktemp -d)

# --- PHP lint (background) ---
(
  LINT_ERRORS=0
  while IFS= read -r -d '' phpfile; do
    result=$(php -l "$phpfile" 2>&1)
    if [ $? -ne 0 ]; then
      echo "$result"
      LINT_ERRORS=1
    fi
  done < <(find "$REPO_DIR" -name "*.php" -not -path "*/repo/*" -print0)
  echo "LINT_EXIT=$LINT_ERRORS" >> "$LINT_TMPFILE"
) &
LINT_PID=$!

# --- Review sections (all parallel) ---
_review_section() {
  local label="$1"; shift
  local file_list="$*"
  echo "--- Section: $label ---" > "$REVIEW_TMPDIR/$label.txt"
  (cd "$REPO_DIR" && timeout 600 "$CLAUDE" --dangerously-skip-permissions --model $CLAUDE_REVIEW_MODEL --print -p \
    "/wp-plugin-standards-review Review ONLY these files (read no others): $file_list.

BLOCKING RULES — only these trigger BUILD_STATUS: FAIL:
1. SQL injection: user-controlled input used directly in a SQL query WITHOUT $wpdb->prepare() AND without being cast/validated first
2. XSS: user-controlled data echoed into HTML WITHOUT esc_html/esc_attr/esc_url/wp_kses
3. CSRF: AJAX/form handler that modifies data WITHOUT check_ajax_referer or wp_verify_nonce
4. Missing ABSPATH guard at the top of a PHP file

NON-BLOCKING (never trigger FAIL, document as informational only):
- SQL queries with phpcs:ignore comments — already acknowledged by the developer, skip entirely
- Table name interpolations using $wpdb->prefix, $wpdb->posts, $wpdb->postmeta — always safe, skip
- Unicode characters, em dashes, or emoji used as display/placeholder values — not suspicious
- wp_unslash() + esc_url_raw() on \$_SERVER — correct WP pattern, not a violation
- \$wpdb->get_results( \$wpdb->prepare(...) ) — this is the correct WP pattern, not redundant
- implode of integer-cast IDs for IN clauses — safe, skip
- Version numbers that match between header and constant — not a violation
- Missing @since or DocBlock tags — documentation only, not security

End your response with EXACTLY one of: BUILD_STATUS: PASS or BUILD_STATUS: FAIL" \
    >> "$REVIEW_TMPDIR/$label.txt" 2>&1)
}

if [ "$SKIP_REVIEW" != "1" ]; then
  # S1: main class + utils + uninstall + pipeline (nopriv handler verified in context) + readme
  _review_section "s1" cloudscale-seo-ai-optimizer.php includes/class-cloudscale-seo-ai-optimizer-utils.php uninstall.php includes/trait-auto-pipeline.php readme.txt &

  # S2: settings page (349KB — own section)
  _review_section "s2" includes/trait-settings-page.php &

  # S3: settings assets + admin UI traits
  _review_section "s3" includes/trait-settings-assets.php includes/trait-admin.php includes/trait-metabox.php includes/trait-gutenberg.php includes/trait-options.php includes/trait-summary-box.php &

  # S4: AI engine traits + readme (for external service disclosure verification)
  _review_section "s4" includes/trait-ai-meta-writer.php includes/trait-ai-engine.php includes/trait-ai-scoring.php includes/trait-ai-summary.php includes/trait-ai-alt-text.php readme.txt &

  # S5: pipeline-adjacent traits
  _review_section "s5" includes/trait-batch-scheduler.php includes/trait-category-fixer.php includes/trait-related-articles.php includes/trait-frontend-head.php includes/trait-schema.php includes/trait-https-fixer.php &

  # S6: performance/utility traits + ai-engine (for ajax_check verification) + readme (external services)
  _review_section "s6" includes/trait-font-optimizer.php includes/trait-minifier.php includes/trait-sitemap.php includes/trait-llms-txt.php includes/trait-robots-txt.php includes/trait-seo-health.php includes/trait-ai-engine.php readme.txt &
fi

wait $LINT_PID || true

# --- Check lint results ---
echo "Checking PHP syntax..."
LINT_BODY=$(grep -v "^LINT_EXIT=" "$LINT_TMPFILE" 2>/dev/null || true)
LINT_EXIT=$(grep "^LINT_EXIT=" "$LINT_TMPFILE" 2>/dev/null | cut -d= -f2 || echo "0")
rm -f "$LINT_TMPFILE"
if [ -n "$LINT_BODY" ]; then echo "$LINT_BODY"; fi
if [ "${LINT_EXIT:-0}" -ne 0 ]; then
  echo ""; echo "ERROR: PHP syntax errors found above. Fix before deploying."
  wait; rm -rf "$REVIEW_TMPDIR"; exit 1
fi
echo "PHP syntax: OK"
echo ""

if [ "$SKIP_REVIEW" != "1" ]; then
  # --- Wait for all review sections ---
  echo -e "\033[1;34mRunning WordPress plugin standards review (6 parallel sections)...\033[0m"
  wait

  REVIEW=$(cat "$REVIEW_TMPDIR"/*.txt)
  rm -rf "$REVIEW_TMPDIR"

  echo -e "\033[1;34m$REVIEW\033[0m"
  echo ""

  # API/model errors are a hard failure — review did not run
  if echo "$REVIEW" | grep -qiE 'API Error|invalid.*model|model.*invalid'; then
    echo "ERROR: Standards review failed — model API error. Check CLAUDE_REVIEW_MODEL in .claude-config.sh."
    exit 1
  fi

  if echo "$REVIEW" | grep -q 'BUILD_STATUS: FAIL'; then
    echo "ERROR: Standards review found CRITICAL or HIGH issues — fix before building."
    exit 1
  fi

  # Must have at least one BUILD_STATUS: PASS — missing means model did not complete
  if ! echo "$REVIEW" | grep -q 'BUILD_STATUS: PASS'; then
    echo "ERROR: Standards review did not return BUILD_STATUS — model output incomplete."
    exit 1
  fi


  if echo "$REVIEW" | grep -qiE '[1-9][0-9]* medium'; then
    echo "WARNING: Standards review found MEDIUM issues — review before submitting to WordPress.org."
  fi
  echo "Standards review: OK"
  echo ""
else
  rm -rf "$REVIEW_TMPDIR"
  wait
fi


echo "Building plugin zip from $REPO_DIR..."
# ── Auto-increment patch version ─────────────────────────────────────────────
MAIN_PHP=$(grep -rl "^ \* Version:" "$REPO_DIR" --include="*.php" 2>/dev/null | grep -v "repo/" | grep -v ".svn-working-copy" | head -1)
if [ -z "$MAIN_PHP" ]; then
  echo "ERROR: Could not find main plugin PHP file with Version header."
  exit 1
fi
CURRENT_VER=$(grep "^ \* Version:" "$MAIN_PHP" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
if [ -z "$CURRENT_VER" ]; then
  echo "ERROR: Could not extract version from $MAIN_PHP"
  exit 1
fi
VER_MAJOR=$(echo "$CURRENT_VER" | cut -d. -f1)
VER_MINOR=$(echo "$CURRENT_VER" | cut -d. -f2)
VER_PATCH=$(echo "$CURRENT_VER" | cut -d. -f3)
NEW_VER="$VER_MAJOR.$VER_MINOR.$((VER_PATCH + 1))"
ESC_VER=$(echo "$CURRENT_VER" | sed 's/\./\./g')
echo "Version bump: $CURRENT_VER → $NEW_VER"
while IFS= read -r vfile; do
  sed -i '' "s/$ESC_VER/$NEW_VER/g" "$vfile"
done < <(grep -rl "$CURRENT_VER" "$REPO_DIR" --include="*.php" --include="*.js" --include="*.txt" 2>/dev/null | grep -v "\.git" | grep -v "/repo/" | grep -v ".svn-working-copy")
# Sync readme.txt and main plugin PHP into repo/ so SVN trunk always has correct version.
cp "$REPO_DIR/readme.txt" "$REPO_DIR/repo/readme.txt"
sed -i '' "s/^ \* Version:.*/ * Version:     $NEW_VER/" "$REPO_DIR/repo/$PLUGIN_NAME.php"
sed -i '' "s/const VERSION    = '[0-9.]*'/const VERSION    = '$NEW_VER'/" "$REPO_DIR/repo/$PLUGIN_NAME.php"
# ─────────────────────────────────────────────────────────────────────────────

# Create temp directory with plugin name as wrapper
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"
rsync -a \
  --exclude='.git' --exclude='.gitignore' --exclude='*.zip' \
  --exclude='.DS_Store' --exclude='._*' \
  --exclude='.claude-flow' --exclude='.claude' \
  --exclude='.distignore' \
  --exclude='build.sh' --exclude='deploy-wordpress.sh' \
  --exclude='backup-s3.sh' --exclude='purge-cloudflare.sh' \
  --exclude='rollback-wordpress.sh' --exclude='repo/' \
  --exclude='tests/' --exclude='run-ui-tests.sh' \
  --exclude='.svn-working-copy' --exclude='.svn' --exclude='.svn-credentials.sh' \
  --exclude='generate-help-docs.sh' --exclude='MANUAL-deploy-svn.sh' \
  --exclude='build-review.sh' --exclude='docs/' \
  --exclude='CloudScaleSEOAI.jpg' \
  "$REPO_DIR/" "$TEMP_DIR/$PLUGIN_NAME/"

# Build zip with correct structure
rm -f "$ZIP_FILE"
cd "$TEMP_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_NAME/"

# Cleanup
rm -rf "$TEMP_DIR"

echo ""
echo "Zip built: $ZIP_FILE"
echo ""
echo "Contents:"
unzip -l "$ZIP_FILE" | head -25
echo ""

# Show version and verify stable tag matches
VERSION=$(grep "^ \* Version:" "$REPO_DIR/cloudscale-seo-ai-optimizer.php" | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
STABLE_TAG=$(grep "^Stable tag:" "$REPO_DIR/readme.txt" | head -1 | sed 's/Stable tag:[[:space:]]*//' | tr -d '[:space:]')
echo "Plugin version: $VERSION"
echo "Stable tag:     $STABLE_TAG"
if [ "$VERSION" != "$STABLE_TAG" ]; then
  echo ""
  echo "ERROR: Version mismatch! Plugin version ($VERSION) != Stable tag ($STABLE_TAG)"
  echo "Update readme.txt Stable tag before deploying."
  exit 1
fi
echo "Version check: OK"
echo ""
echo "To deploy to S3, run:"
echo "  bash $SCRIPT_DIR/backup-s3.sh"
echo ""
echo "Then on the server:"
echo "  sudo aws s3 cp s3://your-s3-bucket/cloudscale-seo-ai-optimizer.zip /tmp/plugin.zip && sudo rm -rf /var/www/html/wp-content/plugins/cloudscale-seo-ai-optimizer && sudo unzip -q /tmp/plugin.zip -d /var/www/html/wp-content/plugins/ && sudo chown -R apache:apache /var/www/html/wp-content/plugins/cloudscale-seo-ai-optimizer && php -r \"if(function_exists('opcache_reset'))opcache_reset();\""
