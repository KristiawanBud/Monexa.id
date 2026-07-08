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

    // ── FIX bug horizontal scroll: overflow-x-hidden di root wrapper ──
    '<div class="report-tw bg-slate-50 min-h-screen pb-8">' =>
    '<div class="report-tw bg-slate-50 min-h-screen pb-8 overflow-x-hidden" style="max-width:100vw;">',

    // ── 1. HERO: kurangi tinggi (pt-6 pb-14 -> pt-5 pb-10) ──
    'class="relative overflow-visible bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6]
               pl-5 pr-[18px] pt-6 pb-14 rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]"' =>
    'class="relative overflow-hidden bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6]
               pl-5 pr-[18px] pt-5 pb-9 rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]"',

    // ── Jarak dropdown ke Summary diperkecil ──
    'class="relative inline-block z-10 mt-3"' =>
    'class="relative inline-block z-10 mt-2.5"',

    // ── 2. SUMMARY: overlap lebih dalam (-28px -> -48px), gap diperlebar, padding & tinggi naik ──
    '<div class="px-5 relative z-20 grid grid-cols-2 gap-2.5" style="margin-top:-28px;">' =>
    '<div class="px-5 relative z-20 grid grid-cols-2 gap-3" style="margin-top:-48px;">',

    '<div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] p-3 min-w-0">
          <div class="flex items-center gap-1 mb-1">
            <div class="w-5 h-5 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
              <TrendingUp :size="10" class="text-emerald-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10px;">Pemasukan</span>
          </div>' =>
    '<div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] px-4 py-4 min-w-0">
          <div class="flex items-center gap-1.5 mb-1.5">
            <div class="w-6 h-6 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
              <TrendingUp :size="11" class="text-emerald-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10.5px;">Pemasukan</span>
          </div>',

    '<div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] p-3 min-w-0">
          <div class="flex items-center gap-1 mb-1">
            <div class="w-5 h-5 rounded-full bg-rose-50 flex items-center justify-center flex-shrink-0">
              <TrendingDown :size="10" class="text-rose-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10px;">Pengeluaran</span>
          </div>' =>
    '<div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] px-4 py-4 min-w-0">
          <div class="flex items-center gap-1.5 mb-1.5">
            <div class="w-6 h-6 rounded-full bg-rose-50 flex items-center justify-center flex-shrink-0">
              <TrendingDown :size="11" class="text-rose-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10.5px;">Pengeluaran</span>
          </div>',

    // ── 3. FILTER: tinggi ~48px, gap antar tab lebih lega ──
    'class="relative bg-white rounded-full p-1 flex shadow-[0_4px_14px_rgba(15,23,42,0.06)]">' =>
    'class="relative bg-white rounded-full p-1.5 flex gap-1 shadow-[0_4px_14px_rgba(15,23,42,0.06)]">',

    'style="font-size:11px; min-height:38px;"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- ═══ CHART ═══ -->' =>
    'style="font-size:11.5px; min-height:48px;"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- ═══ CHART ═══ -->',

    // ── 4. CHART: padding atas dikurangi, grafik lebih tinggi (150/120 -> 180/150 ~20%), legend lebih dekat ──
    'class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
          <div class="flex justify-between items-center mb-4 gap-2">
            <div class="font-semibold text-slate-800 text-left truncate min-w-0 flex-1" style="font-size:16px;">
              Pemasukan vs Pengeluaran' =>
    'class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] px-5 pt-4 pb-5">
          <div class="flex justify-between items-center mb-3 gap-2">
            <div class="font-semibold text-slate-800 text-left truncate min-w-0 flex-1" style="font-size:16px;">
              Pemasukan vs Pengeluaran',

    'style="height:150px; width:32px;"' => 'style="height:180px; width:32px;"',
    'style="height:150px;">
              <div v-for="item in barData"' =>
    'style="height:180px;">
              <div v-for="item in barData"',
    'style="height:120px;">
                  <div
                    class="w-2.5 rounded-t-md bg-emerald-500' =>
    'style="height:150px;">
                  <div
                    class="w-2.5 rounded-t-md bg-emerald-500',

    'function barHeight(v) { return Math.max(3, (v / maxBarVal.value) * 120) }' =>
    'function barHeight(v) { return Math.max(3, (v / maxBarVal.value) * 150) }',

    'class="flex gap-4 justify-center pt-3 mt-1 border-t border-slate-100">
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Pemasukan
            </div>
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span> Pengeluaran
            </div>
          </div>
        </div>

        <!-- ═══ HEALTH' =>
    'class="flex gap-4 justify-center pt-2 border-t border-slate-100">
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Pemasukan
            </div>
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span> Pengeluaran
            </div>
          </div>
        </div>

        <!-- ═══ HEALTH',

    // ── 5. HEALTH SCORE: angka+badge naik lebih dekat ke judul, jarak sebelum progress dikurangi ──
    '<span class="font-semibold text-slate-800 text-left" style="font-size:16px;">Budget Health Score</span>
            </div>
            <div class="text-left">
              <span class="font-bold text-[#2F6BFF]" style="font-size:24px; line-height:1.1;">{{ healthScore }}</span>
              <span class="text-slate-400" style="font-size:12px;">/100</span>
            </div>
            <div
              :class="[\'font-medium mb-4 mt-1 flex items-center gap-1.5 text-left\', healthColorText]"' =>
    '<span class="font-semibold text-slate-800 text-left" style="font-size:16px;">Budget Health Score</span>
            </div>
            <div class="text-left mt-0.5">
              <span class="font-bold text-[#2F6BFF]" style="font-size:24px; line-height:1.1;">{{ healthScore }}</span>
              <span class="text-slate-400" style="font-size:12px;">/100</span>
            </div>
            <div
              :class="[\'font-medium mb-3 mt-0.5 flex items-center gap-1.5 text-left\', healthColorText]"',

    // ── 6. DANA DARURAT: kurangi padding vertikal, progress bar lebih panjang (sudah full width bawaan) ──
    'class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.15)] p-5 relative overflow-hidden">
            <div class="flex items-center gap-2 mb-3 relative z-10">
              <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="15" class="text-emerald-400" />
              </div>
              <span class="font-semibold text-left" style="font-size:16px;">Dana Darurat</span>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="text-left relative z-10 mb-3">' =>
    'class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.15)] px-5 py-4 relative overflow-hidden">
            <div class="flex items-center gap-2 mb-2.5 relative z-10">
              <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="15" class="text-emerald-400" />
              </div>
              <span class="font-semibold text-left" style="font-size:16px;">Dana Darurat</span>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="text-left relative z-10 mb-2.5">',

    // ── 7. KATEGORI PENGELUARAN: tambah progress bar horizontal per item ──
    '<div v-for="(cat, i) in donutData" :key="cat.category" class="flex items-center gap-2" style="font-size:13px;">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                <span class="flex-1 text-slate-700 font-medium text-left min-w-0 truncate">{{ cat.emoji }} {{ cat.category }}</span>
                <div class="text-right flex-shrink-0">
                  <div class="font-semibold text-slate-800" style="font-size:12px;">{{ cat.percent }}%</div>
                  <div class="text-slate-400" style="font-size:10px;">{{ formatRupiah(cat.total) }}</div>
                </div>
              </div>' =>
    '<div v-for="(cat, i) in donutData" :key="cat.category">
                <div class="flex items-center gap-2 mb-1.5" style="font-size:13px;">
                  <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                  <span class="flex-1 text-slate-700 font-medium text-left min-w-0 truncate">{{ cat.emoji }} {{ cat.category }}</span>
                  <div class="text-right flex-shrink-0">
                    <div class="font-semibold text-slate-800" style="font-size:12px;">{{ cat.percent }}%</div>
                    <div class="text-slate-400" style="font-size:10px;">{{ formatRupiah(cat.total) }}</div>
                  </div>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden ml-4.5">
                  <div
                    class="h-full rounded-full transition-all"
                    :style="`width:${cat.percent}%; background:${donutColors[i % donutColors.length]}`"
                  ></div>
                </div>
              </div>',
]);

echo "\n=== SELESAI ===\n";
