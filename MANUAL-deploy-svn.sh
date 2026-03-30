#!/usr/bin/env bash
# Deploy plugin to WordPress.org SVN.
#
# Pre-flight gates (all must pass before SVN is touched):
#   1. WordPress plugin standards review — zero Critical, High, or Medium findings
#   2. CHANGELOG.md contains an entry for the current version
#   3. readme.txt contains an entry for the current version
#
# Usage:
#   bash MANUAL-deploy-svn.sh            # full run with all gates
#   bash MANUAL-deploy-svn.sh --force    # skip standards review (emergency only)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="cloudscale-seo-ai-optimizer"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_NAME"
SVN_USERNAME="andrewjbaker"
SVN_WORKING="$SCRIPT_DIR/.svn-working-copy"
CLAUDE="/opt/homebrew/bin/claude"

# ── Flags ─────────────────────────────────────────────────────────────────────
FORCE=0
for arg in "$@"; do
    [[ "$arg" == "--force" ]] && FORCE=1
done

# ── Load Claude model config (shared with build.sh) ───────────────────────────
GITHUB_DIR="$(dirname "$SCRIPT_DIR")"
# shellcheck source=../.claude-config.sh
if [[ -f "$GITHUB_DIR/.claude-config.sh" ]]; then
    source "$GITHUB_DIR/.claude-config.sh"
fi
: "${CLAUDE_REVIEW_MODEL:=claude-opus-4-5}"

# ── Credentials ──────────────────────────────────────────────────────────────
CREDS_FILE="$SCRIPT_DIR/.svn-credentials.sh"
if [[ -f "$CREDS_FILE" ]]; then
    # shellcheck source=.svn-credentials.sh
    source "$CREDS_FILE"
fi
if [[ -z "${SVN_PASSWORD:-}" ]]; then
    echo -n "SVN password: "
    read -rs SVN_PASSWORD
    echo
fi
SVN_AUTH="--username $SVN_USERNAME --password $SVN_PASSWORD --non-interactive"

