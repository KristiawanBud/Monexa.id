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
        <Link :href="route('admin.categories')" class="nav-item active" data-label="Kategori">
          <span class="ni-icon">🏷️</span><span v-if="!sc">Kategori</span>
        </Link>
        <Link :href="route('admin.packages')" class="nav-item" data-label="Paket">
          <span class="ni-icon">💳</span><span v-if="!sc">Paket & Harga</span>
        </Link>
        <Link :href="route('admin.subscriptions')" class="nav-item" data-label="Subscription">
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
            <div class="topbar-title">Kategori 🏷️</div>
            <div class="topbar-breadcrumb">Admin → Kategori</div>
          </div>
        </div>
        <button class="btn-add" @click="showAddModal = true">+ Tambah Kategori</button>
      </div>

      <div class="admin-content">
        <div v-if="$page.props.flash?.success" class="flash-success">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.flash?.error" class="flash-error">{{ $page.props.flash.error }}</div>

        <h3 class="group-title">💰 Pemasukan</h3>
        <div class="cat-grid">
          <div v-for="cat in incomeCategories" :key="cat.id" class="cat-card">
            <div class="cat-preview">
              <img v-if="cat.icon_url" :src="cat.icon_url" alt="" />
              <span v-else class="cat-emoji">{{ cat.emoji || '✨' }}</span>
            </div>
            <div class="cat-name">{{ cat.name }}</div>
            <div class="cat-badge" v-if="cat.is_system">Sistem</div>

            <input type="file" :ref="el => fileInputs[cat.id] = el" accept="image/*" style="display:none" @change="e => onFileChange(e, cat.id)" />
            <div class="cat-actions">
              <button class="btn-upload" @click="fileInputs[cat.id]?.click()">📤</button>
              <button v-if="cat.icon_url" class="btn-reset" @click="resetIcon(cat.id)">🔄</button>
              <button v-if="!cat.is_system" class="btn-delete" @click="deleteCategory(cat)">🗑️</button>
            </div>
          </div>
        </div>

        <h3 class="group-title">💸 Pengeluaran</h3>
        <div class="cat-grid">
          <div v-for="cat in expenseCategories" :key="cat.id" class="cat-card">
            <div class="cat-preview">
              <img v-if="cat.icon_url" :src="cat.icon_url" alt="" />
              <span v-else class="cat-emoji">{{ cat.emoji || '✨' }}</span>
            </div>
            <div class="cat-name">{{ cat.name }}</div>
            <div class="cat-badge" v-if="cat.is_system">Sistem</div>

            <input type="file" :ref="el => fileInputs[cat.id] = el" accept="image/*" style="display:none" @change="e => onFileChange(e, cat.id)" />
            <div class="cat-actions">
              <button class="btn-upload" @click="fileInputs[cat.id]?.click()">📤</button>
              <button v-if="cat.icon_url" class="btn-reset" @click="resetIcon(cat.id)">🔄</button>
              <button v-if="!cat.is_system" class="btn-delete" @click="deleteCategory(cat)">🗑️</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
        <div class="modal-box">
          <h3>Tambah Kategori Baru</h3>
          <form @submit.prevent="submitAdd">
            <div class="form-group">
              <label>Nama</label>
              <input v-model="addForm.name" type="text" required />
            </div>
            <div class="form-group">
              <label>Tipe</label>
              <select v-model="addForm.type" required>
                <option value="expense">Pengeluaran</option>
                <option value="income">Pemasukan</option>
              </select>
            </div>
            <div class="form-group">
              <label>Emoji Default</label>
              <input v-model="addForm.emoji" type="text" maxlength="10" placeholder="✨" />
            </div>
            <div class="modal-actions">
              <button type="button" class="btn-cancel" @click="showAddModal = false">Batal</button>
              <button type="submit" class="btn-save">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({ categories: Array })

const sc = ref(false)
const fileInputs = reactive({})
const showAddModal = ref(false)
const addForm = reactive({ name: '', type: 'expense', emoji: '' })

const incomeCategories = computed(() => props.categories.filter(c => c.type === 'income'))
const expenseCategories = computed(() => props.categories.filter(c => c.type === 'expense'))

function onFileChange(e, id) {
  const file = e.target.files[0]
  if (!file) return
  const form = new FormData()
  form.append('icon', file)
  router.post(route('admin.categories.icon.upload', id), form, {
    forceFormData: true, preserveScroll: true,
  })
}

function resetIcon(id) {
  if (!confirm('Kembalikan ke emoji default?')) return
  router.delete(route('admin.categories.icon.reset', id), { preserveScroll: true })
}

function deleteCategory(cat) {
  if (!confirm(`Hapus kategori "${cat.name}"?`)) return
  router.delete(route('admin.categories.destroy', cat.id), { preserveScroll: true })
}

function submitAdd() {
  router.post(route('admin.categories.store'), addForm, {
    preserveScroll: true,
    onSuccess: () => { showAddModal.value = false; Object.assign(addForm, { name: '', type: 'expense', emoji: '' }) },
  })
}
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
.btn-add { background:var(--green);color:white;border:none;padding:8px 16px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer; }

.group-title { font-size:14px;font-weight:800;margin:20px 0 12px; }
.cat-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:8px; }
.cat-card { background:var(--white);border-radius:var(--radius);padding:12px;box-shadow:var(--shadow);text-align:center;position:relative; }
.cat-preview { width:48px;height:48px;margin:0 auto 8px;border-radius:10px;background:var(--off);display:flex;align-items:center;justify-content:center;overflow:hidden; }
.cat-preview img { width:100%;height:100%;object-fit:contain; }
.cat-emoji { font-size:22px; }
.cat-name { font-size:12px;font-weight:600;margin-bottom:4px; }
.cat-badge { font-size:9px;background:var(--off);color:var(--ink-muted);padding:1px 6px;border-radius:99px;display:inline-block;margin-bottom:8px; }
.cat-actions { display:flex;gap:4px;justify-content:center; }
.btn-upload, .btn-reset, .btn-delete { border:none;padding:5px 8px;border-radius:6px;font-size:11px;cursor:pointer; }
.btn-upload { background:var(--ink);color:white; }
.btn-reset { background:var(--off); }
.btn-delete { background:#FDECEC;color:var(--red-dark); }

.flash-success { background:#E9FBEF;color:var(--green-dark);padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px; }
.flash-error { background:#FDECEC;color:var(--red-dark);padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px; }

.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px; }
.modal-box { background:white;border-radius:16px;padding:24px;width:100%;max-width:380px; }
.modal-box h3 { margin:0 0 16px;font-size:15px;font-weight:800; }
.form-group { margin-bottom:12px; }
.form-group label { display:block;font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:5px; }
.form-group input, .form-group select { width:100%;padding:9px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px; }
.modal-actions { display:flex;gap:8px;margin-top:16px; }
.btn-cancel { flex:1;background:var(--off);border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
.btn-save { flex:1;background:var(--green);color:white;border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
</style>
