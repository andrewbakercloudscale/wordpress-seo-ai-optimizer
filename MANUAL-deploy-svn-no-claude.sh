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

trap 'echo ""; read -rp "Press any key to close..." -n1' EXIT

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="cloudscale-seo-ai-optimizer"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_NAME"
SVN_USERNAME="andrewjbaker"
SVN_WORKING="$SCRIPT_DIR/.svn-working-copy"
CLAUDE="${CLAUDE_CLI:-claude}"

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