# ── Version ───────────────────────────────────────────────────────────────────
VERSION=$(grep "^ \* Version:" "$SCRIPT_DIR/cloudscale-seo-ai-optimizer.php" \
    | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
if [[ -z "$VERSION" ]]; then
    echo "ERROR: Could not read version from plugin header."
    exit 1
fi
echo "Preparing WordPress.org SVN release for v$VERSION..."
echo ""

# ═════════════════════════════════════════════════════════════════════════════
# PRE-FLIGHT 1 — WordPress plugin standards review
# ═════════════════════════════════════════════════════════════════════════════
if [[ "$FORCE" -eq 1 ]]; then
    echo -e "\033[1;33m⚠️  --force passed — skipping standards review.\033[0m"
    echo ""
elif [[ ! -x "$CLAUDE" ]]; then
    echo -e "\033[1;31mERROR: claude CLI not found at $CLAUDE.\033[0m"
    echo "       Install claude CLI or pass --force to skip the review."
    exit 1
else
    # ── Colour helpers ────────────────────────────────────────────────────────
    _print_review() {
        # Print review text with colour coding:
        #   section headers  → bold cyan
        #   FAIL lines       → bold red
        #   PASS lines       → bold green
        #   everything else  → white (bright)
        while IFS= read -r line; do
            if [[ "$line" =~ ^---.*---$ ]]; then
                echo -e "\033[1;36m$line\033[0m"
            elif echo "$line" | grep -qiE 'BUILD_STATUS: FAIL|critical|high severity|medium severity'; then
                echo -e "\033[1;31m$line\033[0m"
            elif echo "$line" | grep -q 'BUILD_STATUS: PASS'; then
                echo -e "\033[1;32m$line\033[0m"
            else
                echo -e "\033[0;97m$line\033[0m"
            fi
        done <<< "$1"
    }

    echo -e "\033[1;36m════════════════════════════════════════════════════════════════\033[0m"
    echo -e "\033[1;36m  WordPress Plugin Standards Review\033[0m"
    echo -e "\033[1;36m  Critical, High, and Medium findings all block release\033[0m"
    echo -e "\033[1;36m════════════════════════════════════════════════════════════════\033[0m"
    echo ""

    REVIEW_TMPDIR=$(mktemp -d)

    _review_section() {
        local label="$1"; shift
        local file_list="$*"
        echo "--- Section: $label ---" > "$REVIEW_TMPDIR/$label.txt"
        (cd "$SCRIPT_DIR" && timeout 600 "$CLAUDE" \
            --dangerously-skip-permissions \
            --model "$CLAUDE_REVIEW_MODEL" \
            --print -p \
            "/wp-plugin-standards-review Review ONLY these files (read no others): $file_list.

BLOCKING RULES — Critical, High, or Medium issues trigger BUILD_STATUS: FAIL:
1. SQL injection: user-controlled input in a SQL query WITHOUT \$wpdb->prepare() and without cast/validated
2. XSS: user-controlled data echoed into HTML WITHOUT esc_html/esc_attr/esc_url/wp_kses
3. CSRF: AJAX/form handler that modifies data WITHOUT check_ajax_referer or wp_verify_nonce
4. Missing ABSPATH guard at the top of a PHP file
5. Medium severity: hardcoded credentials, arbitrary file read/write, unsafe deserialization, open redirects

NON-BLOCKING (never trigger FAIL, document as informational only):
- SQL queries with phpcs:ignore comments — already acknowledged, skip entirely
- Table name interpolations using \$wpdb->prefix, \$wpdb->posts, \$wpdb->postmeta — always safe
- Unicode characters, em dashes, or emoji used as display/placeholder values — not suspicious
- wp_unslash() + esc_url_raw() on \$_SERVER — correct WP pattern
- \$wpdb->get_results( \$wpdb->prepare(...) ) — correct WP pattern
- implode of integer-cast IDs for IN clauses — safe
- Missing @since or DocBlock tags — documentation only, not security
- Version numbers matching between header and constant — not a violation

End your response with EXACTLY one of: BUILD_STATUS: PASS or BUILD_STATUS: FAIL" \
            >> "$REVIEW_TMPDIR/$label.txt" 2>&1) || true
    }

    # Sections mirror build.sh, with trait-redirects.php added to s6
    _review_section "s1" cloudscale-seo-ai-optimizer.php includes/class-cloudscale-seo-ai-optimizer-utils.php uninstall.php includes/trait-auto-pipeline.php readme.txt &
    _review_section "s2" includes/trait-settings-page.php &
    _review_section "s3" includes/trait-settings-assets.php includes/trait-admin.php includes/trait-metabox.php includes/trait-gutenberg.php includes/trait-options.php includes/trait-summary-box.php &
    _review_section "s4" includes/trait-ai-meta-writer.php includes/trait-ai-engine.php includes/trait-ai-scoring.php includes/trait-ai-summary.php includes/trait-ai-alt-text.php readme.txt &
    _review_section "s5" includes/trait-batch-scheduler.php includes/trait-category-fixer.php includes/trait-related-articles.php includes/trait-frontend-head.php includes/trait-schema.php includes/trait-https-fixer.php &
    _review_section "s6" includes/trait-font-optimizer.php includes/trait-minifier.php includes/trait-sitemap.php includes/trait-llms-txt.php includes/trait-robots-txt.php includes/trait-seo-health.php includes/trait-redirects.php readme.txt &

    wait

    REVIEW=$(cat "$REVIEW_TMPDIR"/*.txt)
    rm -rf "$REVIEW_TMPDIR"

    # Print the review in colour
    _print_review "$REVIEW"
    echo ""
    echo -e "\033[1;36m════════════════════════════════════════════════════════════════\033[0m"
    echo ""

    # API/model error — review did not run
    if echo "$REVIEW" | grep -qiE 'API Error|invalid.*model|model.*invalid'; then
        echo -e "\033[1;31m✖  Standards review failed — model API error.\033[0m"
        echo "   Check CLAUDE_REVIEW_MODEL in .claude-config.sh or pass --force."
        exit 1
    fi

    # Must have at least one BUILD_STATUS line — missing means model did not complete
    if ! echo "$REVIEW" | grep -qE 'BUILD_STATUS: (PASS|FAIL)'; then
        echo -e "\033[1;31m✖  Standards review did not return BUILD_STATUS — model output incomplete.\033[0m"
        echo "   Pass --force to bypass (emergency only)."
        exit 1
    fi

    if echo "$REVIEW" | grep -q 'BUILD_STATUS: FAIL'; then
        echo -e "\033[1;31m✖  RELEASE BLOCKED — standards review found Critical, High, or Medium issues.\033[0m"
        echo -e "\033[1;31m   Fix all findings above before releasing to WordPress.org.\033[0m"
        exit 1
    fi

    echo -e "\033[1;32m✔  Standards review passed — no Critical, High, or Medium findings.\033[0m"
    echo ""
fi

# ═════════════════════════════════════════════════════════════════════════════
# PRE-FLIGHT 2 — CHANGELOG.md entry
# ═════════════════════════════════════════════════════════════════════════════
echo "Checking CHANGELOG.md for v$VERSION entry..."
if ! grep -q "\[$VERSION\]" "$SCRIPT_DIR/CHANGELOG.md"; then
    echo -e "\033[1;31m✖  RELEASE BLOCKED — CHANGELOG.md has no entry for v$VERSION.\033[0m"
    echo "   Add a '## [$VERSION]' section before releasing."
    exit 1
fi
echo -e "\033[1;32m✔  CHANGELOG.md: entry found.\033[0m"
echo ""

# ═════════════════════════════════════════════════════════════════════════════
# PRE-FLIGHT 3 — readme.txt entry
# ═════════════════════════════════════════════════════════════════════════════
echo "Checking readme.txt for v$VERSION entry..."
if ! grep -q "= $VERSION =" "$SCRIPT_DIR/readme.txt"; then
    echo -e "\033[1;31m✖  RELEASE BLOCKED — readme.txt has no changelog entry for v$VERSION.\033[0m"
    echo "   Add a '= $VERSION =' section under == Changelog == before releasing."
    exit 1
fi
echo -e "\033[1;32m✔  readme.txt: entry found.\033[0m"
echo ""

# ═════════════════════════════════════════════════════════════════════════════
# SVN SYNC
# ═════════════════════════════════════════════════════════════════════════════
echo -e "\033[1;32m✔  All pre-flight checks passed. Proceeding with SVN release...\033[0m"
echo ""

# ── Checkout or update working copy ──────────────────────────────────────────
if [[ ! -d "$SVN_WORKING/.svn" ]]; then
    echo "Checking out SVN repository (first run — this may take a while)..."
    svn co "$SVN_URL" "$SVN_WORKING" $SVN_AUTH
else
    echo "Updating SVN working copy..."
    svn up "$SVN_WORKING" $SVN_AUTH
fi

# ── Sync repo/ → trunk/ ──────────────────────────────────────────────────────
echo ""
echo "Syncing repo/ to trunk/..."
rsync -a --delete \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='*.zip' \
    --exclude='.DS_Store' \
    --exclude='._*' \
    "$SCRIPT_DIR/repo/" "$SVN_WORKING/trunk/"

# Remove any dot-files rsync may have copied (WordPress.org rejects them)
find "$SVN_WORKING/trunk" -name ".*" ! -path "*/.svn*" -delete 2>/dev/null || true

# ── Stage adds and deletes ────────────────────────────────────────────────────
cd "$SVN_WORKING"

svn status trunk | awk '/^\?/{print $2}' | while IFS= read -r f; do
    svn add --force "$f"
done

svn status trunk | awk '/^!/{print $2}' | while IFS= read -r f; do
    svn delete --force "$f"
done

# ── Show diff summary ─────────────────────────────────────────────────────────
echo ""
echo "Changes staged for commit:"
svn status trunk
echo ""

# ── Commit trunk ─────────────────────────────────────────────────────────────
if svn status trunk | grep -qE "^[AMDR]"; then
    svn ci trunk -m "Update trunk to v$VERSION" $SVN_AUTH
    echo "Trunk committed."
else
    echo "Trunk already up to date — skipping trunk commit."
fi

# ── Tag the release ───────────────────────────────────────────────────────────
echo ""
if svn ls "$SVN_URL/tags/$VERSION" $SVN_AUTH > /dev/null 2>&1; then
    echo "Tag $VERSION already exists — replacing..."
    svn rm --force "tags/$VERSION"
    svn ci -m "Remove stale tag $VERSION" $SVN_AUTH
fi

echo "Tagging v$VERSION..."
svn cp trunk "tags/$VERSION"
svn ci -m "Tag version $VERSION" $SVN_AUTH

echo ""
echo "✅ Done. v$VERSION is live on WordPress.org."
echo "   Plugin page : https://wordpress.org/plugins/$PLUGIN_NAME/"
echo "   SVN browser : https://plugins.svn.wordpress.org/$PLUGIN_NAME/"
echo ""
read -rp "Press any key to close..." -n1
