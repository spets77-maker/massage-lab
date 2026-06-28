#!/usr/bin/env bash
# Upload .deploy-staging/ to remote staging via lftp (FTP/FTPS).
#
# 1) cp .deploy-credentials.example .deploy-credentials
# 2) Edit .deploy-credentials (FTP user/password from cPanel; not committed)
# 3) chmod +x scripts/deploy-staging.sh && ./scripts/deploy-staging.sh
#
# If your FTP password contains a comma, lftp -u user,pass breaks — set YML_DEPLOY_PASS
# in the shell and omit it from the file, or use a different FTP client.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CRED="$ROOT/.deploy-credentials"

if [[ -f "$CRED" ]]; then
  # shellcheck disable=SC1090
  source "$CRED"
fi

: "${YML_DEPLOY_HOST:?Set YML_DEPLOY_HOST in .deploy-credentials or env}"
: "${YML_DEPLOY_USER:?Set YML_DEPLOY_USER in .deploy-credentials or env}"
: "${YML_DEPLOY_PASS:?Set YML_DEPLOY_PASS in .deploy-credentials or env}"

REMOTE_DIR="${YML_REMOTE_DIR:-public_html/yuliasmassagelab.com/staging}"

"$ROOT/scripts/prep-deploy.sh"
STAGING="$ROOT/.deploy-staging"

if [[ ! -d "$STAGING" ]]; then
  echo "Missing $STAGING — prep-deploy failed?" >&2
  exit 1
fi

lftp <<EOF
set net:max-retries 3
set net:reconnect-interval-base 5
set ssl:verify-certificate no
set ftp:ssl-allow yes
set ftp:passive-mode yes
open -u ${YML_DEPLOY_USER},${YML_DEPLOY_PASS} ${YML_DEPLOY_HOST}
mkdir -p ${REMOTE_DIR}
cd ${REMOTE_DIR}
lcd "${STAGING}"
mirror -R --delete --verbose ./
bye
EOF

echo "Deployed to ftp://${YML_DEPLOY_HOST}/${REMOTE_DIR}"
