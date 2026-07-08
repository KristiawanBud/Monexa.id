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

      <!-- ═══════════ HERO (pendek, cuma header + dropdown) ═══════════ -->
      <div class="relative overflow-hidden bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6] px-5 pt-6 pb-16 rounded-b-[32px]">
        <div class="absolute -top-16 -right-10 w-56 h-56 rounded-full bg-white/10"></div>
        <div class="absolute top-14 right-20 w-20 h-20 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-10 left-10 w-32 h-32 rounded-full bg-white/5"></div>
        <svg class="absolute top-2 right-4 opacity-[0.08]" width="140" height="140" viewBox="0 0 140 140" fill="none">
          <circle cx="70" cy="70" r="1" fill="none" stroke="white" stroke-width="50" stroke-dasharray="2 7"/>
        </svg>

        <div class="relative z-10 flex justify-between items-center mb-4">
          <h1 class="text-[22px] font-bold text-white flex items-center gap-2">📊 Laporan</h1>
          <div class="flex items-center gap-2">
            <button type="button" @click="showExportMenu = true"
              class="h-10 flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold text-[12.5px] px-3.5 rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)]">
              <Download :size="14" /> Export
            </button>
            <button type="button" class="relative h-10 w-10 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center text-white">
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
            class="h-10 flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-[12.5px] font-semibold px-3.5 rounded-full">
            <Calendar :size="14" /> {{ periodLabel }} <ChevronDown :size="14" :class="['transition-transform', showMonthMenu && 'rotate-180']" />
          </button>
          <div v-if="showMonthMenu" class="absolute top-full left-0 mt-2 bg-white rounded-2xl shadow-[0_8px_30px_rgba(0,0,0,0.12)] py-2 w-40 max-h-64 overflow-y-auto z-20">
            <button type="button" v-for="m in months" :key="m.value" @click="changePeriod(m.value)"
              :class="['block w-full text-left px-4 py-2.5 text-[13px]', period === m.value ? 'text-[#2F6BFF] font-bold bg-[#EAF0FF]' : 'text-slate-600 hover:bg-slate-50']">
              {{ m.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════════ SUMMARY — "nembus" keluar dari Hero, 1 kesatuan visual ═══════════ -->
      <div class="px-5 -mt-11 relative z-20 grid grid-cols-2 gap-3 mb-7">
        <div class="bg-white rounded-[22px] p-4 shadow-[0_10px_30px_rgba(15,23,42,0.10)]">
          <div class="flex items-center gap-1.5 mb-1.5">
            <div class="w-6 h-6 rounded-full bg-emerald-50 flex items-center justify-center"><TrendingUp :size="13" class="text-emerald-500" /></div>
            <span class="text-[11px] text-slate-400 font-medium">Pemasukan</span>
          </div>
          <div class="text-[19px] font-extrabold text-slate-800 leading-tight tracking-tight">{{ formatRupiah(totalIncome) }}</div>
          <div :class="['text-[11px] font-bold mt-1.5 flex items-center gap-1', incomeChange >= 0 ? 'text-emerald-500' : 'text-rose-500']">
            <component :is="incomeChange >= 0 ? ArrowUp : ArrowDown" :size="10" /> {{ Math.abs(incomeChange) }}% vs bulan lalu
          </div>
        </div>
        <div class="bg-white rounded-[22px] p-4 shadow-[0_10px_30px_rgba(15,23,42,0.10)]">
          <div class="flex items-center gap-1.5 mb-1.5">
            <div class="w-6 h-6 rounded-full bg-rose-50 flex items-center justify-center"><TrendingDown :size="13" class="text-rose-500" /></div>
            <span class="text-[11px] text-slate-400 font-medium">Pengeluaran</span>
          </div>
          <div class="text-[19px] font-extrabold text-slate-800 leading-tight tracking-tight">{{ formatRupiah(totalExpense) }}</div>
          <div :class="['text-[11px] font-bold mt-1.5 flex items-center gap-1', expenseChange <= 0 ? 'text-emerald-500' : 'text-rose-500']">
            <component :is="expenseChange >= 0 ? ArrowUp : ArrowDown" :size="10" /> {{ Math.abs(expenseChange) }}% vs bulan lalu
          </div>
        </div>
      </div>

      <div class="px-5">

        <!-- ═══════════ CHART — filter jadi BAGIAN dari card ini ═══════════ -->
        <div class="bg-white rounded-[24px] p-5 shadow-[0_4px_20px_rgba(15,23,42,0.06)] mb-6">
          <div class="flex justify-between items-center mb-4">
            <div class="text-[16px] font-bold text-slate-800">Pemasukan vs Pengeluaran</div>
            <button type="button" class="flex items-center gap-1 bg-slate-50 text-slate-500 text-[11px] font-semibold px-2.5 py-1.5 rounded-full">
              Bulanan <ChevronDown :size="12" />
            </button>
          </div>

          <!-- Filter menyatu di dalam card -->
          <div class="relative bg-slate-100 rounded-full p-1 flex mb-5">
            <div class="absolute top-1 bottom-1 rounded-full bg-[#2F6BFF] shadow-[0_2px_8px_rgba(47,107,255,0.35)] transition-all duration-300 ease-out"
              :style="segmentStyle"></div>
            <button type="button" v-for="opt in rangeOptions" :key="opt.value"
              @click="changeRange(opt.value)"
              :class="['relative z-10 flex-1 text-center text-[11.5px] font-bold py-2 rounded-full transition-colors', range === opt.value ? 'text-white' : 'text-slate-500']">
              {{ opt.label }}
            </button>
          </div>

          <div class="flex items-end gap-3 overflow-x-auto pb-1" style="height:210px;">
            <div v-for="item in barData" :key="item.period" class="flex flex-col items-center flex-shrink-0" style="min-width:34px;">
              <div class="flex items-end gap-1.5" style="height:180px;">
                <div class="w-3.5 rounded-t-md bg-emerald-500 transition-all" :style="`height:${barHeight(item.income)}px`" :title="formatRupiah(item.income)"></div>
                <div class="w-3.5 rounded-t-md bg-rose-500 transition-all" :style="`height:${barHeight(item.expense)}px`" :title="formatRupiah(item.expense)"></div>
              </div>
              <div :class="['text-[11px] mt-2 font-medium', item.active ? 'text-[#2F6BFF] font-bold' : 'text-slate-400']">{{ item.period }}</div>
            </div>
          </div>

          <div class="flex gap-5 justify-center mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Pemasukan</div>
            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Pengeluaran</div>
          </div>
        </div>

        <!-- ═══════════ HEALTH + EMERGENCY ═══════════ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
          <div class="bg-white rounded-[24px] p-5 shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
            <div class="flex justify-between items-start mb-4">
              <div class="flex items-center gap-2 text-[14px] font-bold text-slate-800">
                <Activity :size="16" class="text-[#2F6BFF]" /> Budget Health Score
              </div>
              <button type="button" class="w-7 h-7 rounded-full bg-slate-50 flex items-center justify-center">
                <ArrowRight :size="14" class="text-slate-400" />
              </button>
            </div>
            <div class="flex items-baseline gap-1 mb-1.5">
              <span class="text-[26px] font-extrabold text-[#2F6BFF] leading-none tracking-tight">{{ healthScore }}</span>
              <span class="text-[12px] text-slate-400">/100</span>
            </div>
            <div :class="['text-[11.5px] font-bold mb-4 flex items-center gap-1.5', healthColorText]">
              <AppIcon :slug="`health_tier_${healthStatus}`" class="w-3.5 h-3.5 inline-block">{{ defaultTierEmoji(healthStatus) }}</AppIcon>
              {{ healthLabel(healthStatus) }}
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-[88px] text-slate-500 flex-shrink-0">Rasio Tabungan</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" :style="`width:${Math.min(savingRatio,100)}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ savingRatio }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-[88px] text-slate-500 flex-shrink-0">Disiplin</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-purple-500 rounded-full" :style="`width:${budgetDiscipline}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ budgetDiscipline }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-[88px] text-slate-500 flex-shrink-0">Runway</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-[#2F6BFF] rounded-full" :style="`width:${runwayScore}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ runwayScore }}%</span>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px] p-5 shadow-[0_4px_20px_rgba(15,23,42,0.15)] relative overflow-hidden">
            <div class="flex justify-between items-start mb-4 relative z-10">
              <div class="flex items-center gap-2 text-[14px] font-bold">
                <Shield :size="16" class="text-emerald-400" /> Dana Darurat
              </div>
              <button type="button" class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <ArrowRight :size="14" class="text-white/60" />
              </button>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="flex items-baseline gap-1.5 mb-4 relative z-10">
                <span class="text-[26px] font-extrabold leading-none tracking-tight">{{ runwayMonths }}</span>
                <span class="text-[11.5px] text-white/60">Bulan / target 6</span>
              </div>
              <div class="h-2 bg-white/15 rounded-full overflow-hidden mb-2.5 relative z-10">
                <div class="h-full bg-amber-400 rounded-full" :style="`width:${Math.min((runwayMonths/6)*100,100)}%`"></div>
              </div>
              <div class="text-[10.5px] text-white/45 relative z-10">Target ideal: 6 bulan pengeluaran</div>
            </template>
            <div v-else class="text-[12px] text-white/60 relative z-10">Belum cukup data.</div>
            <PiggyBank :size="76" class="absolute -bottom-4 -right-4 text-white/[0.08]" />
          </div>
        </div>

        <!-- ═══════════ DONUT ═══════════ -->
        <div v-if="donutData.length > 0" class="bg-white rounded-[24px] p-5 shadow-[0_4px_20px_rgba(15,23,42,0.06)] mb-6">
          <div class="text-[16px] font-bold text-slate-800 mb-5">Kategori Pengeluaran</div>
          <div class="flex flex-col sm:flex-row items-center gap-7">
            <div class="relative w-40 h-40 flex-shrink-0">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <circle v-for="(seg, i) in donutSegments" :key="i" cx="50" cy="50" r="40" fill="transparent"
                  :stroke="donutColors[i % donutColors.length]" stroke-width="15" stroke-linecap="round"
                  :stroke-dasharray="`${seg.length} ${251.2 - seg.length}`" :stroke-dashoffset="-seg.offset" />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-[10.5px] text-slate-400 font-medium">Total</div>
                <div class="text-[17px] font-bold">{{ formatShort(totalExpense) }}</div>
              </div>
            </div>
            <div class="flex-1 w-full space-y-3">
              <div v-for="(cat, i) in donutData" :key="cat.category" class="flex items-center gap-2.5 text-[13px]">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                <span class="flex-1 text-slate-700 font-medium">{{ cat.emoji }} {{ cat.category }}</span>
                <span class="text-slate-400">Rp {{ formatShort(cat.total) }}</span>
                <span class="font-bold text-slate-800 w-11 text-right">{{ cat.percent }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══════════ BUDGET VS REALISASI ═══════════ -->
        <div v-if="budgets.length > 0" class="bg-white rounded-[24px] p-5 shadow-[0_4px_20px_rgba(15,23,42,0.06)] mb-6">
          <div class="text-[16px] font-bold text-slate-800 mb-4">Budget vs Realisasi</div>
          <div v-for="b in budgets" :key="b.category" class="mb-4 last:mb-0">
            <div class="flex justify-between text-[13px] mb-2">
              <span class="font-semibold text-slate-700">{{ b.emoji }} {{ b.category }}</span>
              <span class="text-slate-400">Rp {{ formatShort(b.spent) }} / Rp {{ formatShort(b.budget) }}</span>
            </div>
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div :class="['h-full rounded-full', b.status === 'over' ? 'bg-rose-500' : b.status === 'warn' ? 'bg-amber-500' : 'bg-emerald-500']" :style="`width:${Math.min(b.percent,100)}%`"></div>
            </div>
          </div>
        </div>

        <!-- ═══════════ INSIGHT ═══════════ -->
        <div class="mb-2">
          <div class="text-[16px] font-bold text-slate-800 mb-4">Insight Bulan Ini</div>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-2xl p-4 shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
              <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center mb-2"><TrendingUp :size="15" class="text-emerald-500" /></div>
              <div class="text-[10.5px] text-slate-400 font-medium">Pemasukan tertinggi</div>
              <div class="text-[14px] font-extrabold mt-1">{{ insight.biggest_income ? formatShort(insight.biggest_income.amount) : '-' }}</div>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
              <div class="w-8 h-8 rounded-xl bg-rose-50 flex items-center justify-center mb-2"><TrendingDown :size="15" class="text-rose-500" /></div>
              <div class="text-[10.5px] text-slate-400 font-medium">Pengeluaran tertinggi</div>
              <div class="text-[14px] font-extrabold mt-1">{{ insight.biggest_expense ? formatShort(insight.biggest_expense.amount) : '-' }}</div>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
              <div class="w-8 h-8 rounded-xl bg-purple-50 flex items-center justify-center mb-2"><Users :size="15" class="text-purple-500" /></div>
              <div class="text-[10.5px] text-slate-400 font-medium">Rata-rata harian</div>
              <div class="text-[14px] font-extrabold mt-1">Rp {{ formatShort(insight.daily_average) }}</div>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
              <div class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center mb-2"><CalendarDays :size="15" class="text-amber-500" /></div>
              <div class="text-[10.5px] text-slate-400 font-medium">Hari paling boros</div>
              <div class="text-[14px] font-extrabold mt-1">{{ insight.most_wasteful_day?.date ?? '-' }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Export Menu Bottom Sheet -->
    <Teleport to="body">
      <div v-if="showExportMenu" class="report-tw fixed inset-0 bg-slate-900/45 backdrop-blur-sm z-[500] flex items-end justify-center" @click.self="showExportMenu = false">
        <div class="bg-white rounded-t-[28px] w-full max-w-md p-6 pb-10">
          <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-6"></div>
          <div class="text-[17px] font-bold mb-5">📤 Export Laporan {{ periodLabel }}</div>

          <a :href="route('report.export-pdf', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-2xl mb-3 no-underline">
            <span class="text-2xl">📄</span>
            <div>
              <div class="text-[14px] font-bold text-slate-800">Export PDF</div>
              <div class="text-[12px] text-slate-500">Laporan rapi untuk dicetak atau dibagikan</div>
            </div>
          </a>

          <a :href="route('report.export-excel', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-2xl mb-3 no-underline">
            <span class="text-2xl">📊</span>
            <div>
              <div class="text-[14px] font-bold text-slate-800">Export Excel</div>
              <div class="text-[12px] text-slate-500">Detail transaksi dalam format spreadsheet</div>
            </div>
          </a>

          <button type="button" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-2xl mb-3 disabled:opacity-60" @click="sendWhatsApp" :disabled="sendingWa">
            <span class="text-2xl">💬</span>
            <div class="text-left">
              <div class="text-[14px] font-bold text-slate-800">{{ sendingWa ? 'Mengirim...' : 'Kirim ke WhatsApp' }}</div>
              <div class="text-[12px] text-slate-500">Ringkasan laporan dikirim ke nomor WA kamu</div>
            </div>
          </button>

          <button type="button" class="w-full py-3.5 rounded-2xl text-[14px] font-semibold text-slate-500 bg-slate-50" @click="showExportMenu = false">Batal</button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed, markRaw } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'
import axios from 'axios'
import {
  TrendingUp, TrendingDown, Download, Bell, ChevronDown, Calendar,
  Activity, Shield, PiggyBank, ArrowRight, Users, CalendarDays,
  ArrowUp, ArrowDown,
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
const barHeight = (v) => Math.max(4, (v / maxBarVal.value) * 180)

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
  if (n >= 1_000_000) return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return (n/1_000).toFixed(0) + 'rb'
  return String(Math.round(n))
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
