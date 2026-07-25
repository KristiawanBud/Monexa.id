<?php

function writeFile(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($path)) {
        copy($path, $path.'.bak_'.date('Ymd_His'));
    }
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

writeFile('/var/www/monexa/resources/js/Pages/App/Report.vue', <<<'EOT'
<template>
  <AppLayout>
    <div class="report-tw bg-slate-50 min-h-screen pb-8">

      <!-- ═══ HERO — sticky, collapse pas discroll ═══ -->
      <div
        class="sticky top-0 z-30 overflow-visible bg-gradient-to-br from-[#2F6BFF] via-[#4F7FFF] to-[#1E4FD6]
               pl-5 pr-[18px] rounded-b-[24px] shadow-[0_12px_40px_rgba(47,107,255,0.20)]
               transition-all duration-300 ease-out"
        :class="isScrolled ? 'pt-4 pb-4' : 'pt-6 pb-14'"
      >
        <div class="absolute -top-14 -right-8 w-52 h-52 rounded-full bg-white/10 pointer-events-none"></div>
        <div class="absolute top-16 right-24 w-16 h-16 rounded-full bg-white/10 pointer-events-none"></div>

        <div class="relative z-10 flex justify-between items-center">
          <h1 class="font-bold text-white flex-shrink-0" style="font-size:20px; line-height:1.2;">
            📊 Laporan
          </h1>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              type="button"
              @click="showExportMenu = true"
              class="flex items-center gap-1.5 bg-white text-[#2F6BFF] font-semibold
                     rounded-full shadow-[0_4px_14px_rgba(0,0,0,0.12)] transition-transform
                     active:scale-95 px-3.5"
              style="font-size:11px; min-height:40px;"
            >
              <Download :size="13" /> Export
            </button>
            <button
              type="button"
              class="relative rounded-full bg-white/15 backdrop-blur-sm flex items-center
                     justify-center text-white transition-transform active:scale-95"
              style="width:40px;height:40px;"
            >
              <Bell :size="15" />
              <span
                v-if="$page.props.unread_notifications > 0"
                class="absolute -top-1 -right-1 bg-rose-500 text-white font-bold w-4 h-4
                       rounded-full flex items-center justify-center ring-2 ring-[#2F6BFF]"
                style="font-size:9px;"
              >
                {{ $page.props.unread_notifications }}
              </span>
            </button>
          </div>
        </div>

        <div
          class="relative inline-block z-10 overflow-hidden transition-all duration-300 ease-out"
          :class="isScrolled ? 'max-h-0 opacity-0 mt-0' : 'max-h-12 opacity-100 mt-3'"
        >
          <button
            type="button"
            @click="showMonthMenu = !showMonthMenu"
            class="flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white font-semibold
                   rounded-full transition-transform active:scale-95 px-3.5"
            style="font-size:12px; min-height:36px;"
          >
            <Calendar :size="13" /> {{ periodLabel }}
            <ChevronDown :size="13" :class="['transition-transform', showMonthMenu && 'rotate-180']" />
          </button>
          <div
            v-if="showMonthMenu"
            class="absolute top-full left-0 mt-2 bg-white rounded-[16px]
                   shadow-[0_8px_24px_rgba(15,23,42,0.12)] py-2 w-40 max-h-64 overflow-y-auto z-30"
          >
            <button
              type="button"
              v-for="m in months"
              :key="m.value"
              @click="changePeriod(m.value)"
              :class="[
                'block w-full text-left px-4 py-2.5',
                period === m.value ? 'text-[#2F6BFF] font-semibold bg-[#EAF0FF]' : 'text-slate-600 hover:bg-slate-50'
              ]"
              style="font-size:12px;"
            >
              {{ m.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ SUMMARY — 2 kolom, padet, no-truncate ═══ -->
      <div class="px-5 relative z-20 grid grid-cols-2 gap-2.5" style="margin-top:-28px;">
        <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] p-3 min-w-0">
          <div class="flex items-center gap-1 mb-1">
            <div class="w-5 h-5 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
              <TrendingUp :size="10" class="text-emerald-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10px;">Pemasukan</span>
          </div>
          <div
            class="font-bold text-slate-800 text-left tracking-tight overflow-hidden text-ellipsis whitespace-nowrap"
            style="font-size:clamp(13px,3.9vw,17px); line-height:1.2;"
          >
            {{ formatRupiah(totalIncome) }}
          </div>
          <div
            :class="['font-medium text-left mt-1 truncate', incomeChange >= 0 ? 'text-emerald-500' : 'text-rose-500']"
            style="font-size:10px;"
          >
            {{ incomeChange >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeChange) }}%
          </div>
        </div>
        <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.10)] p-3 min-w-0">
          <div class="flex items-center gap-1 mb-1">
            <div class="w-5 h-5 rounded-full bg-rose-50 flex items-center justify-center flex-shrink-0">
              <TrendingDown :size="10" class="text-rose-500" />
            </div>
            <span class="text-slate-400 truncate" style="font-size:10px;">Pengeluaran</span>
          </div>
          <div
            class="font-bold text-slate-800 text-left tracking-tight overflow-hidden text-ellipsis whitespace-nowrap"
            style="font-size:clamp(13px,3.9vw,17px); line-height:1.2;"
          >
            {{ formatRupiah(totalExpense) }}
          </div>
          <div
            :class="['font-medium text-left mt-1 truncate', expenseChange <= 0 ? 'text-emerald-500' : 'text-rose-500']"
            style="font-size:10px;"
          >
            {{ expenseChange >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseChange) }}%
          </div>
        </div>
      </div>

      <div class="px-5 pt-4 space-y-4">

        <!-- ═══ FILTER ═══ -->
        <div class="relative bg-white rounded-full p-1 flex shadow-[0_4px_14px_rgba(15,23,42,0.06)]">
          <div
            class="absolute top-1 bottom-1 rounded-full bg-[#2F6BFF]
                   shadow-[0_2px_8px_rgba(47,107,255,0.35)] transition-all duration-300 ease-out"
            :style="segmentStyle"
          ></div>
          <button
            type="button"
            v-for="opt in rangeOptions"
            :key="opt.value"
            @click="changeRange(opt.value)"
            :class="[
              'relative z-10 flex-1 text-center font-medium py-2.5 rounded-full transition-all',
              'active:scale-95 whitespace-nowrap overflow-hidden',
              range === opt.value ? 'text-white' : 'text-slate-500'
            ]"
            style="font-size:11px; min-height:38px;"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- ═══ CHART ═══ -->
        <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
          <div class="flex justify-between items-center mb-4 gap-2">
            <div class="font-semibold text-slate-800 text-left truncate min-w-0 flex-1" style="font-size:16px;">
              Pemasukan vs Pengeluaran ({{ activeRangeLabel }})
            </div>
            <button
              type="button"
              class="flex items-center gap-1 bg-slate-50 text-slate-500 font-medium
                     px-2.5 rounded-full flex-shrink-0 whitespace-nowrap"
              style="font-size:11px; min-height:30px;"
            >
              Bulanan <ChevronDown :size="11" />
            </button>
          </div>

          <div class="flex gap-2">
            <div class="flex flex-col justify-between text-right flex-shrink-0" style="height:150px; width:32px;">
              <span class="text-slate-400" style="font-size:9.5px;">{{ yAxisLabel(4) }}</span>
              <span class="text-slate-400" style="font-size:9.5px;">{{ yAxisLabel(3) }}</span>
              <span class="text-slate-400" style="font-size:9.5px;">{{ yAxisLabel(2) }}</span>
              <span class="text-slate-400" style="font-size:9.5px;">{{ yAxisLabel(1) }}</span>
              <span class="text-slate-400" style="font-size:9.5px;">0</span>
            </div>
            <div class="flex items-end justify-between gap-1.5 flex-1 min-w-0 border-l border-slate-100 pl-2" style="height:150px;">
              <div v-for="item in barData" :key="item.period" class="flex flex-col items-center flex-1 min-w-0">
                <div class="flex items-end gap-1" style="height:120px;">
                  <div
                    class="w-2.5 rounded-t-md bg-emerald-500 transition-all"
                    :style="`height:${barHeight(item.income)}px`"
                    :title="formatRupiah(item.income)"
                  ></div>
                  <div
                    class="w-2.5 rounded-t-md bg-rose-500 transition-all"
                    :style="`height:${barHeight(item.expense)}px`"
                    :title="formatRupiah(item.expense)"
                  ></div>
                </div>
                <div
                  :class="['mt-1.5 font-normal', item.active ? 'text-[#2F6BFF] font-semibold' : 'text-slate-400']"
                  style="font-size:10.5px;"
                >
                  {{ item.period }}
                </div>
              </div>
            </div>
          </div>

          <div class="flex gap-4 justify-center pt-3 mt-1 border-t border-slate-100">
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Pemasukan
            </div>
            <div class="flex items-center gap-1.5 text-slate-500 font-normal" style="font-size:11px;">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span> Pengeluaran
            </div>
          </div>
        </div>

        <!-- ═══ HEALTH + EMERGENCY (stack) ═══ -->
        <div class="space-y-4">
          <div class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-[#EAF0FF] flex items-center justify-center flex-shrink-0">
                <Activity :size="15" class="text-[#2F6BFF]" />
              </div>
              <span class="font-semibold text-slate-800 text-left" style="font-size:16px;">Budget Health Score</span>
            </div>
            <div class="text-left">
              <span class="font-bold text-[#2F6BFF]" style="font-size:24px; line-height:1.1;">{{ healthScore }}</span>
              <span class="text-slate-400" style="font-size:12px;">/100</span>
            </div>
            <div
              :class="['font-medium mb-4 mt-1 flex items-center gap-1.5 text-left', healthColorText]"
              style="font-size:12px;"
            >
              <AppIcon :slug="`health_tier_${healthStatus}`" class="w-3.5 h-3.5 inline-block flex-shrink-0">
                {{ defaultTierEmoji(healthStatus) }}
              </AppIcon>
              {{ healthLabel(healthStatus) }}
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center gap-2" style="font-size:11px;">
                <span class="w-[92px] text-slate-500 flex-shrink-0">Rasio Tabungan</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden min-w-0">
                  <div class="h-full bg-emerald-500 rounded-full" :style="`width:${Math.min(savingRatio,100)}%`"></div>
                </div>
                <span class="w-9 text-right font-semibold flex-shrink-0">{{ savingRatio }}%</span>
              </div>
              <div class="flex items-center gap-2" style="font-size:11px;">
                <span class="w-[92px] text-slate-500 flex-shrink-0">Disiplin Anggaran</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden min-w-0">
                  <div class="h-full bg-purple-500 rounded-full" :style="`width:${budgetDiscipline}%`"></div>
                </div>
                <span class="w-9 text-right font-semibold flex-shrink-0">{{ budgetDiscipline }}%</span>
              </div>
              <div class="flex items-center gap-2" style="font-size:11px;">
                <span class="w-[92px] text-slate-500 flex-shrink-0">Runway</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden min-w-0">
                  <div class="h-full bg-[#2F6BFF] rounded-full" :style="`width:${runwayScore}%`"></div>
                </div>
                <span class="w-9 text-right font-semibold flex-shrink-0">{{ runwayScore }}%</span>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.15)] p-5 relative overflow-hidden">
            <div class="flex items-center gap-2 mb-3 relative z-10">
              <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <Shield :size="15" class="text-emerald-400" />
              </div>
              <span class="font-semibold text-left" style="font-size:16px;">Dana Darurat</span>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="text-left relative z-10 mb-3">
                <span class="font-bold" style="font-size:clamp(26px,7vw,32px); font-weight:700; line-height:1.1;">
                  {{ runwayMonths }}
                </span>
                <span class="text-white/60" style="font-size:12px;"> Bulan dari target 6 bulan</span>
              </div>
              <div class="h-2 bg-white/15 rounded-full overflow-hidden mb-2 relative z-10">
                <div class="h-full bg-amber-400 rounded-full" :style="`width:${Math.min((runwayMonths/6)*100,100)}%`"></div>
              </div>
              <div class="text-white/45 relative z-10 text-left" style="font-size:11px;">Target ideal: 6 bulan pengeluaran</div>
            </template>
            <div v-else class="text-white/60 relative z-10 text-left" style="font-size:12px;">Belum cukup data.</div>
            <PiggyBank :size="70" class="absolute -bottom-3 -right-3 text-white/[0.08]" />
          </div>
        </div>

        <!-- ═══ DONUT ═══ -->
        <div v-if="donutData.length > 0" class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
          <div class="font-semibold text-slate-800 mb-4 text-left" style="font-size:16px;">Kategori Pengeluaran</div>
          <div class="flex flex-col items-center gap-5">
            <div class="relative flex-shrink-0" style="width:clamp(120px,35vw,140px);height:clamp(120px,35vw,140px);">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <circle
                  v-for="(seg, i) in donutSegments"
                  :key="i"
                  cx="50" cy="50" r="40"
                  fill="transparent"
                  :stroke="donutColors[i % donutColors.length]"
                  stroke-width="15"
                  stroke-linecap="round"
                  :stroke-dasharray="`${seg.length} ${251.2 - seg.length}`"
                  :stroke-dashoffset="-seg.offset"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <div class="text-slate-400" style="font-size:11px;">Total</div>
                <div class="font-bold" style="font-size:clamp(14px,4vw,17px); line-height:1.1;">
                  {{ formatShort(totalExpense) }}
                </div>
              </div>
            </div>
            <div class="w-full flex flex-col gap-3">
              <div v-for="(cat, i) in donutData" :key="cat.category" class="flex items-center gap-2" style="font-size:13px;">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                <span class="flex-1 text-slate-700 font-medium text-left min-w-0 truncate">{{ cat.emoji }} {{ cat.category }}</span>
                <div class="text-right flex-shrink-0">
                  <div class="font-semibold text-slate-800" style="font-size:12px;">{{ cat.percent }}%</div>
                  <div class="text-slate-400" style="font-size:10px;">{{ formatRupiah(cat.total) }}</div>
                </div>
              </div>
            </div>
            <button type="button" class="w-full text-center text-[#2F6BFF] font-semibold flex items-center justify-center gap-1 pt-2" style="font-size:13px;">
              Lihat Semua Kategori <ArrowRight :size="14" />
            </button>
          </div>
        </div>

        <!-- ═══ BUDGET VS REALISASI ═══ -->
        <div v-if="budgets.length > 0" class="bg-white rounded-[24px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-5">
          <div class="font-semibold text-slate-800 mb-4 text-left" style="font-size:16px;">Budget vs Realisasi</div>
          <div v-for="b in budgets" :key="b.category" class="mb-4 last:mb-0">
            <div class="flex justify-between mb-2 gap-2" style="font-size:13px;">
              <span class="font-medium text-slate-700 text-left truncate">{{ b.emoji }} {{ b.category }}</span>
              <span class="text-slate-400 flex-shrink-0" style="font-size:11px;">
                {{ formatShort(b.spent) }} / {{ formatShort(b.budget) }}
              </span>
            </div>
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div
                :class="['h-full rounded-full', b.status === 'over' ? 'bg-rose-500' : b.status === 'warn' ? 'bg-amber-500' : 'bg-emerald-500']"
                :style="`width:${Math.min(b.percent,100)}%`"
              ></div>
            </div>
          </div>
        </div>

        <!-- ═══ INSIGHT ═══ -->
        <div>
          <div class="font-semibold text-slate-800 mb-4 text-left" style="font-size:16px;">Insight Bulan Ini</div>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-3.5">
              <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center mb-2">
                <TrendingUp :size="15" class="text-emerald-500" />
              </div>
              <div class="text-slate-400 leading-snug" style="font-size:10.5px;">Pemasukan tertinggi</div>
              <div class="font-semibold leading-snug mt-0.5 break-words" style="font-size:13px;">
                {{ insight.biggest_income ? formatShort(insight.biggest_income.amount) : dash }}
              </div>
            </div>
            <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-3.5">
              <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center mb-2">
                <TrendingDown :size="15" class="text-rose-500" />
              </div>
              <div class="text-slate-400 leading-snug" style="font-size:10.5px;">Pengeluaran tertinggi</div>
              <div class="font-semibold leading-snug mt-0.5 break-words" style="font-size:13px;">
                {{ insight.biggest_expense ? formatShort(insight.biggest_expense.amount) : dash }}
              </div>
            </div>
            <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-3.5">
              <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center mb-2">
                <Users :size="15" class="text-purple-500" />
              </div>
              <div class="text-slate-400 leading-snug" style="font-size:10.5px;">Rata-rata harian</div>
              <div class="font-semibold leading-snug mt-0.5 break-words" style="font-size:13px;">
                {{ formatShort(insight.daily_average) }}
              </div>
            </div>
            <div class="bg-white rounded-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.06)] p-3.5">
              <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center mb-2">
                <CalendarDays :size="15" class="text-amber-500" />
              </div>
              <div class="text-slate-400 leading-snug" style="font-size:10.5px;">Hari paling boros</div>
              <div class="font-semibold leading-snug mt-0.5 break-words" style="font-size:13px;">
                {{ wastefulDayLabel }}
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Export Menu Bottom Sheet -->
    <Teleport to="body">
      <div
        v-if="showExportMenu"
        class="report-tw fixed inset-0 bg-slate-900/45 backdrop-blur-sm z-[500] flex items-end justify-center"
        @click.self="showExportMenu = false"
      >
        <div class="bg-white rounded-t-[24px] w-full max-w-md p-6 pb-10">
          <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-6"></div>
          <div class="font-semibold mb-5 text-left" style="font-size:17px;">📤 Export Laporan {{ periodLabel }}</div>

          <a :href="route('report.export-pdf', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[16px] mb-3">
            <span class="text-2xl">📄</span>
            <div class="text-left min-w-0">
              <div class="font-semibold text-slate-800" style="font-size:14px;">Export PDF</div>
              <div class="text-slate-500" style="font-size:12px;">Laporan rapi untuk dicetak atau dibagikan</div>
            </div>
          </a>

          <a :href="route('report.export-excel', { period })" class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[16px] mb-3">
            <span class="text-2xl">📊</span>
            <div class="text-left min-w-0">
              <div class="font-semibold text-slate-800" style="font-size:14px;">Export Excel</div>
              <div class="text-slate-500" style="font-size:12px;">Detail transaksi dalam format spreadsheet</div>
            </div>
          </a>

          <button
            type="button"
            class="flex items-center gap-3 w-full p-4 bg-slate-50 rounded-[16px] mb-3 disabled:opacity-60"
            @click="sendWhatsApp"
            :disabled="sendingWa"
          >
            <span class="text-2xl">💬</span>
            <div class="text-left min-w-0">
              <div class="font-semibold text-slate-800" style="font-size:14px;">
                {{ sendingWa ? 'Mengirim...' : 'Kirim ke WhatsApp' }}
              </div>
              <div class="text-slate-500" style="font-size:12px;">Ringkasan laporan dikirim ke nomor WA kamu</div>
            </div>
          </button>

          <button type="button" class="w-full py-3.5 rounded-[16px] font-medium text-slate-500 bg-slate-50" style="font-size:14px;" @click="showExportMenu = false">
            Batal
          </button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'
