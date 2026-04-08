#!/usr/bin/env bash
set -euo pipefail

PI_KEY="REPO_BASE/pi-monitor/deploy/pi_key"
CONTAINER="pi_wordpress"
WP_PATH="/var/www/html"
WP_CLI="php ${WP_PATH}/wp-cli.phar --allow-root"

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/tests" && pwd)"

# ── Auto-detect network: direct SSH on home network, Cloudflare tunnel off it ──
PI_LOCAL="andrew-pi-5.local"
CF_HOSTNAME="ssh.andrewbaker.ninja"
# ── SSH ControlMaster — single persistent connection reused for all commands ─
CTRL_SOCK="/tmp/pw-ui-test-$$"
if nc -z -w2 "$PI_LOCAL" 22 2>/dev/null; then
    PI_HOST="pi@${PI_LOCAL}"
    pi_ssh() { ssh -i "${PI_KEY}" -o StrictHostKeyChecking=no -o LogLevel=ERROR -o ControlMaster=auto -o ControlPath="${CTRL_SOCK}" -o ControlPersist=yes -o ServerAliveInterval=15 -o ServerAliveCountMax=10 "${PI_HOST}" "$@"; }
    echo "Network: home — direct SSH"
else
    PI_HOST="pi@${CF_HOSTNAME}"
    pi_ssh() { ssh -i "${PI_KEY}" -o "ProxyCommand=cloudflared access ssh --hostname ${CF_HOSTNAME}" -o StrictHostKeyChecking=no -o LogLevel=ERROR -o ControlMaster=auto -o ControlPath="${CTRL_SOCK}" -o ControlPersist=yes -o ServerAliveInterval=15 -o ServerAliveCountMax=10 "${PI_HOST}" "$@"; }
    echo "Network: remote — Cloudflare tunnel"
fi

run_remote()  { pi_ssh "$@"; }
run_wp()      { run_remote "docker exec ${CONTAINER} ${WP_CLI} $*"; }
run_wp_php()  { pi_ssh "docker exec -i ${CONTAINER} ${WP_CLI} eval-file - --path=${WP_PATH}"; }

close_ssh() {
    ssh -i "${PI_KEY}" -o ControlPath="${CTRL_SOCK}" -o LogLevel=ERROR \
        -O exit "${PI_HOST}" 2>/dev/null || true
}

# ── Load config from .env ────────────────────────────────────────────────────
if [[ ! -f "$TESTS_DIR/.env" ]]; then
    echo "ERROR: $TESTS_DIR/.env not found."
    echo "  cp $TESTS_DIR/.env.example $TESTS_DIR/.env  then set WP_BASE_URL"
    exit 1
fi
WP_BASE_URL=$(grep '^WP_BASE_URL=' "$TESTS_DIR/.env" | cut -d'=' -f2- | tr -d '\r')
[[ -z "$WP_BASE_URL" ]] && { echo "ERROR: WP_BASE_URL not set in .env"; exit 1; }

# ── Open the persistent SSH connection ─────────────────────────────────────
echo "--- Connecting to server..."
run_remote "echo 'Connection OK'"

# ── Check wp-cli ─────────────────────────────────────────────────────────────
if ! run_remote "docker exec ${CONTAINER} ${WP_CLI} --info >/dev/null 2>&1"; then
    echo "ERROR: wp-cli not found on server. Install: https://wp-cli.org/#installing"
    close_ssh; exit 1
fi

# ── Generate one-time credentials ────────────────────────────────────────────
TEST_USER="pw_runner_$(date +%s)"
TEST_EMAIL="${TEST_USER}@test.invalid"
TEST_PASS="$(openssl rand -hex 12)"

# ── Cleanup: always runs on exit, Ctrl+C, or test failure ────────────────────
cleanup() {
    echo ""
    if [[ -n "${TEST_USER:-}" ]]; then
        echo "--- Deleting test account ${TEST_USER}..."
        run_wp "user delete '${TEST_USER}' --yes --path=${WP_PATH} 2>/dev/null || true"
        echo "--- Test account deleted."
    fi
    close_ssh
}
trap cleanup EXIT

# ── Create the temporary admin account ───────────────────────────────────────
echo "--- Creating temporary test account: ${TEST_USER}"
run_wp "user create '${TEST_USER}' '${TEST_EMAIL}' \
    --role=administrator \
    --user_pass='${TEST_PASS}' \
    --path=${WP_PATH}"
echo "--- Test account created."

# ── Mark test user as excluded from 2FA enforcement ──────────────────────────
# WP 2FA re-evaluates enforcement state on first access if the user's stored
# settings hash doesn't match the global hash.  We set BOTH the state and the
# hash so the re-evaluation is skipped and our 'excluded' value sticks.
printf '<?php
$user = get_user_by("login", "%s");
if (!$user) { die("User not found\n"); }
// WP_2FA_PREFIX may not be defined in WP-CLI context if plugin loads lazily
$prefix = defined("WP_2FA_PREFIX") ? WP_2FA_PREFIX : "wp_2fa_";
$hash   = get_option($prefix . "settings_hash", "");
update_user_meta($user->ID, "wp_2fa_enforcement_state",    "excluded");
update_user_meta($user->ID, "wp_2fa_global_settings_hash", $hash);
echo "2FA_OK\n";
' "$TEST_USER" | run_wp_php 2>/dev/null | grep '2FA_OK\|not found' || true
echo "--- 2FA excluded."

# ── Generate WordPress auth cookies via PHP — bypasses login form, 2FA, etc. ─
# Playwright injects these directly so it never needs to touch the login page.
echo "--- Generating auth cookies for test session..."
WP_COOKIES=$(printf '<?php
$user = get_user_by("login", "%s");
if (!$user) { echo json_encode(["error"=>"user not found"]); exit(1); }
$expiration = time() + 7200;
$manager = WP_Session_Tokens::get_instance($user->ID);
$token   = $manager->create($expiration);
$hash    = md5(get_option("siteurl"));
$domain  = parse_url(get_option("siteurl"), PHP_URL_HOST);
echo json_encode([
    "auth_name"    => "wordpress_" . $hash,
    "auth_value"   => wp_generate_auth_cookie($user->ID, $expiration, "auth",        $token),
    "sec_name"     => "wordpress_sec_" . $hash,
    "sec_value"    => wp_generate_auth_cookie($user->ID, $expiration, "secure_auth", $token),
    "login_name"   => "wordpress_logged_in_" . $hash,
    "login_value"  => wp_generate_auth_cookie($user->ID, $expiration, "logged_in",   $token),
    "domain"       => $domain,
    "expiration"   => $expiration,
]);' "$TEST_USER" | run_wp_php)

if echo "$WP_COOKIES" | grep -q '"error"'; then
    echo "ERROR: Failed to generate auth cookies: $WP_COOKIES"
    exit 1
fi
echo "--- Auth cookies generated."
echo ""

# ── Install Node deps + Playwright browser on first run ──────────────────────
cd "$TESTS_DIR"
if [[ ! -d node_modules ]]; then
    echo "--- Installing Playwright dependencies..."
    npm install
    npx playwright install chromium
fi

# ── Run tests (cookies as env var — no credentials ever written to disk) ─────
echo "--- Running UI regression tests..."
WP_BASE_URL="${WP_BASE_URL}" WP_COOKIES="${WP_COOKIES}" \
    npx playwright test "$@"
