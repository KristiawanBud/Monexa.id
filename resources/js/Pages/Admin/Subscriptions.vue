<template>
  <div class="admin-shell">
    <aside :class="['admin-sidebar', { collapsed: sc }]">
      <div class="sidebar-logo">
        <div class="logo-icon">CC</div>
        <span v-if="!sc" class="logo-name">CatatCuan Admin</span>
      </div>
      <button class="hamburger" @click="sc = !sc"><span></span><span></span><span></span></button>
      <nav class="sidebar-nav">
        <Link :href="route('admin.dashboard')" class="nav-item" data-label="Dashboard">
          <span class="ni-icon">📊</span><span v-if="!sc">Dashboard</span>
        </Link>
        <Link :href="route('admin.users')" class="nav-item" data-label="Users">
          <span class="ni-icon">👥</span><span v-if="!sc">Manajemen User</span>
        </Link>
        <Link :href="route('admin.packages')" class="nav-item" data-label="Paket">
          <span class="ni-icon">💳</span><span v-if="!sc">Paket & Harga</span>
        </Link>
        <Link :href="route('admin.subscriptions')" class="nav-item active" data-label="Subscription">
          <span class="ni-icon">📋</span><span v-if="!sc">Subscription User</span>
        </Link>
        <Link :href="route('admin.gateway.index')" class="nav-item" data-label="WA Gateway">
          <span class="ni-icon">📱</span><span v-if="!sc">WA Gateway</span>
        </Link>
        <Link :href="route('admin.cuan-ai-rules')" class="nav-item" data-label="CuanAI Rules">
          <span class="ni-icon">🤖</span><span v-if="!sc">CuanAI Rules</span>
        </Link>
        <Link :href="route('admin.icons')" class="nav-item" data-label="Icons">
          <span class="ni-icon">🖼️</span><span v-if="!sc">Icon & Assets</span>
        </Link>
        <Link :href="route('dashboard')" class="nav-item" data-label="App">
          <span class="ni-icon">🏠</span><span v-if="!sc">Kembali ke App</span>
        </Link>
      </nav>
    </aside>

    <div :class="['admin-main', { expanded: sc }]">
      <div class="admin-topbar">
        <div class="topbar-left">
          <button class="hamburger-top" @click="sc = !sc">☰</button>
          <div>
            <div class="topbar-title">Subscription User 📋</div>
            <div class="topbar-breadcrumb">Admin → Subscription User</div>
          </div>
        </div>
      </div>

      <div class="admin-content">
        <div v-if="$page.props.flash?.success" class="flash-success">
          {{ $page.props.flash.success }}
        </div>

        <div class="summary-row">
          <div class="summary-card"><div class="sc-label">Aktif</div><div class="sc-value">{{ summary.total_active }}</div></div>
          <div class="summary-card"><div class="sc-label">Trial</div><div class="sc-value">{{ summary.total_trial }}</div></div>
          <div class="summary-card"><div class="sc-label">Berbayar</div><div class="sc-value">{{ summary.total_paid }}</div></div>
          <div class="summary-card"><div class="sc-label">Expired</div><div class="sc-value">{{ summary.total_expired }}</div></div>
        </div>

        <div class="filter-row">
          <input v-model="search" placeholder="Cari nama/email..." class="filter-input" @keyup.enter="applyFilter" />
          <select v-model="statusFilter" class="filter-select" @change="applyFilter">
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select v-model="planFilter" class="filter-select" @change="applyFilter">
            <option value="">Semua Plan</option>
            <option value="trial">Trial</option>
            <option value="monthly">Bulanan</option>
            <option value="yearly">Tahunan</option>
          </select>
        </div>

        <div class="table-wrap">
          <table class="sub-table">
            <thead>
              <tr>
                <th>User</th><th>Plan</th><th>Status</th><th>Mulai</th><th>Berakhir</th><th>Jumlah</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in subscriptions.data" :key="s.id">
                <td>
                  <div class="u-name">{{ s.user_name }}</div>
                  <div class="u-email">{{ s.user_email }}</div>
                </td>
                <td><span class="plan-chip">{{ s.plan }}</span></td>
                <td><span :class="['status-chip', s.status]">{{ s.status }}</span></td>
                <td>{{ s.starts_at }}</td>
                <td>{{ s.ends_at || '-' }}</td>
                <td>Rp {{ formatRupiah(s.amount) }}</td>
                <td><button class="btn-edit-sm" @click="openEdit(s)">✏️</button></td>
              </tr>
              <tr v-if="subscriptions.data.length === 0">
                <td colspan="7" class="empty-row">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pagination" v-if="subscriptions.links">
          <Link v-for="link in subscriptions.links" :key="link.label"
            :href="link.url || '#'"
            :class="['page-link', { active: link.active, disabled: !link.url }]"
            v-html="link.label" />
        </div>
      </div>
    </div>

    <!-- Modal Edit -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <h3>Edit Subscription — {{ editing?.user_name }}</h3>
          <form @submit.prevent="submit">
            <div class="form-group">
              <label>Plan</label>
              <select v-model="form.plan" required>
                <option value="trial">Trial</option>
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select v-model="form.status" required>
                <option value="active">Aktif</option>
                <option value="expired">Expired</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Mulai</label>
                <input v-model="form.starts_at" type="date" required />
              </div>
              <div class="form-group">
                <label>Berakhir</label>
                <input v-model="form.ends_at" type="date" />
              </div>
            </div>
            <div class="form-group">
              <label>Jumlah (Rp)</label>
              <input v-model.number="form.amount" type="number" min="0" />
            </div>
            <div class="modal-actions">
              <button type="button" class="btn-cancel" @click="showModal = false">Batal</button>
              <button type="submit" class="btn-save">{{ processing ? 'Menyimpan...' : 'Simpan' }}</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  subscriptions: Object,
  summary: Object,
  filters: Object,
})

