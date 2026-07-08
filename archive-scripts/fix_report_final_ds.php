<?php

function writeFile(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_exists($path)) copy($path, $path . '.bak_' . date('Ymd_His'));
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

writeFile('/var/www/monexa/resources/js/Pages/App/Report.vue', <<<'EOT'
<template>
  <AppLayout>
    <div class="report-tw bg-slate-50 min-h-screen pb-8">

      <!-- ═══════════ HERO — 240px fix, padding 24px ═══════════ -->
      <div class="relative overflow-visible bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6] px-6 pt-6 rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]" style="height:240px;">
        <div class="absolute -top-14 -right-8 w-52 h-52 rounded-full bg-white/10 pointer-events-none"></div>
        <div class="absolute top-16 right-24 w-16 h-16 rounded-full bg-white/10 pointer-events-none"></div>
        <svg class="absolute top-0 right-0 opacity-[0.08] pointer-events-none" width="130" height="130" viewBox="0 0 130 130" fill="none">
          <circle cx="65" cy="65" r="1" fill="none" stroke="white" stroke-width="46" stroke-dasharray="2 7"/>
        </svg>

        <div class="relative z-10 flex justify-between items-center mb-4">
          <h1 class="text-[24px] font-semibold text-white leading-none">📊 Laporan</h1>
          <div class="flex items-center gap-2">
            <button type="button" @click="showExportMenu = true"
              class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[13px] rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)]"
              style="padding:12px 18px;">
              <Download :size="14" /> Export
            </button>
            <button type="button" class="relative w-11 h-11 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center text-white">
              <Bell :size="16" />
              <span v-if="$page.props.unread_notifications > 0"
                class="absolute -top-1 -right-1 bg-rose-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center ring-2 ring-[#2F6BFF]">
                {{ $page.props.unread_notifications }}
              </span>
            </button>
          </div>
        </div>

        <div class="relative inline-block z-10">
          <button type="button" @click="showMonthMenu = !showMonthMenu"
            class="flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-[13px] font-semibold rounded-full"
            style="padding:12px 18px;">
            <Calendar :size="14" /> {{ periodLabel }} <ChevronDown :size="14" :class="['transition-transform', showMonthMenu && 'rotate-180']" />
          </button>
          <div v-if="showMonthMenu" class="absolute top-full left-0 mt-2 bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.12)] py-2 w-40 max-h-64 overflow-y-auto z-30">
            <button type="button" v-for="m in months" :key="m.value" @click="changePeriod(m.value)"
              :class="['block w-full text-left px-4 py-2.5 text-[13px]', period === m.value ? 'text-[#2F6BFF] font-semibold bg-[#EAF0FF]' : 'text-slate-600 hover:bg-slate-50']">
              {{ m.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════════ SUMMARY — floating, naik 40px ke Hero, turun 120px ke body ═══════════ -->
      <div class="px-5 relative z-20 grid grid-cols-2 gap-4" style="margin-top:-40px;">
        <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] flex flex-col justify-center" style="height:160px; padding:20px;">
          <div class="text-[13px] font-normal text-slate-400 text-left">↑ Total Pemasukan</div>
          <div class="text-[36px] font-bold text-slate-800 text-left leading-tight tracking-tight mt-1">{{ formatShort(totalIncome) }}</div>
          <div :class="['text-[13px] font-medium text-left mt-2', incomeChange >= 0 ? 'text-emerald-500' : 'text-rose-500']">
            {{ incomeChange >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeChange) }}% dibanding bulan lalu
          </div>
        </div>
        <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] flex flex-col justify-center" style="height:160px; padding:20px;">
          <div class="text-[13px] font-normal text-slate-400 text-left">↓ Total Pengeluaran</div>
          <div class="text-[36px] font-bold text-slate-800 text-left leading-tight tracking-tight mt-1">{{ formatShort(totalExpense) }}</div>
          <div :class="['text-[13px] font-medium text-left mt-2', expenseChange <= 0 ? 'text-emerald-500' : 'text-rose-500']">
            {{ expenseChange >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseChange) }}% dibanding bulan lalu
          </div>
        </div>
      </div>

      <!-- Body: turun 120px dari batas Hero, spacing antar-section 24px -->
      <div class="px-5 pt-6 space-y-6">

        <!-- ═══════════ CHART — fokus utama, 340px, filter di dalam ═══════════ -->
        <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)]" style="height:340px; padding:24px; display:flex; flex-direction:column;">
          <!-- Header -->
          <div class="flex justify-between items-center mb-3">
            <div class="text-[18px] font-semibold text-slate-800 text-left">Pemasukan vs Pengeluaran</div>
            <button type="button" class="flex items-center gap-1 bg-slate-50 text-slate-500 text-[13px] font-medium px-3 py-2 rounded-full flex-shrink-0">
              Bulanan <ChevronDown :size="12" />
            </button>
          </div>

          <!-- Filter -->
          <div class="relative bg-slate-100 rounded-full p-1 flex mb-3 flex-shrink-0">
            <div class="absolute top-1 bottom-1 rounded-full bg-[#2F6BFF] shadow-[0_2px_8px_rgba(47,107,255,0.35)] transition-all duration-300 ease-out"
              :style="segmentStyle"></div>
            <button type="button" v-for="opt in rangeOptions" :key="opt.value"
              @click="changeRange(opt.value)"
              :class="['relative z-10 flex-1 text-center text-[13px] font-medium py-2 rounded-full transition-colors', range === opt.value ? 'text-white' : 'text-slate-500']">
              {{ opt.label }}
            </button>
          </div>

          <!-- Chart 220px -->
          <div class="flex items-end gap-3 overflow-x-auto flex-1" style="height:220px;">
            <div v-for="item in barData" :key="item.period" class="flex flex-col items-center flex-shrink-0" style="min-width:32px;">
              <div class="flex items-end gap-1.5" style="height:170px;">
                <div class="w-3 rounded-t-md bg-emerald-500 transition-all" :style="`height:${barHeight(item.income)}px`" :title="formatRupiah(item.income)"></div>
                <div class="w-3 rounded-t-md bg-rose-500 transition-all" :style="`height:${barHeight(item.expense)}px`" :title="formatRupiah(item.expense)"></div>
              </div>
              <div :class="['text-[13px] mt-2 font-normal', item.active ? 'text-[#2F6BFF] font-semibold' : 'text-slate-400']">{{ item.period }}</div>
            </div>
          </div>

          <!-- Legend -->
          <div class="flex gap-5 justify-center pt-2 flex-shrink-0">
            <div class="flex items-center gap-1.5 text-[13px] text-slate-500 font-normal"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Pemasukan</div>
            <div class="flex items-center gap-1.5 text-[13px] text-slate-500 font-normal"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Pengeluaran</div>
          </div>
        </div>

        <!-- ═══════════ HEALTH (40%) + EMERGENCY (60%) ═══════════ -->
        <div class="grid grid-cols-5 gap-4">
          <div class="col-span-2 bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
            <div class="flex justify-between items-start mb-3">
              <div class="flex items-center gap-1.5 text-[15px] font-semibold text-slate-800 text-left">
                <Activity :size="15" class="text-[#2F6BFF] flex-shrink-0" /> <span>Health Score</span>
              </div>
            </div>
            <div class="text-left">
              <span class="text-[24px] font-semibold text-[#2F6BFF] leading-none">{{ healthScore }}</span>
              <span class="text-[13px] text-slate-400">/100</span>
            </div>
            <div :class="['text-[13px] font-medium mb-3 mt-1 flex items-center gap-1.5 text-left', healthColorText]">
              <AppIcon :slug="`health_tier_${healthStatus}`" class="w-3.5 h-3.5 inline-block flex-shrink-0">{{ defaultTierEmoji(healthStatus) }}</AppIcon>
              {{ healthLabel(healthStatus) }}
            </div>
            <div class="space-y-2">
              <div class="flex items-center gap-2 text-[13px]">
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" :style="`width:${Math.min(savingRatio,100)}%`"></div></div>
                <span class="text-right font-medium text-slate-500 flex-shrink-0">{{ savingRatio }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[13px]">
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-purple-500 rounded-full" :style="`width:${budgetDiscipline}%`"></div></div>
                <span class="text-right font-medium text-slate-500 flex-shrink-0">{{ budgetDiscipline }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[13px]">
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-[#2F6BFF] rounded-full" :style="`width:${runwayScore}%`"></div></div>
                <span class="text-right font-medium text-slate-500 flex-shrink-0">{{ runwayScore }}%</span>
              </div>
            </div>
          </div>

          <div class="col-span-3 bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.15)] p-5 relative overflow-hidden">
            <div class="flex items-center gap-1.5 text-[15px] font-semibold mb-3 relative z-10 text-left">
              <Shield :size="15" class="text-emerald-400 flex-shrink-0" /> <span>Dana Darurat</span>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="text-left relative z-10 mb-3">
                <span class="text-[24px] font-semibold leading-none">{{ runwayMonths }}</span>
                <span class="text-[13px] text-white/60"> Bulan / target 6</span>
              </div>
              <div class="h-2 bg-white/15 rounded-full overflow-hidden mb-2 relative z-10 max-w-[200px]">
                <div class="h-full bg-amber-400 rounded-full" :style="`width:${Math.min((runwayMonths/6)*100,100)}%`"></div>
              </div>
              <div class="text-[13px] text-white/45 relative z-10 text-left">Target ideal: 6 bulan pengeluaran</div>
            </template>
            <div v-else class="text-[13px] text-white/60 relative z-10 text-left">Belum cukup data.</div>
            <PiggyBank :size="72" class="absolute -bottom-3 -right-3 text-white/[0.08]" />
          </div>
        </div>

        <!-- ═══════════ DONUT — diameter 140px, legend vertical kanan ═══════════ -->
        <div v-if="donutData.length > 0" class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-6">
          <div class="text-[18px] font-semibold text-slate-800 mb-5 text-left">Kategori Pengeluaran</div>
          <div class="flex flex-row items-center gap-6">
            <div class="relative flex-shrink-0" style="width:140px;height:140px;">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <circle v-for="(seg, i) in donutSegments" :key="i" cx="50" cy="50" r="40" fill="transparent"
                  :stroke="donutColors[i % donutColors.length]" stroke-width="15" stroke-linecap="round"
                  :stroke-dasharray="`${seg.length} ${251.2 - seg.length}`" :stroke-dashoffset="-seg.offset" />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <div class="text-[13px] text-slate-400">Total</div>
                <div class="text-[15px] font-semibold">{{ formatShort(totalExpense) }}</div>
              </div>
            </div>
            <div class="flex-1 w-full flex flex-col gap-3">
              <div v-for="(cat, i) in donutData" :key="cat.category" class="flex items-center gap-2.5 text-[15px]">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                <span class="flex-1 text-slate-700 font-medium text-left truncate">{{ cat.emoji }} {{ cat.category }}</span>
                <span class="text-slate-400 text-[13px] flex-shrink-0">Rp {{ formatShort(cat.total) }}</span>
                <span class="font-semibold text-slate-800 text-[13px] w-11 text-right flex-shrink-0">{{ cat.percent }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══════════ BUDGET VS REALISASI ═══════════ -->
        <div v-if="budgets.length > 0" class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-6">
          <div class="text-[18px] font-semibold text-slate-800 mb-4 text-left">Budget vs Realisasi</div>
          <div v-for="b in budgets" :key="b.category" class="mb-4 last:mb-0">
            <div class="flex justify-between text-[15px] mb-2">
              <span class="font-medium text-slate-700 text-left">{{ b.emoji }} {{ b.category }}</span>
              <span class="text-slate-400 text-[13px]">Rp {{ formatShort(b.spent) }} / Rp {{ formatShort(b.budget) }}</span>
            </div>
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div :class="['h-full rounded-full', b.status === 'over' ? 'bg-rose-500' : b.status === 'warn' ? 'bg-amber-500' : 'bg-emerald-500']" :style="`width:${Math.min(b.percent,100)}%`"></div>
            </div>
          </div>
        </div>

        <!-- ═══════════ INSIGHT — grid 2x2, 88px, radius 20px, padding 18px ═══════════ -->
        <div>
          <div class="text-[18px] font-semibold text-slate-800 mb-4 text-left">Insight Bulan Ini</div>
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] flex items-center gap-3" style="height:88px; padding:18px;">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0"><TrendingUp :size="16" class="text-emerald-500" /></div>
              <div class="text-left min-w-0">
                <div class="text-[13px] text-slate-400 truncate">Pemasukan tertinggi</div>
                <div class="text-[15px] font-semibold truncate">{{ insight.biggest_income ? formatShort(insight.biggest_income.amount) : '-' }}</div>
              </div>
            </div>
            <div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] flex items-center gap-3" style="height:88px; padding:18px;">
              <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0"><TrendingDown :size="16" class="text-rose-500" /></div>
              <div class="text-left min-w-0">
                <div class="text-[13px] text-slate-400 truncate">Pengeluaran tertinggi</div>
                <div class="text-[15px] font-semibold truncate">{{ insight.biggest_expense ? formatShort(insight.biggest_expense.amount) : '-' }}</div>
              </div>
            </div>
            <div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] flex items-center gap-3" style="height:88px; padding:18px;">
              <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0"><Users :size="16" class="text-purple-500" /></div>
              <div class="text-left min-w-0">
                <div class="text-[13px] text-slate-400 truncate">Rata-rata harian</div>
                <div class="text-[15px] font-semibold truncate">Rp {{ formatShort(insight.daily_average) }}</div>
              </div>
            </div>
            <div class="bg-white rounded-[20px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] flex items-center gap-3" style="height:88px; padding:18px;">
              <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0"><CalendarDays :size="16" class="text-amber-500" /></div>
              <div class="text-left min-w-0">
                <div class="text-[13px] text-slate-400 truncate">Hari paling boros</div>
                <div class="text-[15px] font-semibold truncate">{{ insight.most_wasteful_day?.date ?? '-' }}</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Export Menu Bottom Sheet -->
    <Teleport to="body">
      <div v-if="showExportMenu" class="report-tw fixed inset-0 bg-slate-900/45 backdrop-blur-sm z-[500] flex items-end justify-center" @click.self="showExportMenu = false">
        <div class="bg-white rounded-t-[24px] w-full max-w-md p-6 pb-10">
          <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-6"></div>
          <div class="text-[18px] font-semibold mb-5 text-left">📤 Export Laporan {{ periodLabel }}</div>

          <a :href="route('report.export-pdf', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[18px] mb-3">
            <span class="text-2xl">📄</span>
            <div class="text-left">
              <div class="text-[15px] font-semibold text-slate-800">Export PDF</div>
              <div class="text-[13px] text-slate-500">Laporan rapi untuk dicetak atau dibagikan</div>
            </div>
          </a>

          <a :href="route('report.export-excel', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[18px] mb-3">
            <span class="text-2xl">📊</span>
            <div class="text-left">
              <div class="text-[15px] font-semibold text-slate-800">Export Excel</div>
              <div class="text-[13px] text-slate-500">Detail transaksi dalam format spreadsheet</div>
            </div>
          </a>

          <button type="button" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[18px] mb-3 disabled:opacity-60" @click="sendWhatsApp" :disabled="sendingWa">
            <span class="text-2xl">💬</span>
            <div class="text-left">
              <div class="text-[15px] font-semibold text-slate-800">{{ sendingWa ? 'Mengirim...' : 'Kirim ke WhatsApp' }}</div>
              <div class="text-[13px] text-slate-500">Ringkasan laporan dikirim ke nomor WA kamu</div>
            </div>
          </button>

          <button type="button" class="w-full py-3.5 rounded-[18px] text-[15px] font-medium text-slate-500 bg-slate-50" @click="showExportMenu = false">Batal</button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'
import axios from 'axios'
import {
  TrendingUp, TrendingDown, Download, Bell, ChevronDown, Calendar,
  Activity, Shield, PiggyBank, Users, CalendarDays,
} from 'lucide-vue-next'

const props = defineProps({
  period: String, periodLabel: String, range: String,
  barData: Array, donutData: Array, budgets: Array,
  totalIncome: Number, totalExpense: Number,
  incomeChange: Number, expenseChange: Number,
  savingRatio: Number, budgetDiscipline: Number,
  runwayMonths: Number, runwayScore: Number,
  healthScore: Number, healthStatus: String,
  insight: Object,
  months: Array,
})

const showExportMenu = ref(false)
const showMonthMenu  = ref(false)
const sendingWa       = ref(false)

const rangeOptions = [
  { value: '6months', label: '6 Bulan' },
  { value: '12months', label: '12 Bulan' },
  { value: 'year', label: 'Tahun Ini' },
  { value: 'all', label: 'Semua' },
]
const activeIndex = computed(() => rangeOptions.findIndex(o => o.value === props.range))
const segmentStyle = computed(() => ({
  width: `${100 / rangeOptions.length}%`,
  left: `${(100 / rangeOptions.length) * activeIndex.value}%`,
}))

const changeRange = (r) => router.get(route('report'), { period: props.period, range: r }, { preserveState: true, preserveScroll: true })
const changePeriod = (p) => { showMonthMenu.value = false; router.get(route('report'), { period: p, range: props.range }, { preserveState: true }) }

const sendWhatsApp = async () => {
  sendingWa.value = true
  try {
    await axios.post(route('report.send-whatsapp'), { period: props.period })
    showExportMenu.value = false
    router.reload({ only: ['flash'] })
  } catch {
  } finally {
    sendingWa.value = false
  }
}

const donutColors = ['#2F6BFF','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#84CC16']

const maxBarVal = computed(() => Math.max(...props.barData.flatMap(d => [d.income, d.expense]), 1))
const barHeight = (v) => Math.max(4, (v / maxBarVal.value) * 170)

const donutSegments = computed(() => {
  let offset = 0
  return props.donutData.map(d => {
    const length = (d.percent / 100) * 251.2
    const seg = { length, offset }
    offset += length
    return seg
  })
})

const healthColorText = computed(() => ({
  sehat: 'text-emerald-500', cukup: 'text-amber-500', perlu_perhatian: 'text-rose-500',
}[props.healthStatus] ?? 'text-slate-500'))

const healthLabel = (s) => ({ sehat: 'Sehat', cukup: 'Cukup', perlu_perhatian: 'Perlu Perhatian' }[s] ?? s)
const defaultTierEmoji = (s) => ({ sehat: '✅', cukup: '⚠️', perlu_perhatian: '🔴' }[s] ?? '')

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return 'Rp' + (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return 'Rp' + (n/1_000).toFixed(0) + 'rb'
  return 'Rp' + Math.round(n)
}
</script>

<style scoped>
.report-tw button,
.report-tw select,
.report-tw input,
.report-tw a {
  border: none;
  outline: none;
  background-color: transparent;
  font: inherit;
  color: inherit;
  -webkit-appearance: none;
  appearance: none;
  box-sizing: border-box;
}
.report-tw button { cursor: pointer; }
.report-tw a { text-decoration: none; }
</style>

EOT
);

echo "\n=== SELESAI ===\n";
