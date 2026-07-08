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

    // FIX 1: Judul chart - satu baris, clamp font, truncate kalau kepanjangan
    '<div class="text-[16px] font-semibold text-slate-800 text-left leading-tight">
              Pemasukan vs Pengeluaran
            </div>
            <button
              type="button"
              class="flex items-center gap-1 bg-slate-50 text-slate-500 text-[11px] font-medium
                     px-2.5 py-1.5 rounded-full flex-shrink-0"
            >
              Bulanan <ChevronDown :size="11" />
            </button>' =>
    '<div
              class="font-semibold text-slate-800 text-left leading-tight truncate min-w-0 flex-1"
              style="font-size:clamp(14px,4vw,16px);"
            >
              Pemasukan vs Pengeluaran
            </div>
            <button
              type="button"
              class="flex items-center gap-1 bg-slate-50 text-slate-500 font-medium
                     px-2.5 py-1.5 rounded-full flex-shrink-0 whitespace-nowrap"
              style="font-size:clamp(10px,2.5vw,11px); min-height:32px;"
            >
              Bulanan <ChevronDown :size="11" />
            </button>',

    // FIX 2: Filter segmented - nowrap + font clamp lebih kecil, biar nggak wrap 2 baris
    ":class=\"[
                'relative z-10 flex-1 text-center text-[11px] font-medium py-2 rounded-full transition-all active:scale-95',
                range === opt.value ? 'text-white' : 'text-slate-500'
              ]\"
            >
              {{ opt.label }}
            </button>" =>
    ":class=\"[
                'relative z-10 flex-1 text-center font-medium py-2.5 rounded-full transition-all',
                'active:scale-95 whitespace-nowrap overflow-hidden',
                range === opt.value ? 'text-white' : 'text-slate-500'
              ]\"
              style=\"font-size:clamp(9px,2.6vw,11px); min-height:36px;\"
            >
              {{ opt.label }}
            </button>",

    // FIX 3: Chart bars - jangan andelin overflow-scroll, muat semua bulan tanpa scroll,
    // tinggi chart isi minimal 65% area (dinaikin ke 150px dari 100px)
    '<div class="flex items-end gap-2.5 overflow-x-auto" style="height:130px;">' =>
    '<div class="flex items-end justify-between gap-1.5" style="height:170px;">',

    '<div
              v-for="item in barData"
              :key="item.period"
              class="flex flex-col items-center flex-shrink-0"
              style="min-width:28px;"
            >
              <div class="flex items-end gap-1" style="height:100px;">' =>
    '<div
              v-for="item in barData"
              :key="item.period"
              class="flex flex-col items-center flex-1 min-w-0"
            >
              <div class="flex items-end gap-1" style="height:140px;">',

    // FIX 4: Summary nominal pakai clamp (biar makin gede kalau layar lega, tapi nggak overflow di layar kecil)
    'class="text-[21px] font-bold text-slate-800 text-left leading-tight tracking-tight mt-1 truncate">
            {{ formatShort(totalIncome) }}' =>
    'class="font-bold text-slate-800 text-left leading-tight tracking-tight mt-1 truncate"
            style="font-size:clamp(20px,6vw,28px);"
          >
            {{ formatShort(totalIncome) }}',

    'class="text-[21px] font-bold text-slate-800 text-left leading-tight tracking-tight mt-1 truncate">
            {{ formatShort(totalExpense) }}' =>
    'class="font-bold text-slate-800 text-left leading-tight tracking-tight mt-1 truncate"
            style="font-size:clamp(20px,6vw,28px);"
          >
            {{ formatShort(totalExpense) }}',

    // FIX 5: Donut diameter pakai clamp
    'style="width:128px;height:128px;"' =>
    'style="width:clamp(120px,35vw,140px);height:clamp(120px,35vw,140px);"',

    // FIX 6: Tombol Export & Notif — pastiin target sentuh minimal 44x44px
    'class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[12.5px]
                     rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)] transition-transform
                     active:scale-95 px-3.5 py-2.5"' =>
    'class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[12.5px]
                     rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)] transition-transform
                     active:scale-95 px-3.5"
              style="min-height:44px;"',

    'class="relative w-10 h-10 rounded-full bg-white/15 backdrop-blur-sm flex items-center
                     justify-center text-white transition-transform active:scale-95"' =>
    'class="relative rounded-full bg-white/15 backdrop-blur-sm flex items-center
                     justify-center text-white transition-transform active:scale-95"
              style="width:44px;height:44px;"',
]);

echo "\n=== SELESAI ===\n";