const sc = ref(false)
const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const planFilter = ref(props.filters?.plan || '')

const showModal = ref(false)
const processing = ref(false)
const editing = ref(null)
const form = reactive({ plan: 'monthly', status: 'active', starts_at: '', ends_at: '', amount: 0 })

function applyFilter() {
  router.get(route('admin.subscriptions'), {
    search: search.value, status: statusFilter.value, plan: planFilter.value,
  }, { preserveState: true, preserveScroll: true })
}

function openEdit(s) {
  editing.value = s
  Object.assign(form, {
    plan: s.plan, status: s.status, starts_at: s.starts_at, ends_at: s.ends_at, amount: s.amount,
  })
  showModal.value = true
}

function submit() {
  processing.value = true
  router.put(route('admin.subscriptions.update', editing.value.id), form, {
    preserveScroll: true,
    onFinish: () => { processing.value = false; showModal.value = false },
  })
}

const formatRupiah = (n) => Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
.admin-shell { display:flex;min-height:100vh;background:var(--off); }
.admin-sidebar { width:220px;min-height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;overflow-x:hidden;transition:width .25s ease;z-index:100; }
.admin-sidebar.collapsed { width:64px; }
.sidebar-logo { display:flex;align-items:center;gap:10px;padding:18px 14px;border-bottom:1px solid rgba(255,255,255,.08);position:relative; }
.logo-icon { width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:"Syne",sans-serif;font-size:11px;font-weight:800;color:white;flex-shrink:0; }
.logo-name { font-family:"Syne",sans-serif;font-size:14px;font-weight:800;color:white;white-space:nowrap; }
.hamburger { position:absolute;top:16px;right:10px;background:none;border:none;cursor:pointer;display:flex;flex-direction:column;gap:4px;padding:2px; }
.hamburger span { display:block;width:16px;height:2px;background:rgba(255,255,255,.5);border-radius:99px; }
.sidebar-nav { padding:8px 0; }
.nav-item { display:flex;align-items:center;gap:10px;padding:9px 14px;margin:1px 8px;border-radius:8px;cursor:pointer;color:rgba(255,255,255,.5);font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;position:relative;white-space:nowrap; }
.nav-item:hover { background:rgba(255,255,255,.07);color:rgba(255,255,255,.85); }
.nav-item.active { background:rgba(255,255,255,.13);color:white;font-weight:600; }
.nav-item.active::before { content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--green);border-radius:0 3px 3px 0; }
.ni-icon { font-size:16px;flex-shrink:0;width:20px;text-align:center; }
.admin-main { margin-left:220px;flex:1;min-height:100vh; }
.admin-main.expanded { margin-left:64px; }
.admin-topbar { background:var(--white);border-bottom:1px solid var(--stone);padding:0 24px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow); }
.topbar-left { display:flex;align-items:center;gap:12px; }
.hamburger-top { background:var(--stone);border:none;border-radius:8px;padding:6px 8px;cursor:pointer;font-size:16px; }
.topbar-title { font-family:"Syne",sans-serif;font-size:16px;font-weight:800; }
.topbar-breadcrumb { font-size:11px;color:var(--ink-muted); }
.admin-content { padding:24px; }

