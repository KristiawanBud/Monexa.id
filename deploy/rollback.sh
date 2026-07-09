#!/bin/bash
# rollback.sh — Rollback instan, dipakai untuk SEMUA proyek Athena AI Dev.
# Dipanggil oleh n8n dengan 1 argumen wajib: base path repo (sama seperti argumen deploy.sh).
#
#   Contoh: bash rollback.sh /var/www/monexa
#
# Output:
#   sukses -> stdout "ROLLBACK_OK:<nama_release_lama>"
#   gagal  -> stdout "ROLLBACK_FAILED: <alasan>"

GIT_REPO="$1"
if [ -z "$GIT_REPO" ]; then
  echo "ROLLBACK_FAILED: argumen base path wajib diisi, contoh: bash rollback.sh /var/www/monexa"
  exit 1
fi

RELEASES_DIR="${GIT_REPO}-releases"
LIVE_LINK="${GIT_REPO}-live"

fail() {
  echo "ROLLBACK_FAILED: $1"
  exit 1
}

[ -L "$LIVE_LINK" ] || fail "symlink live tidak ditemukan di $LIVE_LINK"

CURRENT_TARGET=$(readlink -f "$LIVE_LINK")
CURRENT_NAME=$(basename "$CURRENT_TARGET")

# Urutkan release dari yang terbaru, cari nama tepat SETELAH release yang aktif sekarang
# (artinya itu release sebelumnya secara kronologis)
PREV=$(ls -1t "$RELEASES_DIR" | awk -v cur="$CURRENT_NAME" '
  found==1 { print; exit }
  $0==cur { found=1 }
')

[ -n "$PREV" ] || fail "tidak ada release sebelumnya untuk di-rollback (cuma ada 1 release)"
[ "$PREV" != "$CURRENT_NAME" ] || fail "release sebelumnya sama dengan yang aktif sekarang"

ln -nfs "$RELEASES_DIR/$PREV" "$LIVE_LINK" || fail "gagal switch symlink ke release sebelumnya"

sudo systemctl reload php-fpm 2>/dev/null || true
php artisan queue:restart 2>/dev/null || true

echo "ROLLBACK_OK:$PREV"
