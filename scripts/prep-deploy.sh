#!/usr/bin/env bash
# Build a clean tree under .deploy-staging/ (respects .deployignore).
# Then run lftp mirror from .deploy-staging, not from the repo root.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/.deploy-staging"
IGNORE="$ROOT/.deployignore"

rm -rf "$OUT"
mkdir -p "$OUT"

if [[ ! -f "$IGNORE" ]]; then
  echo "Missing .deployignore" >&2
  exit 1
fi

rsync -a \
  --exclude-from="$IGNORE" \
  "$ROOT/" "$OUT/"

echo "Ready: $OUT"
echo "Next (example):"
echo "  lftp -c \"open -u USER,PASS HOST; cd public_html/yuliasmassagelab.com/spets; lcd $OUT; mirror -R --delete --verbose ./\""