import axios from 'axios'
import {
  TrendingUp, TrendingDown, Download, Bell, ChevronDown, Calendar,
  Activity, Shield, PiggyBank, Users, CalendarDays, ArrowRight,
} from 'lucide-vue-next'

const props = defineProps({
  period: String, periodLabel: String, range: String,
  barData: Array, donutData: Array, budgets: Array,
  totalIncome: Number, totalExpense: Number,
  incomeChange: Number, expenseChange: Number,
  savingRatio: Number, budgetDiscipline: Number,
  runwayMonths: Number, runwayScore: Number,
  healthScore: Number, healthStatus: String,
  insight: Object, months: Array,
})

const showExportMenu = ref(false)
const showMonthMenu = ref(false)
const sendingWa = ref(false)
const dash = '-'
const isScrolled = ref(false)

function onScroll() {
  isScrolled.value = window.scrollY > 40
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
})
onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
})

const rangeOptions = [
  { value: '6months', label: '6 Bulan' },
  { value: '12months', label: '12 Bulan' },
  { value: 'year', label: 'Tahun Ini' },
  { value: 'all', label: 'Semua' },
]

const activeIndex = computed(() => rangeOptions.findIndex((o) => o.value === props.range))
const activeRangeLabel = computed(() => rangeOptions[activeIndex.value]?.label ?? '6 Bulan')

