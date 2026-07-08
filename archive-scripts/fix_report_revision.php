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
    else { echo "SKIP (pattern tidak ketemu): $path\n"; }
}

$file = '/var/www/monexa/resources/js/Pages/App/Report.vue';

patchFile($file, [
    // ── FIX 1: Chart card overflow bug — height fixed jadi min-height ──
    'style="height:340px; padding:24px; display:flex; flex-direction:column;"' =>
    'style="min-height:340px; padding:24px; display:flex; flex-direction:column;"',

    // ── FIX 2: Hapus flex-1 konflik di chart bars container ──
    'class="flex items-end gap-3 overflow-x-auto flex-1" style="height:220px;"' =>
    'class="flex items-end gap-3 overflow-x-auto" style="height:220px;"',

    // ── FIX 3: Palet warna donut lebih harmonis (turunan biru + aksen terkontrol) ──
    "const donutColors = ['#2F6BFF','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#84CC16']" =>
    "const donutColors = ['#2F6BFF','#60A5FA','#818CF8','#34D399','#FBBF24','#F87171','#A78BFA','#38BDF8']",

    // ── FIX 4: Micro-interaction — active:scale-95 di tombol Export ──
    'class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[13px] rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)]"
              style="padding:12px 18px;">
              <Download :size="14" /> Export' =>
    'class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[13px] rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)] transition-transform active:scale-95"
              style="padding:12px 18px;">
              <Download :size="14" /> Export',

    // ── FIX 5: Micro-interaction — tombol Notif & Dropdown Bulan ──
    'class="relative w-11 h-11 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center text-white">' =>
    'class="relative w-11 h-11 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center text-white transition-transform active:scale-95">',

    'class="flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-[13px] font-semibold rounded-full"
            style="padding:12px 18px;">' =>
    'class="flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-[13px] font-semibold rounded-full transition-transform active:scale-95"
            style="padding:12px 18px;">',

    // ── FIX 6: Micro-interaction — filter segmented pills ──
    ":class=\"['relative z-10 flex-1 text-center text-[13px] font-medium py-2 rounded-full transition-colors', range === opt.value ? 'text-white' : 'text-slate-500']\">" =>
    ":class=\"['relative z-10 flex-1 text-center text-[13px] font-medium py-2 rounded-full transition-all active:scale-95', range === opt.value ? 'text-white' : 'text-slate-500']\">",

    // ── FIX 7: Health Score card — tambah badge icon berwarna (matching treatment Emergency) ──
    '<div class="flex justify-between items-start mb-3">
              <div class="flex items-center gap-1.5 text-[15px] font-semibold text-slate-800 text-left">
                <Activity :size="15" class="text-[#2F6BFF] flex-shrink-0" /> <span>Health Score</span>
              </div>
            </div>' =>
    '<div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-[#EAF0FF] flex items-center justify-center flex-shrink-0">
                <Activity :size="15" class="text-[#2F6BFF]" />
              </div>
              <span class="text-[15px] font-semibold text-slate-800 text-left">Health Score</span>
            </div>',

    // ── FIX 8: Dana Darurat icon jadi badge juga (konsisten sama Health Score) ──
    '<div class="flex items-center gap-1.5 text-[15px] font-semibold mb-3 relative z-10 text-left">
              <Shield :size="15" class="text-emerald-400 flex-shrink-0" /> <span>Dana Darurat</span>
            </div>' =>
    '<div class="flex items-center gap-2 mb-3 relative z-10">
              <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="15" class="text-emerald-400" />
              </div>
              <span class="text-[15px] font-semibold text-left">Dana Darurat</span>
            </div>',
]);

echo "\n=== SELESAI ===\n";
