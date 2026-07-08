<template>
  <AppLayout>
    <div class="page-content">

      <div class="page-header">
        <h1 class="page-title">Budget 💡</h1>
        <button class="copy-btn" @click="copyLastMonth" title="Salin dari bulan lalu">
          📋 Salin Bulan Lalu
        </button>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Atur batas pengeluaran per kategori dan pantau strategi 50/30/20.</span>
      </div>

      <!-- Month selector -->
      <div class="month-scroll">
        <button v-for="m in months" :key="m.value"
          :class="['mpill', { active: period === m.value }]"
          @click="changePeriod(m.value)">
          {{ m.label }}
        </button>
      </div>

      <!-- Summary strip -->
      <div class="summary-strip">
        <div class="ss-item">
          <div class="ss-label">Total Budget</div>
          <div class="ss-val">{{ formatRupiah(total_budget) }}</div>
        </div>
        <div class="ss-item">
          <div class="ss-label">Terpakai</div>
          <div class="ss-val" :class="total_spent > total_budget ? 'red' : 'green'">
            {{ formatRupiah(total_spent) }}
          </div>
        </div>
        <div class="ss-item">
          <div class="ss-label">Sisa</div>
          <div class="ss-val">{{ formatRupiah(Math.max(0, total_budget - total_spent)) }}</div>
        </div>
      </div>

      <!-- 50/30/20 Strategy -->
      <div v-if="strategy && total_income > 0" class="strategy-card">
        <div class="sc-header">
          <div class="sc-title">Strategi 50/30/20</div>
          <div class="sc-sub">Berdasarkan pemasukan {{ formatShort(total_income) }}</div>
        </div>
        <div class="sc-items">
          <div class="sc-item">
            <div class="sci-top">
              <span class="sci-label">50% Kebutuhan</span>
              <span :class="['sci-pct', strategy.kebutuhan.pct > 50 ? 'over' : '']">
                {{ strategy.kebutuhan.pct }}%
              </span>
            </div>
            <div class="sci-bar">
              <div class="sci-fill needs" :style="`width:${Math.min(strategy.kebutuhan.pct*2, 100)}%`"></div>
            </div>
            <div class="sci-vals">
              <span>{{ formatShort(strategy.kebutuhan.spent) }} dipakai</span>
              <span>Target: {{ formatShort(strategy.kebutuhan.target) }}</span>
            </div>
          </div>
          <div class="sc-item">
            <div class="sci-top">
              <span class="sci-label">30% Keinginan</span>
              <span :class="['sci-pct', strategy.keinginan.pct > 30 ? 'over' : '']">
                {{ strategy.keinginan.pct }}%
              </span>
            </div>
            <div class="sci-bar">
              <div class="sci-fill wants" :style="`width:${Math.min((strategy.keinginan.pct/30)*100, 100)}%`"></div>
            </div>
            <div class="sci-vals">
              <span>{{ formatShort(strategy.keinginan.spent) }} dipakai</span>
              <span>Target: {{ formatShort(strategy.keinginan.target) }}</span>
            </div>
          </div>
          <div class="sc-item">
            <div class="sci-top">
              <span class="sci-label">20% Tabungan</span>
              <span :class="['sci-pct', strategy.tabungan.pct < 20 ? 'low' : '']">
                {{ strategy.tabungan.pct }}%
              </span>
            </div>
            <div class="sci-bar">
              <div class="sci-fill saving" :style="`width:${Math.min((strategy.tabungan.pct/20)*100, 100)}%`"></div>
            </div>
            <div class="sci-vals">
              <span>{{ formatShort(strategy.tabungan.spent) }} ditabung</span>
              <span>Target: {{ formatShort(strategy.tabungan.target) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Budget per kategori -->
      <div class="sec-label">Budget per Kategori</div>
      <div class="budget-hint">
        💡 Atur budget untuk setiap kategori. Klik nominal untuk edit.
      </div>

      <div class="budget-list">
        <div v-for="b in budgets" :key="b.category_id" class="budget-row">

          <div class="br-left">
            <span class="br-emoji">{{ b.category_emoji }}</span>
            <div class="br-info">
              <div class="br-name">{{ b.category_name }}</div>
              <div class="br-spent" v-if="b.budget > 0">
                {{ formatShort(b.spent) }} / {{ formatShort(b.budget) }}
              </div>
              <div class="br-spent unset" v-else>Belum diset</div>
            </div>
          </div>

          <div class="br-right">
            <!-- Progress bar (hanya jika ada budget) -->
            <div v-if="b.budget > 0" class="br-progress">
              <div class="br-bar">
                <div :class="['br-fill', b.status]" :style="`width:${b.percent}%`"></div>
              </div>
              <span :class="['br-pct', b.status]">{{ b.percent }}%</span>
            </div>

            <!-- Input budget -->
            <div class="br-input-wrap">
              <input
                :value="localBudgets[b.category_id]"
                @input="updateLocal(b.category_id, $event.target.value)"
                @focus="$event.target.select()"
                type="number"
                min="0"
                class="br-input"
                placeholder="0"
              />
            </div>
          </div>

        </div>
      </div>

      <!-- Save button -->
      <button class="btn-cc" @click="saveBudgets" :disabled="isSaving" style="margin-top:16px;">
        {{ isSaving ? 'Menyimpan...' : '💾 Simpan Semua Budget' }}
      </button>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  period: String,
  periodLabel: String,
  budgets: Array,
  total_income: Number,
  total_budget: Number,
  total_spent: Number,
  strategy: Object,
  months: Array,
})