const segmentStyle = computed(() => {
  const width = 100 / rangeOptions.length
  const left = width * activeIndex.value
  return { width: `${width}%`, left: `${left}%` }
})

function changeRange(r) {
  router.get(route('report'), { period: props.period, range: r }, { preserveState: true, preserveScroll: true })
}
function changePeriod(p) {
  showMonthMenu.value = false
  router.get(route('report'), { period: p, range: props.range }, { preserveState: true })
}

async function sendWhatsApp() {
  sendingWa.value = true
  try {
    await axios.post(route('report.send-whatsapp'), { period: props.period })
    showExportMenu.value = false
    router.reload({ only: ['flash'] })
  } catch (e) {
  } finally {
    sendingWa.value = false
  }
}

const donutColors = ['#2F6BFF', '#60A5FA', '#818CF8', '#34D399', '#FBBF24', '#F87171', '#A78BFA', '#38BDF8']

const maxBarVal = computed(() => Math.max(...props.barData.flatMap((d) => [d.income, d.expense]), 1))
function barHeight(v) { return Math.max(3, (v / maxBarVal.value) * 120) }
function yAxisLabel(step) { return formatShort((maxBarVal.value * step) / 4) }

const donutSegments = computed(() => {
  let offset = 0
  return props.donutData.map((d) => {
    const length = (d.percent / 100) * 251.2
    const seg = { length, offset }
    offset += length
    return seg
  })
})

