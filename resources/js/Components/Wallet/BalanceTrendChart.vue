<template>
  <div ref="rootRef" class="card balance-trend-card" v-show="!failed">
    <div class="btc-header">
      <div class="btc-title-row">
        <span class="btc-title">📈 Tren Saldo</span>
        <span v-if="!loading && points.length" class="btc-percent" :class="percentChange >= 0 ? 'up' : 'down'">
          {{ percentChange >= 0 ? '▲' : '▼' }} {{ percentChange >= 0 ? '+' : '' }}{{ percentChange.toFixed(2) }}%
        </span>
      </div>
      <div class="btc-range-toggle">
        <button type="button" :class="['btc-range-btn', { active: range === '7d' }]" @click="setRange('7d')">7H</button>
        <button type="button" :class="['btc-range-btn', { active: range === '30d' }]" @click="setRange('30d')">30H</button>
      </div>
    </div>

    <div class="btc-body">
      <SkeletonLoader v-if="loading" variant="card" />
      <template v-else-if="points.length">
        <svg viewBox="0 0 300 80" preserveAspectRatio="none" class="btc-svg" aria-hidden="true">
          <polyline :points="polylinePoints" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <div class="btc-range-labels">
          <span>{{ firstLabel }}</span>
          <span>{{ lastLabel }}</span>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import SkeletonLoader from './SkeletonLoader.vue'

const range = ref('7d')
const points = ref([])
const percentChange = ref(0)
const loading = ref(true)
const failed = ref(false)
const rootRef = ref(null)

let observer = null
let hasFetchedOnce = false

async function fetchTrend() {
  loading.value = true
  try {
    const { data } = await axios.get(route('dompet.balanceTrend'), { params: { range: range.value } })
    points.value = data.points || []
    percentChange.value = data.percent_change ?? 0
    failed.value = false
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

function setRange(newRange) {
  if (range.value === newRange) return
  range.value = newRange
  fetchTrend()
}

const polylinePoints = computed(() => {
  if (!points.value.length) return ''
  const values = points.value.map((p) => Number(p.total_balance))
  const min = Math.min(...values)
  const max = Math.max(...values)
  const span = max - min || 1
  const stepX = points.value.length > 1 ? 300 / (points.value.length - 1) : 0
  return values
    .map((v, i) => {
      const x = stepX * i
      const y = 76 - ((v - min) / span) * 72
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})

const firstLabel = computed(() => formatDateShort(points.value[0]?.date))
const lastLabel = computed(() => formatDateShort(points.value[points.value.length - 1]?.date))

function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}

onMounted(() => {
  if (!rootRef.value || typeof IntersectionObserver === 'undefined') {
    fetchTrend()
    return
  }
  observer = new IntersectionObserver((entries) => {
    if (entries[0]?.isIntersecting && !hasFetchedOnce) {
      hasFetchedOnce = true
      fetchTrend()
      observer.disconnect()
    }
  }, { rootMargin: '150px' })
  observer.observe(rootRef.value)
})

onUnmounted(() => {
  observer?.disconnect()
})
</script>

<style scoped>
.balance-trend-card { margin-bottom: 16px; min-height: 132px; }
.btc-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.btc-title-row { display: flex; align-items: center; gap: 8px; }
.btc-title { font-size: 13px; font-weight: 700; }
.btc-percent { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 99px; }
.btc-percent.up { color: var(--success); background: var(--success-bg); }
.btc-percent.down { color: var(--danger); background: var(--danger-bg); }
.btc-range-toggle { display: flex; gap: 4px; background: var(--background); border-radius: 99px; padding: 2px; }
.btc-range-btn { padding: 5px 12px; min-height: 28px; border: none; background: none; border-radius: 99px; font-size: 11px; font-weight: 700; color: var(--text-secondary); cursor: pointer; }
.btc-range-btn.active { background: var(--primary); color: white; }
.btc-body { height: 80px; }
.btc-svg { width: 100%; height: 80px; display: block; }
.btc-range-labels { display: flex; justify-content: space-between; font-size: 10px; color: var(--text-secondary); margin-top: 4px; }
</style>
