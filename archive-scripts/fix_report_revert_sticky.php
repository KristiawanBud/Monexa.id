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

    // FIX 1: Hero balik jadi statis (bukan sticky/collapse)
    'class="sticky top-0 z-30 overflow-visible bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6]
               pl-5 pr-[18px] rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]
               transition-all duration-300 ease-out"
        :class="isScrolled ? \'pt-4 pb-4\' : \'pt-6 pb-14\'"' =>
    'class="relative overflow-visible bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6]
               pl-5 pr-[18px] pt-6 pb-14 rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]"',

    // FIX 2: Dropdown bulan balik selalu keliatan (bukan collapse)
    'class="relative inline-block z-10 overflow-hidden transition-all duration-300 ease-out"
          :class="isScrolled ? \'max-h-0 opacity-0 mt-0\' : \'max-h-12 opacity-100 mt-3\'"' =>
    'class="relative inline-block z-10 mt-3"',

    // FIX 3: Format Rupiah pakai spasi lagi
    "function formatRupiah(n) {\n  return 'Rp' + Number(n || 0).toLocaleString('id-ID')\n}" =>
    "function formatRupiah(n) {\n  return 'Rp ' + Number(n || 0).toLocaleString('id-ID')\n}",

    // FIX 4: Tambah "vs bulan lalu" balik ke indikator kenaikan/penurunan
    "{{ incomeChange >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeChange) }}%\n          </div>" =>
    "{{ incomeChange >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeChange) }}% vs bulan lalu\n          </div>",

    "{{ expenseChange >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseChange) }}%\n          </div>" =>
    "{{ expenseChange >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseChange) }}% vs bulan lalu\n          </div>",

    // FIX 5: Bersihin scroll listener yang udah nggak kepake
    "const isScrolled = ref(false)

function onScroll() {
  isScrolled.value = window.scrollY > 40
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
})
onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
})

" => "",

    "import { ref, computed, onMounted, onUnmounted } from 'vue'" =>
    "import { ref, computed } from 'vue'",
]);

echo "\n=== SELESAI ===\n";