const healthColorText = computed(() => {
  const map = { sehat: 'text-emerald-500', cukup: 'text-amber-500', perlu_perhatian: 'text-rose-500' }
  return map[props.healthStatus] ?? 'text-slate-500'
})

function healthLabel(s) {
  return { sehat: 'Sehat', cukup: 'Cukup', perlu_perhatian: 'Perlu Perhatian' }[s] ?? s
}
function defaultTierEmoji(s) {
  return { sehat: '✅', cukup: '⚠️', perlu_perhatian: '🔴' }[s] ?? ''
}

function formatRupiah(n) {
  return 'Rp' + Number(n || 0).toLocaleString('id-ID')
}
function formatShort(n) {
  const num = Number(n || 0)
  if (num >= 1000000) return 'Rp' + (num / 1000000).toFixed(1) + 'jt'
  if (num >= 1000) return 'Rp' + (num / 1000).toFixed(0) + 'rb'
  return 'Rp' + Math.round(num)
}

const wastefulDayLabel = computed(() => {
  if (props.insight?.most_wasteful_day?.date) return props.insight.most_wasteful_day.date
  return dash
})
</script>

<style scoped>
.report-tw button, .report-tw select, .report-tw input, .report-tw a {
  border: none; outline: none; background-color: transparent; font: inherit; color: inherit;
  -webkit-appearance: none; appearance: none; box-sizing: border-box;
}
.report-tw button { cursor: pointer; }
.report-tw a { text-decoration: none; }
</style>
EOT
);

echo "\n=== SELESAI ===\n";