.flash-success { background:#E9FBEF; color:var(--green-dark); padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; }

.summary-row { display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:18px; }
.summary-card { background:var(--white);border-radius:var(--radius);padding:14px 16px;box-shadow:var(--shadow); }
.sc-label { font-size:11px;color:var(--ink-muted);font-weight:600;text-transform:uppercase; }
.sc-value { font-family:"Syne",sans-serif;font-size:22px;font-weight:800;margin-top:2px; }

.filter-row { display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap; }
.filter-input { flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px; }
.filter-select { padding:8px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px; }

.table-wrap { background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto; }
.sub-table { width:100%;border-collapse:collapse;font-size:13px; }
.sub-table th { text-align:left;padding:12px 14px;font-size:11px;text-transform:uppercase;color:var(--ink-muted);border-bottom:1px solid var(--stone); }
.sub-table td { padding:12px 14px;border-bottom:1px solid var(--stone); }
.u-name { font-weight:600; }
.u-email { font-size:11px;color:var(--ink-muted); }
.plan-chip { background:var(--off);padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;text-transform:capitalize; }
.status-chip { padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;text-transform:capitalize; }
.status-chip.active { background:#E9FBEF;color:var(--green-dark); }
.status-chip.expired { background:#FDECEC;color:var(--red-dark); }
.status-chip.cancelled { background:var(--stone);color:var(--ink-muted); }
.btn-edit-sm { background:var(--off);border:none;padding:6px 10px;border-radius:6px;cursor:pointer; }
.empty-row { text-align:center;color:var(--ink-muted);padding:30px; }

.pagination { display:flex;gap:4px;margin-top:16px;flex-wrap:wrap; }
.page-link { padding:6px 12px;border-radius:6px;background:var(--white);font-size:12px;text-decoration:none;color:var(--ink); box-shadow:var(--shadow); }
.page-link.active { background:var(--ink);color:white; }
.page-link.disabled { opacity:.4;pointer-events:none; }

.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px; }
.modal-box { background:white;border-radius:16px;padding:24px;width:100%;max-width:420px; }
.modal-box h3 { margin:0 0 16px;font-size:15px;font-weight:800; }
.form-group { margin-bottom:12px; }
.form-group label { display:block;font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:5px; }
.form-group input, .form-group select { width:100%;padding:9px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px;font-family:inherit; }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.modal-actions { display:flex;gap:8px;margin-top:16px; }
.btn-cancel { flex:1;background:var(--off);border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
.btn-save { flex:1;background:var(--green);color:white;border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
</style>
