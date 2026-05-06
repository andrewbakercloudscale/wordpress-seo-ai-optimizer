#!/usr/bin/env bash
# Reads .payfast-credentials, writes config.php and deploys updated PHP files to the Pi.
# Run: bash proxy-api/setup-config.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CREDS="${SCRIPT_DIR}/.payfast-credentials"

if [[ ! -f "$CREDS" ]]; then
    echo "ERROR: $CREDS not found"; exit 1
fi

# shellcheck source=/dev/null
source "$CREDS"

PI_KEY="${HOME}/.cloudflared/pi-service-key"
SSH_OPTS=(-i "$PI_KEY" -o "ProxyCommand=${HOME}/.cloudflared/cf-ssh-proxy.sh" -o StrictHostKeyChecking=no)
REMOTE="pi@ssh.andrewbaker.ninja"

# Write config.php
ssh "${SSH_OPTS[@]}" "$REMOTE" "cat > /tmp/proxy-api-config.php" << EOF
<?php
define('ANTHROPIC_API_KEY',   '${ANTHROPIC_API_KEY}');
define('PAYFAST_MERCHANT_ID', '${PAYFAST_MERCHANT_ID}');
define('PAYFAST_MERCHANT_KEY','${PAYFAST_MERCHANT_KEY}');
define('PAYFAST_PASSPHRASE',  '${PAYFAST_PASSPHRASE}');
define('PAYFAST_TESTING',     false);
define('DB_PATH',             __DIR__ . '/data/licenses.db');
define('PROXY_BASE_URL',      'https://api.andrewbaker.ninja');
EOF

ssh "${SSH_OPTS[@]}" "$REMOTE" "sudo mv /tmp/proxy-api-config.php /var/www/proxy-api/config.php && sudo chown www-data:www-data /var/www/proxy-api/config.php && sudo chmod 640 /var/www/proxy-api/config.php"
echo "config.php written to Pi."

# Deploy updated PHP files
for f in db.php index.php status.php webhook.php checkout.php cancel.php billing-portal.php payfast.php manage.php; do
    if [[ -f "${SCRIPT_DIR}/${f}" ]]; then
        scp "${SSH_OPTS[@]}" "${SCRIPT_DIR}/${f}" "${REMOTE}:/tmp/proxy-${f}"
        ssh "${SSH_OPTS[@]}" "$REMOTE" "sudo mv /tmp/proxy-${f} /var/www/proxy-api/${f} && sudo chown www-data:www-data /var/www/proxy-api/${f} && sudo chmod 640 /var/www/proxy-api/${f}"
        echo "${f} deployed."
    fi
done

echo "All done."
