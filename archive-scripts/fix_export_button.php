<?php

$file = '/var/www/monexa/resources/js/Pages/App/Report.vue';

if (!file_exists($file)) {
    echo "SKIP: file tidak ditemukan\n";
    exit;
}

$content = file_get_contents($file);
$backupMade = false;
$changed = 0;

function apply(&$content, $old, $new, &$backupMade, &$changed, $file) {
    if (strpos($content, $old) !== false) {
        if (!$backupMade) { copy($file, $file . '.bak_' . date('Ymd_His')); $backupMade = true; }
        $content = str_replace($old, $new, $content);
        $changed++;
        return true;
    }
    return false;
}

// ── 1. Hapus tombol export kecil dari header, kembalikan header jadi biasa ──
apply($content,
    '<div class="page-header">
        <h1 class="page-title">Laporan 📊</h1>
        <button class="export-icon-btn" @click="showExportMenu = true">📤</button>
      </div>',
    '<div class="page-header">
        <h1 class="page-title">Laporan 📊</h1>
      </div>

      <!-- Tombol Export melayang, gaya sama seperti CuanAI FAB, posisi di ATAS -->
      <button class="export-fab" @click="showExportMenu = true" aria-label="Export Laporan">
        <span class="fab-emoji">📤</span>
        <span class="fab-label">Export</span>
      </button>',
    $backupMade, $changed, $file
);

// ── 2. Hapus CSS export-icon-btn lama, tambah CSS export-fab ──
apply($content,
    '.export-icon-btn { width:38px; height:38px; border-radius:50%; background:var(--surface); border:none; cursor:pointer; font-size:16px; box-shadow:var(--shadow-sm); }',
    '.export-fab {
  position: fixed;
  top: 18px;
  right: 18px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px 10px 12px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: white;
  border: none;
  border-radius: 99px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,.25);
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  z-index: 90;
  transition: all .2s;
}
.export-fab:hover { transform: scale(1.05); }
.export-fab .fab-emoji { font-size: 16px; }
.export-fab .fab-label { font-size: 12px; }',
    $backupMade, $changed, $file
);

if ($changed > 0) {
    file_put_contents($file, $content);
    echo "OK ($changed patch diterapkan): $file\n";
} else {
    echo "⚠️  Pattern tidak ketemu — cek manual\n";
}
