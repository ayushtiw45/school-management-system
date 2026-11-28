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
TDS School Management Software - install on schoolapp.thedigitalstrokes.in (Hostinger)

IMPORTANT: your main website thedigitalstrokes.in (WordPress) lives in public_html.
Do NOT delete or extract anything into the main public_html. This app goes into the SUBDOMAIN folder only.

1. hPanel -> Domains -> Subdomains: create "schoolapp" for thedigitalstrokes.in.
   Note its folder (usually public_html/schoolapp).
2. hPanel -> Databases -> MySQL Databases: create a NEW database + user for this app
   (do not use the WordPress database). Note the database name, user and password.
3. hPanel -> Advanced -> PHP Configuration: PHP 8.3 or newer; extensions sodium, pdo_mysql, mbstring, fileinfo, zip, gd ON.
4. hPanel -> File Manager -> open the subdomain folder (public_html/schoolapp). If Hostinger put a default
   index/default.php there, delete only that file. Upload this zip into that folder and Extract it there.
   The folder must now directly contain: .htaccess  app  bootstrap  public  vendor ...
5. hPanel -> Security -> SSL: install SSL for schoolapp.thedigitalstrokes.in.
6. Open https://schoolapp.thedigitalstrokes.in/ - the installer opens. Enter the database details and your owner login.
7. Log in at https://schoolapp.thedigitalstrokes.in/login - the owner panel opens. Add Razorpay keys in Settings.
TXT

rm -f "$OUT"
zip -qr "$OUT" . -x '*.DS_Store'
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
