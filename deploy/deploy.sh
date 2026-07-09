#!/bin/bash
# deploy.sh — Blue-Green / symlink release deploy, dipakai untuk SEMUA proyek Athena AI Dev.
# Dipanggil oleh n8n dengan 1 argumen wajib: base path repo (tanpa suffix apa pun).
#
#   Contoh: bash deploy.sh /var/www/monexa
#
# Dari 1 argumen itu, script menurunkan sendiri:
#   GIT_REPO="/var/www/monexa"            <- argumen yang dikasih
#   RELEASES_DIR="/var/www/monexa-releases"
#   SHARED_DIR="/var/www/monexa-shared"
#   LIVE_LINK="/var/www/monexa-live"
#
# Jadi proyek lain (misal /var/www/toko-budi) otomatis dapat folder blue-green sendiri
# tanpa perlu edit script ini sama sekali - cukup daftarkan repo_path proyek itu di
# projects.json, n8n yang akan mengoper base path yang benar ke argumen ini.
#
# Exit code & output:
#   sukses -> stdout mengandung "DEPLOY_OK:<timestamp>"
#   gagal  -> stdout mengandung "DEPLOY_FAILED", exit code != 0

set -o pipefail

GIT_REPO="$1"
if [ -z "$GIT_REPO" ]; then
  echo "DEPLOY_FAILED: argumen base path wajib diisi, contoh: bash deploy.sh /var/www/monexa"
  exit 1
fi

RELEASES_DIR="${GIT_REPO}-releases"
SHARED_DIR="${GIT_REPO}-shared"
LIVE_LINK="${GIT_REPO}-live"
KEEP_RELEASES=5

fail() {
  echo "DEPLOY_FAILED: $1"
  exit 1
}

TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

mkdir -p "$RELEASES_DIR" "$SHARED_DIR"

# 1) Salin state repo (yang sudah di-checkout ke commit target oleh n8n) ke folder release baru
rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' "$GIT_REPO/" "$NEW_RELEASE/" \
  || fail "rsync gagal menyalin ke release baru"

# 2) Sambungkan file persisten (jangan sampai .env / storage ketimpa tiap release)
if [ ! -f "$SHARED_DIR/.env" ]; then
  fail ".env tidak ditemukan di $SHARED_DIR — jalankan migrasi awal dulu (lihat panduan)"
fi
ln -nfs "$SHARED_DIR/.env" "$NEW_RELEASE/.env"
mkdir -p "$SHARED_DIR/storage"
rm -rf "$NEW_RELEASE/storage"
ln -nfs "$SHARED_DIR/storage" "$NEW_RELEASE/storage"

cd "$NEW_RELEASE" || fail "tidak bisa masuk ke folder release baru"

# 3) Build & migrate DI FOLDER RELEASE BARU (bukan di live), jadi kalau gagal, live belum kesentuh sama sekali
composer install --no-dev --optimize-autoloader --no-interaction || fail "composer install gagal"
php artisan migrate --force || fail "migration gagal"
if [ -f package.json ]; then
  npm ci && npm run build || fail "npm build gagal"
fi
php artisan optimize:clear
php artisan optimize

# 4) Switch atomik — ini satu-satunya langkah yang "menyalakan" release baru ke production
ln -nfs "$NEW_RELEASE" "$LIVE_LINK" || fail "gagal switch symlink live"

# 5) Restart proses yang perlu baca kode baru
sudo systemctl reload php-fpm 2>/dev/null || true
php artisan queue:restart 2>/dev/null || true

# 6) Beres-beres: simpan cuma 5 release terakhir biar disk nggak penuh
cd "$RELEASES_DIR" && ls -1t | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "DEPLOY_OK:$TIMESTAMP"
