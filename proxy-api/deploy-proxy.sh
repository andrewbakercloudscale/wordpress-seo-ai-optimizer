#!/usr/bin/env bash
# Deploy proxy-api/ to Pi host at /var/www/proxy-api/
# Usage: bash proxy-api/deploy-proxy.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REMOTE_DIR="/var/www/proxy-api"

# Detect network (same logic as deploy-wordpress.sh)
if ping -c1 -W1 andrew-pi-5.local &>/dev/null 2>&1; then
    SSH_HOST="andrew-pi-5.local"
    SSH_KEY="$(dirname "$SCRIPT_DIR")/pi-monitor/deploy/pi_key"
    SSH_OPTS="-i $SSH_KEY -o StrictHostKeyChecking=no"
else
    SSH_HOST="ssh.andrewbaker.ninja"
    CF_PROXY="$(dirname "$SCRIPT_DIR")/cf-ssh-proxy.sh"
    SSH_KEY="$HOME/.cloudflared/pi-service-key"
    SSH_OPTS="-i $SSH_KEY -o StrictHostKeyChecking=no -o ProxyCommand='$CF_PROXY %h %p'"
fi

echo "Deploying proxy-api/ → ${SSH_HOST}:${REMOTE_DIR}"

# Files to deploy (never overwrite config.php — that has live secrets)
EXCLUDE=(config.php data)
RSYNC_EXCLUDES=()
for e in "${EXCLUDE[@]}"; do RSYNC_EXCLUDES+=(--exclude="$e"); done

rsync -avz --delete "${RSYNC_EXCLUDES[@]}" \
    -e "ssh ${SSH_OPTS}" \
    "$SCRIPT_DIR/" \
    "andrew@${SSH_HOST}:${REMOTE_DIR}/"

# Ensure data dir exists and is protected
ssh ${SSH_OPTS} "andrew@${SSH_HOST}" "
    mkdir -p ${REMOTE_DIR}/data
    chmod 750 ${REMOTE_DIR}/data
    chmod 640 ${REMOTE_DIR}/config.php 2>/dev/null || true
"

echo "Done."
