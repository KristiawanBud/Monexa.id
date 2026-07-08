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

    // FIX 1: Summary — grid-cols-2 jadi grid-cols-1 (full width), font naik lagi karena udah lega
    '<div class="px-5 relative z-20 grid grid-cols-2 gap-3" style="margin-top:-36px;">' =>
    '<div class="px-5 relative z-20 grid grid-cols-1 gap-3" style="margin-top:-36px;">',

    'style="font-size:clamp(14px,4.4vw,19px); line-height:1.25;"
          >
            {{ formatRupiah(totalIncome) }}' =>
    'style="font-size:clamp(20px,6.5vw,26px); line-height:1.2;"
          >
            {{ formatRupiah(totalIncome) }}',

    'style="font-size:clamp(14px,4.4vw,19px); line-height:1.25;"
          >
            {{ formatRupiah(totalExpense) }}' =>
    'style="font-size:clamp(20px,6.5vw,26px); line-height:1.2;"
          >
            {{ formatRupiah(totalExpense) }}',

    // FIX 2: Health + Emergency — grid-cols-2 jadi grid-cols-1 (stack, full width)
    '<div class="grid grid-cols-2 gap-3">
          <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-4 min-w-0">
            <div class="flex items-center gap-1.5 mb-2.5">
              <div class="w-7 h-7 rounded-lg bg-[#EAF0FF] flex items-center justify-center flex-shrink-0">
                <Activity :size="13" class="text-[#2F6BFF]" />
              </div>
              <span class="font-semibold text-slate-800 text-left truncate" style="font-size:clamp(11px,3vw,13px);">
                Health Score
              </span>
            </div>' =>
    '<div class="grid grid-cols-1 gap-4">
          <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-[#EAF0FF] flex items-center justify-center flex-shrink-0">
                <Activity :size="15" class="text-[#2F6BFF]" />
              </div>
              <span class="font-semibold text-slate-800 text-left" style="font-size:16px;">
                Budget Health Score
              </span>
            </div>',

    'class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px]
                   shadow-[0_8px_24px_rgba(15,23,42,0.15)] p-4 relative overflow-hidden min-w-0"
          >
            <div class="flex items-center gap-1.5 mb-2.5 relative z-10">
              <div class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="13" class="text-emerald-400" />
              </div>
              <span class="font-semibold text-left truncate" style="font-size:clamp(11px,3vw,13px);">
                Dana Darurat
              </span>
            </div>' =>
    'class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px]
                   shadow-[0_8px_24px_rgba(15,23,42,0.15)] p-5 relative overflow-hidden"
          >
            <div class="flex items-center gap-2 mb-3 relative z-10">
              <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="15" class="text-emerald-400" />
              </div>
              <span class="font-semibold text-left" style="font-size:16px;">
                Dana Darurat
              </span>
            </div>',

    // FIX 3: angka Health Score & Dana Darurat balik lebih gede karena udah full-width
    'style="font-size:clamp(18px,5.5vw,22px); line-height:1.1;">
              {{ healthScore }}' =>
    'style="font-size:24px; line-height:1.1;">
              {{ healthScore }}',

    'style="font-size:clamp(18px,5.5vw,22px); line-height:1.1;">
                  {{ runwayMonths }}' =>
    'style="font-size:clamp(26px,7vw,32px); font-weight:700; line-height:1.1;">
                  {{ runwayMonths }}',

    '<span class="text-white/60" style="font-size:10.5px;"> Bulan</span>' =>
    '<span class="text-white/60" style="font-size:12px;"> Bulan dari target 6 bulan</span>',

    '<span class="text-right font-semibold flex-shrink-0 w-7">{{ savingRatio }}%</span>' =>
    '<span class="text-right font-semibold flex-shrink-0 w-9">{{ savingRatio }}%</span>',
    '<span class="text-right font-semibold flex-shrink-0 w-7">{{ budgetDiscipline }}%</span>' =>
    '<span class="text-right font-semibold flex-shrink-0 w-9">{{ budgetDiscipline }}%</span>',
    '<span class="text-right font-semibold flex-shrink-0 w-7">{{ runwayScore }}%</span>' =>
    '<span class="text-right font-semibold flex-shrink-0 w-9">{{ runwayScore }}%</span>',
]);

echo "\n=== SELESAI ===\n";
