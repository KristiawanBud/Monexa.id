<?php

$file = '/var/www/monexa/resources/js/Pages/App/Dashboard.vue';

if (!file_exists($file)) {
    echo "SKIP: file tidak ditemukan\n";
    exit;
}

$content = file_get_contents($file);
$backupMade = false;
$changed = 0;

// ── 1. Ganti tombol gembok jadi SVG mata ──
$oldBtn = '<button class="hide-btn" @click="toggleBalance">{{ balanceHidden ? \'🔒\' : \'🔓\' }}</button>';

$newBtn = <<<'HTML'
<button class="hide-btn" @click="toggleBalance" :aria-label="balanceHidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'">
          <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
          </svg>
        </button>
HTML;

if (strpos($content, $oldBtn) !== false) {
    if (!$backupMade) { copy($file, $file . '.bak_' . date('Ymd_His')); $backupMade = true; }
    $content = str_replace($oldBtn, $newBtn, $content);
    $changed++;
} else {
    echo "⚠️  Template tombol gembok tidak ketemu persis — cek manual\n";
}

// ── 2. Update CSS .hide-btn: tambah warna putih + ukuran SVG ──
$oldCss = '.hide-btn { background:rgba(255,255,255,.15); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; }';

$newCss = '.hide-btn { background:rgba(255,255,255,.15); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; color:white; }
.hide-btn svg { width:16px; height:16px; }';

if (strpos($content, $oldCss) !== false) {
    if (!$backupMade) { copy($file, $file . '.bak_' . date('Ymd_His')); $backupMade = true; }
    $content = str_replace($oldCss, $newCss, $content);
    $changed++;
} else {
    echo "⚠️  CSS .hide-btn tidak ketemu persis — cek manual\n";
}

if ($changed > 0) {
    file_put_contents($file, $content);
    echo "OK ($changed patch diterapkan): $file\n";
} else {
    echo "Tidak ada yang berubah.\n";
}
