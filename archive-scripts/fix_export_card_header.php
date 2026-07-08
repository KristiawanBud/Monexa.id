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

// ── 1. Ganti header jadi 1 card berisi judul + tombol Export sejajar ──
apply($content,
    '<div class="page-header">
        <h1 class="page-title">Laporan 📊</h1>
      </div>

      <!-- Tombol Export melayang, gaya sama seperti CuanAI FAB, posisi di ATAS -->
      <button class="export-fab" @click="showExportMenu = true" aria-label="Export Laporan">
        <span class="fab-emoji">📤</span>
        <span class="fab-label">Export</span>
      </button>',
    '<div class="page-header-card">
        <h1 class="page-title">Laporan 📊</h1>
        <button class="export-btn-inline" @click="showExportMenu = true">
          <span class="fab-emoji">📤</span>
          <span class="fab-label">Export</span>
        </button>
      </div>',
    $backupMade, $changed, $file
);

// ── 2. Ganti CSS: hapus export-fab (fixed), tambah page-header-card + export-btn-inline ──
apply($content,
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
    '.page-header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  margin-bottom: 12px;
  box-shadow: var(--shadow-card);
}
.export-btn-inline {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px 9px 12px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: white;
  border: none;
  border-radius: 99px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  transition: all .2s;
  flex-shrink: 0;
}
.export-btn-inline:hover { transform: scale(1.05); }
.export-btn-inline .fab-emoji { font-size: 15px; }
.export-btn-inline .fab-label { font-size: 12px; }',
    $backupMade, $changed, $file
);

if ($changed > 0) {
    file_put_contents($file, $content);
    echo "OK ($changed patch diterapkan): $file\n";
} else {
    echo "⚠️  Pattern tidak ketemu — cek manual\n";
}
