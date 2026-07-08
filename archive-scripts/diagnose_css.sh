#!/bin/bash
echo "===================================================="
echo "1. Isi folder public/build"
echo "===================================================="
ls -la /var/www/monexa/public/build/ 2>&1
echo ""
ls -la /var/www/monexa/public/build/assets/ 2>&1 | head -10

echo ""
echo "===================================================="
echo "2. Manifest Vite"
echo "===================================================="
cat /var/www/monexa/public/build/manifest.json 2>&1 | head -20

echo ""
echo "===================================================="
echo "3. APP_URL di .env"
echo "===================================================="
grep "^APP_URL" /var/www/monexa/.env

echo ""
echo "===================================================="
echo "4. HTML yang dikirim ke browser untuk /admin (cek tag css/js)"
echo "===================================================="
curl -s http://103.247.11.62/admin | grep -Eo '(href|src)="[^"]*\.(css|js)"'

echo ""
echo "===================================================="
echo "5. Coba akses langsung salah satu file CSS via curl"
echo "===================================================="
CSS_FILE=$(curl -s http://103.247.11.62/admin | grep -Eo 'href="[^"]*app[^"]*\.css"' | head -1 | sed 's/href="//;s/"//')
echo "File yang dicoba: $CSS_FILE"
if [ -n "$CSS_FILE" ]; then
  curl -sI "http://103.247.11.62${CSS_FILE}" 2>&1 | head -5
fi

echo ""
echo "===================================================="
echo "6. Config Nginx aktif"
echo "===================================================="
cat /etc/nginx/sites-enabled/*.conf 2>&1 || cat /etc/nginx/sites-available/*.conf 2>&1

echo ""
echo "===================================================="
echo "7. Permission folder public/build"
echo "===================================================="
stat /var/www/monexa/public/build 2>&1 | head -5
namei -om /var/www/monexa/public/build/assets 2>&1 | tail -15

echo ""
echo "===================================================="
echo "8. Cek error log Nginx terbaru"
echo "===================================================="
tail -30 /var/log/nginx/error.log 2>&1

echo ""
echo "===================================================="
echo "SELESAI — kirim semua output di atas"
echo "===================================================="
