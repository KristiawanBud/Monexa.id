<template>
  <AppLayout>
    <div class="page-content">

      <div class="page-header">
        <h1 class="page-title">Tabungan 🏦</h1>
        <button class="add-btn" @click="showAddGoal = true">+ Goal Baru</button>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Buat target tabungan dan setor dari dompet kapan saja.</span>
      </div>

      <div class="info-box">
        💡 Pilih dari dompet mana uang ditarik, masukkan nominal, lalu <strong>Simpan</strong>.
        Saldo dompet berkurang, tabungan bertambah.
      </div>

      <!-- Goals list -->
      <div v-if="goals.length === 0" class="empty-state">
        <div style="font-size:40px;margin-bottom:10px;">🎯</div>
        <div style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;">Belum ada goal tabungan</div>
        <div style="max-width:200px;" @click="showAddGoal = true">+ Buat Goal Pertama</div>
      </div>

      <div v-for="goal in goals" :key="goal.id" class="goal-card">
        <div class="goal-header">
          <div>
            <div class="goal-title">{{ goal.emoji }} {{ goal.name }}</div>
            <div class="goal-sub">Target: {{ formatRupiah(goal.target_amount) }}{{ goal.deadline ? ` · ${goal.deadline}` : '' }}</div>
          </div>
          <span v-if="goal.status === 'completed'" style="font-size:20px;">✅</span>
        </div>

        <div class="progress-bar">
          <div class="progress-fill"
            :class="{ done: goal.status === 'completed' }"
            :style="`width:${goal.progress_percent}%`">
          </div>
        </div>

        <div class="goal-meta">
          <span><strong>{{ formatRupiah(goal.current_amount) }}</strong> terkumpul</span>
          <span>{{ goal.progress_percent }}%
            {{ goal.status !== 'completed' ? `· sisa ${formatRupiah(goal.target_amount - goal.current_amount)}` : '' }}
          </span>
        </div>

        <div v-if="goal.status === 'completed'" class="completed-badge">
          🎉 Goal tercapai!
        </div>

        <button class="riwayat-btn" @click="openRiwayat(goal)">
          📜 Lihat Riwayat Setoran
        </button>

        <!-- Setor form (hanya jika belum selesai) -->
        <div v-if="goal.status !== 'completed'" class="setor-form">
          <div class="setor-label">Setor dari dompet</div>
          <div class="dompet-pills">
            <button v-for="w in wallets" :key="w.id" type="button"
              :class="['dompet-pill', { selected: depositForms[goal.id]?.wallet_id === w.id }]"
              @click="setDepositWallet(goal.id, w.id)">
              🏦 {{ w.display_name }}
            </button>
          </div>
          <div class="setor-row">
            <input
              :value="depositForms[goal.id]?.amount"
              @input="setDepositAmount(goal.id, $event.target.value)"
              type="number" min="1"
              class="setor-input" placeholder="Rp 0" />
            <button class="setor-btn" @click="submitDeposit(goal.id)" :disabled="isSubmitting[goal.id]">
              {{ isSubmitting[goal.id] ? '...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Add Goal button -->
      <button class="add-goal-btn" @click="showAddGoal = true">
        <span style="font-size:24px;">＋</span>
        <span>Tambah Goal Baru</span>
      </button>

    </div>

    <!-- Add Goal Modal -->
    <Teleport to="body">
      <div v-if="showAddGoal" class="modal-overlay" @click.self="showAddGoal = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">Buat Goal Tabungan</div>
          <form @submit.prevent="submitAddGoal">
            <div class="form-group">
              <label class="form-label">Nama Goal</label>
              <input v-model="goalForm.name" type="text" class="form-input-cc"
                placeholder="Wedding Fund, HP Baru, Liburan Bali..." required />
            </div>
            <div class="form-group">
              <label class="form-label">Emoji</label>
              <input v-model="goalForm.emoji" type="text" class="form-input-cc"
                placeholder="💍" style="width:80px;" />
            </div>
            <div class="form-group">
              <label class="form-label">Target Nominal (Rp)</label>
              <input v-model="goalForm.target_amount" type="number" min="1"
                class="form-input-cc" style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;"
                placeholder="0" required />
            </div>
            <div class="form-group">
              <label class="form-label">Deadline (opsional)</label>
              <input v-model="goalForm.deadline" type="date" class="form-input-cc" />
            </div>
            <button type="submit" class="btn-cc" :disabled="goalForm.processing">
              {{ goalForm.processing ? 'Menyimpan...' : 'Buat Goal' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Riwayat Setoran Modal -->
      <div v-if="showRiwayat" class="modal-overlay" @click.self="showRiwayat = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">📜 Riwayat — {{ selectedGoal?.name }}</div>

          <div v-if="loadingDeposits" class="riwayat-empty">Memuat riwayat...</div>
          <div v-else-if="deposits.length === 0" class="riwayat-empty">Belum ada setoran.</div>
          <div v-else class="riwayat-list">
            <div v-for="d in deposits" :key="d.id" class="riwayat-item">
              <div class="ri-icon">💰</div>
              <div class="ri-info">
                <div class="ri-amt">{{ formatRupiah(d.amount) }}</div>
                <div class="ri-meta">{{ d.wallet }} · {{ d.deposited_at }}</div>
              </div>
            </div>
          </div>

          <button class="qa-cancel" style="margin-top:14px;" @click="showRiwayat = false">Tutup</button>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  goals: Array,
  wallets: Array,
})

const showAddGoal = ref(false)

// Riwayat setoran
const showRiwayat = ref(false)
const selectedGoal = ref(null)
const deposits = ref([])
const loadingDeposits = ref(false)

const openRiwayat = async (goal) => {
  selectedGoal.value = goal
  showRiwayat.value = true
  loadingDeposits.value = true
  deposits.value = []
  try {
    const { data } = await axios.get(route('saving.deposits', goal.id))
    deposits.value = data.deposits
  } catch {
  } finally {
    loadingDeposits.value = false
  }
}

// Deposit forms per goal
const depositForms  = reactive({})
const isSubmitting  = reactive({})

props.goals.forEach(g => {
  depositForms[g.id] = {
    wallet_id: props.wallets[0]?.id ?? '',
    amount: '',
    deposited_at: new Date().toISOString().split('T')[0],
  }
  isSubmitting[g.id] = false
})

const setDepositWallet = (goalId, walletId) => {
  if (!depositForms[goalId]) depositForms[goalId] = {}
  depositForms[goalId].wallet_id = walletId
}

const setDepositAmount = (goalId, val) => {
  if (!depositForms[goalId]) depositForms[goalId] = {}
  depositForms[goalId].amount = val
}

const submitDeposit = (goalId) => {
  const f = depositForms[goalId]
  if (!f?.amount || !f?.wallet_id) return
  isSubmitting[goalId] = true
  router.post(route('saving.deposit', goalId), {
    wallet_id:    f.wallet_id,
    amount:       f.amount,
    deposited_at: f.deposited_at ?? new Date().toISOString().split('T')[0],
  }, {
    onFinish: () => {
      isSubmitting[goalId] = false
      depositForms[goalId].amount = ''
    }
  })
}

// Add Goal Form
const goalForm = useForm({
  name: '', emoji: '', target_amount: '', deadline: '',
})

const submitAddGoal = () => {
  goalForm.post(route('saving.store'), {
    onSuccess: () => { showAddGoal.value = false; goalForm.reset() }
  })
}

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
.page-content { padding:20px; }
.page-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:16px; }
.page-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;letter-spacing:-.02em; }
.add-btn { padding:8px 16px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border:none;border-radius:99px;font-size:12px;font-weight:600;cursor:pointer; }

