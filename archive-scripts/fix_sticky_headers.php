<?php

function patchFile(string $path, array $replacements): void {
    if (!file_exists($path)) { echo "SKIP (tidak ditemukan): $path\n"; return; }
    $content = file_get_contents($path);
    $backupMade = false; $changed = 0;
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            if (!$backupMade) { copy($path, $path . '.bak_' . date('Ymd_His')); $backupMade = true; }
            $content = str_replace($old, $new, $content);
            $changed++;
        }
    }
    if ($changed > 0) { file_put_contents($path, $content); echo "OK ($changed patch): $path\n"; }
    else { echo "SKIP (pattern tidak ketemu/sudah diterapkan): $path\n"; }
}

// ─────────────────────────────────────────────
// 1. App/Dashboard.vue — header jadi card + sticky
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Dashboard.vue', [
    '.dash-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }' =>
    '.dash-header {
  display:flex; justify-content:space-between; align-items:center;
  background:var(--surface); border-radius:var(--radius-lg); padding:16px 18px;
  margin-bottom:14px; box-shadow:var(--shadow-card);
  position:sticky; top:0; z-index:40;
}',
]);

// ─────────────────────────────────────────────
// 2. App/Dompet.vue — header jadi card + sticky
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Dompet.vue', [
    '.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }' =>
    '.page-header {
  display:flex; justify-content:space-between; align-items:center;
  background:var(--surface); border-radius:var(--radius-lg); padding:16px 18px;
  margin-bottom:12px; box-shadow:var(--shadow-card);
  position:sticky; top:0; z-index:40;
}',
]);

// ─────────────────────────────────────────────
// 3. App/Report.vue — tambah sticky ke page-header-card yang udah ada
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Report.vue', [
    '.page-header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  margin-bottom: 12px;
  box-shadow: var(--shadow-card);
}' =>
    '.page-header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  margin-bottom: 12px;
  box-shadow: var(--shadow-card);
  position: sticky;
  top: 0;
  z-index: 40;
}',
]);

// ─────────────────────────────────────────────
// 4. App/Account.vue — header jadi card + sticky
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Account.vue', [
    '.page-header { margin-bottom:12px; }' =>
    '.page-header {
  background:var(--surface); border-radius:var(--radius-lg); padding:16px 18px;
  margin-bottom:12px; box-shadow:var(--shadow-card);
  position:sticky; top:0; z-index:40;
}',
]);

echo "\n=== SELESAI ===\n";
