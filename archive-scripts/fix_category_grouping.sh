#!/bin/bash
set -e
cd /var/www/monexa

FILES=(
  "app/Services/WaParserService.php"
  "app/Services/CuanAiService.php"
  "app/Http/Controllers/App/ReportController.php"
  "app/Http/Controllers/App/DashboardController.php"
)

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "=== Backup & Patch ==="
for FILE in "${FILES[@]}"; do
  if [ ! -f "$FILE" ]; then
    echo "SKIP (file tidak ditemukan): $FILE"
    continue
  fi
  cp "$FILE" "${FILE}.bak_${TIMESTAMP}"
  sed -i "s/->groupBy('category_id')/->groupBy(fn(\$t) => \$t->category?->name ?? 'Lainnya')/g" "$FILE"
  echo "OK  : $FILE"
  echo "      (backup: ${FILE}.bak_${TIMESTAMP})"
done

echo ""
echo "=== Verifikasi hasil patch ==="
for FILE in "${FILES[@]}"; do
  [ -f "$FILE" ] || continue
  echo "--- $FILE ---"
  grep -n "groupBy(fn" "$FILE" || echo "  ⚠️  tidak ketemu, cek manual"
done

echo ""
echo "=== Cek syntax PHP ==="
for FILE in "${FILES[@]}"; do
  [ -f "$FILE" ] || continue
  php -l "$FILE"
done

php artisan config:clear
echo ""
echo "✅ SELESAI. BudgetController.php SENGAJA tidak disentuh"
echo "   (groupBy category_id di situ dipakai buat lookup budget, fungsinya beda)."