.info-box { background:var(--primary-bg);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:16px;font-size:12px;color:#1a5276;line-height:1.7; }

.goal-card { background:var(--surface);border-radius:var(--radius-xl);padding:20px;box-shadow:var(--shadow-card);margin-bottom:12px; }
.goal-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px; }
.goal-title { font-size:14px;font-weight:700; }
.goal-sub { font-size:11px;color:var(--text-secondary);margin-top:2px;margin-bottom:12px; }
.progress-bar { height:7px;background:var(--background);border-radius:99px;overflow:hidden;margin-bottom:7px; }
.progress-fill { height:100%;border-radius:99px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);transition:width .5s ease; }
.progress-fill.done { background:var(--success); }
.goal-meta { display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-bottom:14px; }
.goal-meta strong { color:var(--primary);font-size:13px; }
.completed-badge { text-align:center;font-size:13px;font-weight:700;color:var(--success);padding:8px;background:var(--success-bg);border-radius:var(--radius-md); }

.setor-form { padding-top:12px;border-top:1px solid var(--background); }
.setor-label { font-size:11px;font-weight:600;color:var(--text-secondary);letter-spacing:.04em;text-transform:uppercase;margin-bottom:8px; }
.dompet-pills { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px; }
.dompet-pill { display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface);font-size:12px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .15s; }
.dompet-pill.selected { border-color:var(--primary);background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }
.setor-row { display:flex;gap:8px; }
.setor-input { flex:1;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;background:var(--background);outline:none;transition:border-color .2s; }
.setor-input:focus { border-color:var(--primary);background:var(--surface); }
.setor-btn { padding:10px 18px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border:none;border-radius:var(--radius-md);font-size:12px;font-weight:700;cursor:pointer; }
.setor-btn:disabled { opacity:.5;cursor:not-allowed; }

.add-goal-btn { display:flex;flex-direction:column;align-items:center;gap:6px;width:100%;padding:20px;border:2px dashed var(--border);border-radius:var(--radius-xl);background:none;cursor:pointer;font-size:13px;color:var(--text-secondary);font-weight:500;margin-top:4px;transition:all .2s; }
.add-goal-btn:hover { border-color:var(--primary);background:var(--surface); }

.empty-state { text-align:center;padding:40px 20px; }
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:flex;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px); }
.modal-sheet { background:var(--surface);border-radius:20px 20px 0 0;width:100%;max-width:480px;padding:24px 20px 40px;max-height:90vh;overflow-y:auto;animation:slideUp .3s ease; }
@keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
.modal-handle { width:40px;height:4px;background:var(--border);border-radius:99px;margin:0 auto 20px; }
.modal-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;margin-bottom:16px; }
.form-group { margin-bottom:14px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }

.riwayat-btn { width:100%; padding:9px; background:var(--background); color:var(--text-secondary); border:none; border-radius:var(--radius-md); font-size:12px; font-weight:600; cursor:pointer; margin-bottom:10px; }
.riwayat-empty { text-align:center; padding:24px 0; font-size:13px; color:var(--text-secondary); }
.riwayat-list { display:flex; flex-direction:column; gap:8px; max-height:300px; overflow-y:auto; }
.riwayat-item { display:flex; align-items:center; gap:10px; padding:10px; background:var(--background); border-radius:var(--radius-md); }
.ri-icon { font-size:18px; }
.ri-amt { font-size:13px; font-weight:700; }
.ri-meta { font-size:11px; color:var(--text-secondary); margin-top:1px; }
.qa-cancel { width:100%; padding:13px; background:none; border:1.5px solid var(--border); border-radius:var(--radius-md); font-size:14px; font-weight:600; color:var(--text-secondary); cursor:pointer; }
</style>