const isSaving = ref(false)

// Local state untuk input budget
const localBudgets = reactive({})
props.budgets.forEach(b => {
  localBudgets[b.category_id] = b.budget || ''
})

const updateLocal = (catId, val) => {
  localBudgets[catId] = val
}

const saveBudgets = () => {
  isSaving.value = true

  const budgets = Object.entries(localBudgets).map(([category_id, amount]) => ({
    category_id,
    amount: parseFloat(amount) || 0,
  }))

  router.post(route('budget.upsert'), {
    budgets,
    period: props.period,
  }, {
    onFinish: () => { isSaving.value = false },
  })
}

const copyLastMonth = () => {
  if (!confirm('Salin budget dari bulan lalu ke bulan ini?')) return
  router.post(route('budget.copy'), { period: props.period })
}

const changePeriod = (p) => {
  router.get(route('budget.index'), { period: p }, { preserveState: true })
}

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return (n/1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.page-content { padding:20px; }
.page-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:14px; }
.page-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800; }
.copy-btn { padding:8px 14px;background:var(--background);border:none;border-radius:99px;font-size:12px;font-weight:600;cursor:pointer;color:var(--text-secondary);transition:all .15s; }
.copy-btn:hover { background:var(--border); }

.month-scroll { display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;margin-bottom:16px;padding-bottom:2px; }
.month-scroll::-webkit-scrollbar { display:none; }
.mpill { padding:6px 12px;border-radius:99px;font-size:11px;font-weight:500;cursor:pointer;white-space:nowrap;border:1.5px solid var(--border);background:var(--surface);color:var(--text-secondary);flex-shrink:0; }
.mpill.active { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border-color:var(--primary); }

.summary-strip { display:flex;gap:8px;margin-bottom:16px; }
.ss-item { flex:1;background:var(--surface);border-radius:var(--radius-md);padding:12px;box-shadow:var(--shadow-card);text-align:center; }
.ss-label { font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:4px; }
.ss-val { font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800; }
.ss-val.red { color:var(--danger); }
.ss-val.green { color:var(--success); }

/* 50/30/20 */
.strategy-card { background:var(--surface);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-card);margin-bottom:18px; }
.sc-header { margin-bottom:14px; }
.sc-title { font-size:13px;font-weight:700; }
.sc-sub { font-size:11px;color:var(--text-secondary);margin-top:2px; }
.sc-items { display:flex;flex-direction:column;gap:12px; }
.sc-item { }
.sci-top { display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px; }
.sci-label { font-weight:500; }
.sci-pct { font-weight:700; }
.sci-pct.over { color:var(--danger); }
.sci-pct.low { color:var(--amber); }
.sci-bar { height:7px;background:var(--background);border-radius:99px;overflow:hidden;margin-bottom:4px; }
.sci-fill { height:100%;border-radius:99px;transition:width .5s; }
.sci-fill.needs { background:var(--primary); }
.sci-fill.wants { background:var(--amber); }
.sci-fill.saving { background:var(--success); }
.sci-vals { display:flex;justify-content:space-between;font-size:10px;color:var(--text-secondary); }

/* Budget list */
.sec-label { font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px; }
.budget-hint { font-size:12px;color:var(--text-secondary);background:var(--amber-bg);border-radius:var(--radius-md);padding:10px 12px;margin-bottom:12px;line-height:1.6;color:#7a5a00; }
.budget-list { display:flex;flex-direction:column;gap:8px; }
.budget-row { background:var(--surface);border-radius:var(--radius-md);padding:12px 14px;box-shadow:var(--shadow-card);display:flex;align-items:center;gap:12px; }
.br-left { display:flex;align-items:center;gap:10px;flex:1;min-width:0; }
.br-emoji { font-size:22px;flex-shrink:0; }
.br-info { min-width:0; }
.br-name { font-size:13px;font-weight:600; }
.br-spent { font-size:11px;color:var(--text-secondary);margin-top:1px; }
.br-spent.unset { color:var(--border); }
.br-right { display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;min-width:110px; }
.br-progress { width:100%; }
.br-bar { height:5px;background:var(--background);border-radius:99px;overflow:hidden;margin-bottom:3px; }
.br-fill { height:100%;border-radius:99px;transition:width .4s; }
.br-fill.ok   { background:var(--success); }
.br-fill.warn { background:var(--amber); }
.br-fill.over { background:var(--danger); }
.br-fill.unset { background:var(--border); }
.br-pct { font-size:10px;font-weight:700; }
.br-pct.ok   { color:var(--success); }
.br-pct.warn { color:#7a5a00; }
.br-pct.over { color:var(--danger); }
.br-input-wrap { width:100%; }
.br-input { width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;text-align:right;outline:none;background:var(--background);transition:border-color .2s; }
.br-input:focus { border-color:var(--primary);background:var(--surface); }
</style>
