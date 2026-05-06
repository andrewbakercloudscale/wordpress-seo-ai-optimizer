#!/usr/bin/env bash
# Deploy proxy-api/ to Pi host at /var/www/proxy-api/
# Usage: bash proxy-api/deploy-proxy.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PI_KEY="${SCRIPT_DIR}/../pi-monitor/deploy/pi_key"
REMOTE_DIR="/var/www/proxy-api"

if ssh -i "$PI_KEY" -o StrictHostKeyChecking=no -o ConnectTimeout=3 \
       "pi@andrew-pi-5.local" "exit" 2>/dev/null; then
    PI_HOST="andrew-pi-5.local"
    SSH_OPTS=(-i "$PI_KEY" -o StrictHostKeyChecking=no)
else
    PI_HOST="ssh.andrewbaker.ninja"
    SSH_OPTS=(-i "${HOME}/.cloudflared/pi-service-key"
              -o "ProxyCommand=${HOME}/.cloudflared/cf-ssh-proxy.sh"
              -o StrictHostKeyChecking=no)
fi

echo "Deploying proxy-api/ → pi@${PI_HOST}:${REMOTE_DIR}"

rsync -avz --delete \
    --exclude=config.php \
    --exclude='data/' \
    -e "ssh ${SSH_OPTS[*]}" \
    "$SCRIPT_DIR/" \
    "pi@${PI_HOST}:${REMOTE_DIR}/"

ssh "${SSH_OPTS[@]}" "pi@${PI_HOST}" "
    mkdir -p ${REMOTE_DIR}/data
    chmod 750 ${REMOTE_DIR}/data
    chmod 640 ${REMOTE_DIR}/config.php 2>/dev/null || true
    echo 'Proxy deployed OK'
"

echo "Done."
