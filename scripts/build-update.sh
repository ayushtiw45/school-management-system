#!/usr/bin/env bash
# Builds an update package for Owner panel → Software Update.
# Usage: scripts/build-update.sh [--with-vendor] ["what's new 1" "what's new 2" ...]
#   --with-vendor  include the PHP libraries (needed when composer.lock changed; the panel tells you if it is missing)
# Output: tds-update-<VERSION>[-full].zip   (VERSION comes from the VERSION file — bump it before building)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WITH_VENDOR=0
if [[ "${1:-}" == "--with-vendor" ]]; then WITH_VENDOR=1; shift; fi
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
SUFFIX=$([[ $WITH_VENDOR == 1 ]] && echo "-full" || echo "")
OUT="$ROOT/tds-update-${VERSION}${SUFFIX}.zip"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

tar -C "$ROOT" -cf - \
  --exclude=./.git --exclude=./.github --exclude=./vendor --exclude=./node_modules --exclude=./tests \
  --exclude=./.env --exclude=./storage --exclude='./database/*.sqlite*' --exclude=./scripts --exclude='./*.zip' \
  --exclude=./phpunit.xml --exclude=./AGENTS.md --exclude=./CLAUDE.md --exclude=./CHANGELOG.md \
  --exclude=./package.json --exclude=./vite.config.js --exclude=./.phpunit.result.cache \
  --exclude=./public/install.php --exclude='./bootstrap/cache/*.php' . | tar -C "$WORK" -xf -

if [[ $WITH_VENDOR == 1 ]]; then
  (cd "$WORK" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress -q \
     && find vendor -name .git -type d -prune -exec rm -rf {} + && rm -f bootstrap/cache/*.php)
fi

NOTES_JSON="$(python3 -c 'import json,sys; print(json.dumps(sys.argv[1:]))' "$@")"
cat > "$WORK/update.json" <<JSON
{
  "app": "tds-school",
  "version": "${VERSION}",
  "built_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "php": "8.3.0",
  "vendor": $([[ $WITH_VENDOR == 1 ]] && echo true || echo false),
  "composer_lock": "$(sha1sum "$ROOT/composer.lock" | cut -d' ' -f1)",
  "notes": ${NOTES_JSON}
}
JSON

rm -f "$OUT"
(cd "$WORK" && zip -qr "$OUT" . -x '*.DS_Store')
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
