#!/usr/bin/env bash
# Builds tds-school-hostinger.zip: extract it inside public_html on Hostinger, then open your domain.
# Usage: scripts/build-hostinger-zip.sh [output.zip]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(realpath -m "${1:-$ROOT/tds-school-hostinger.zip}")"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# app files (no git, tests, local data, dev tooling)
tar -C "$ROOT" -cf - \
  --exclude=./.git --exclude=./.github --exclude=./vendor --exclude=./node_modules --exclude=./tests \
  --exclude=./.env --exclude='./database/*.sqlite*' --exclude='./storage/logs/*.log' --exclude=./scripts \
  --exclude='./*.zip' --exclude=./phpunit.xml --exclude=./AGENTS.md --exclude=./CLAUDE.md --exclude=./CHANGELOG.md \
  --exclude=./package.json --exclude=./vite.config.js --exclude=./.phpunit.result.cache . | tar -C "$WORK" -xf -

cd "$WORK"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress -q
find vendor -name .git -type d -prune -exec rm -rf {} +
find storage -type f ! -name .gitignore -delete
rm -f bootstrap/cache/*.php

cat > READ-ME-FIRST.txt <<'TXT'
TDS School Management Software - Hostinger installation
1. hPanel -> Databases -> MySQL Databases: create a database + user. Note the database name, user and password.
2. hPanel -> Advanced -> PHP Configuration: PHP 8.3 (or newer). Extensions: sodium, pdo_mysql, mbstring, fileinfo, zip, gd ON.
3. hPanel -> File Manager -> public_html: delete the old files inside it (default.php etc.),
   upload this zip INTO public_html and Extract it there.
   public_html must now directly contain: .htaccess  app  bootstrap  public  vendor ...
4. Open https://your-domain/ - the installer opens. Enter the database details and your owner login.
5. Log in at https://your-domain/login - the owner panel opens. Add Razorpay keys in Settings.
TXT

rm -f "$OUT"
zip -qr "$OUT" . -x '*.DS_Store'
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
