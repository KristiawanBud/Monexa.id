<template>
  <AppLayout>
    <div class="page-content">

      <div class="page-header">
        <h1 class="page-title">Profil 👤</h1>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Kelola akun, langganan, dan pengaturan aplikasi kamu di sini.</span>
      </div>

      <!-- Subscription badge -->
      <div class="sub-card">
        <div class="sub-info">
          <div class="sub-name">{{ user.name }}</div>
          <div class="sub-email">{{ user.email }}</div>
          <div v-if="user.wa_number" class="sub-wa">📱 {{ user.wa_number }}</div>
        </div>
        <span :class="['sub-badge', subscription?.plan]">{{ planLabel(subscription?.plan) }}</span>
      </div>

      <!-- Admin Panel Shortcut (Super Admin only) -->
      <template v-if="user.role === 'super_admin'">
        <div class="section-title">Admin</div>
        <Link :href="route('admin.dashboard')" class="admin-shortcut-card">
          <div class="asc-icon">
            <AppIcon slug="admin_panel" class="asc-icon-img">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L4 5v6c0 5.25 3.4 9.74 8 11 4.6-1.26 8-5.75 8-11V5l-8-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </AppIcon>
          </div>
          <div class="asc-info">
            <div class="asc-title">Admin Panel</div>
            <div class="asc-sub">Kelola user, WA Gateway, CuanAI Rules, dll</div>
          </div>
          <div class="asc-arrow">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </Link>
      </template>

      <!-- Profil Form -->
      <div class="section-title" style="margin-top:18px;">Akun</div>
      <div class="card">
        <form @submit.prevent="submitProfile">
          <div class="form-group">
            <label class="form-label">Nama</label>
            <input v-model="profileForm.name" type="text" class="form-input-cc" required />
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input :value="user.email" type="email" class="form-input-cc" disabled />
          </div>
          <div class="form-group">
            <label class="form-label">Nomor WhatsApp</label>
            <input v-model="profileForm.wa_number" type="tel" class="form-input-cc" placeholder="+62 812-xxxx-xxxx" />
          </div>

          <div class="toggle-list">
            <div class="toggle-row">
              <div class="toggle-info">
                <div class="tl">Notifikasi WA</div>
                <div class="ts">Pengingat tagihan & laporan via WhatsApp</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" v-model="profileForm.notif_wa_enabled" />
                <span class="slider"></span>
              </label>
            </div>
            <div class="toggle-row">
              <div class="toggle-info">
                <div class="tl">Laporan Bulanan Otomatis</div>
                <div class="ts">Rekap dikirim tiap awal bulan</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" v-model="profileForm.monthly_report_enabled" />
                <span class="slider"></span>
              </label>
            </div>
            <div class="toggle-row">
              <div class="toggle-info">
                <div class="tl">Fitur Saham IDX</div>
                <div class="ts">Tampilkan portofolio saham di dashboard</div>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" v-model="profileForm.saham_enabled" />
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn-primary" :disabled="profileForm.processing">
            {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Profil' }}
          </button>
        </form>
      </div>

      <!-- Tampilan / Tema -->
      <div class="section-title" style="margin-top:18px;">Tampilan</div>
      <div class="card">
        <div class="theme-picker" role="radiogroup" aria-label="Pilih tema aplikasi">
          <button
            v-for="opt in themeOptions"
            :key="opt.value"
            type="button"
            role="radio"
            :aria-checked="currentTheme === opt.value"
            :class="['theme-option', { active: currentTheme === opt.value }]"
            @click="setTheme(opt.value)"
          >
            <span class="theme-swatch" :style="`background:${opt.swatch}`"></span>
            <span class="theme-option-label">{{ opt.label }}</span>
          </button>
        </div>
      </div>

      <!-- WA Bot info -->
      <div class="section-title" style="margin-top:18px;">WA Bot</div>
      <div v-if="bot_gateway" class="bot-card">
        <div class="bg-label">📱 Nomor Bot WA Kamu</div>
        <div class="bg-number">{{ bot_gateway.phone_number }}</div>
        <div class="bg-meta">{{ bot_gateway.name }} · Aktif sejak {{ bot_gateway.assigned_at }}</div>
      </div>
      <div v-else class="card" style="text-align:center;padding:20px;">
        <div style="font-size:24px;margin-bottom:6px;">📵</div>
        <div class="caption">Lengkapi nomor WA di atas untuk aktifkan bot</div>
      </div>

      <!-- Ganti Password -->
      <div class="section-title" style="margin-top:18px;">Keamanan</div>
      <div class="card">
        <form @submit.prevent="submitPassword">
          <div class="form-group">
            <label class="form-label">Password Saat Ini</label>
            <input v-model="passwordForm.current_password" type="password" class="form-input-cc" required />
          </div>
          <div class="form-group">
            <label class="form-label">Password Baru</label>
            <input v-model="passwordForm.password" type="password" class="form-input-cc" required />
            <div class="hint-text">Min 8 karakter, huruf besar, angka, simbol</div>
          </div>
          <div class="form-group">
            <label class="form-label">Konfirmasi Password Baru</label>
            <input v-model="passwordForm.password_confirmation" type="password" class="form-input-cc" required />
          </div>
          <button type="submit" class="btn-secondary" :disabled="passwordForm.processing">
            {{ passwordForm.processing ? 'Menyimpan...' : '🔒 Ganti Password' }}
          </button>
        </form>
      </div>

      <!-- Reset Data (Danger Zone) -->
      <div class="section-title" style="margin-top:18px;">Zona Berbahaya</div>
      <div class="card danger-card">
        <div class="dz-title">🗑️ Reset Semua Data</div>
        <div class="dz-desc">
          Menghapus semua transaksi, dompet, tagihan, tabungan, budget, dan aset.
          Akun kamu tetap ada — cocok untuk membersihkan data hasil coba-coba.
        </div>
        <button class="btn-danger" @click="showResetModal = true">
          Reset Data Sekarang
        </button>
      </div>

      <!-- Logout -->
      <div style="margin-top:18px;margin-bottom:30px;">
        <Link :href="route('logout')" method="post" as="button" class="logout-btn">
          🚪 Keluar dari Akun
        </Link>
      </div>

    </div>

    <!-- Reset Data Modal -->
    <Teleport to="body">
      <div v-if="showResetModal" class="modal-overlay" @click.self="showResetModal = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title" style="color:var(--danger);">⚠️ Konfirmasi Reset Data</div>
          <div class="warn-box">
            Tindakan ini TIDAK BISA DIBATALKAN. Semua transaksi, dompet, tagihan,
            tabungan, budget, dan aset akan dihapus permanen.
          </div>
          <form @submit.prevent="submitReset">
            <div class="form-group">
              <label class="form-label">Masukkan Password untuk Konfirmasi</label>
              <input v-model="resetForm.password" type="password" class="form-input-cc" required autofocus />
              <div v-if="resetForm.errors.password" class="error-text">{{ resetForm.errors.password }}</div>
            </div>
            <button type="submit" class="btn-danger" :disabled="resetForm.processing">
              {{ resetForm.processing ? 'Menghapus...' : '🗑️ Ya, Hapus Semua Data' }}
            </button>
            <button type="button" class="btn-secondary" style="margin-top:10px;" @click="showResetModal = false">
              Batal
            </button>
          </form>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'
import { useTheme } from '@/Composables/useTheme'

const { currentTheme, setTheme } = useTheme()

// Swatch preview tiap opsi tema mengikuti nilai --primary/--background asli di
// resources/css/themes/theme-{blue,green,dark}.css — bukan var(--...) karena swatch
// harus tetap merepresentasikan tampilan tema itu meski tema lain sedang aktif.
const themeOptions = [
  { value: 'blue', label: 'Biru', swatch: 'linear-gradient(135deg, #2563EB 50%, #FFFFFF 50%)' },
  { value: 'green', label: 'Hijau', swatch: 'linear-gradient(135deg, #16A34A 50%, #FFFFFF 50%)' },
  { value: 'dark', label: 'Gelap', swatch: 'linear-gradient(135deg, #5B8DF8 50%, #0F172A 50%)' },
  { value: 'system', label: 'Ikuti Sistem', swatch: 'linear-gradient(135deg, #2563EB 50%, #0F172A 50%)' },
]

const props = defineProps({
  user: Object,
  profile: Object,
  subscription: Object,
  bot_gateway: Object,
})

const showResetModal = ref(false)

const profileForm = useForm({
  name:                    props.user.name,
  wa_number:               props.user.wa_number ?? '',
  notif_wa_enabled:        props.profile?.notif_wa_enabled ?? true,
  monthly_report_enabled:  props.profile?.monthly_report_enabled ?? true,
  saham_enabled:           props.profile?.saham_enabled ?? false,
})

const submitProfile = () => {
  profileForm.put(route('account.profile'))
}

const passwordForm = useForm({
  current_password: '', password: '', password_confirmation: '',
})

const submitPassword = () => {
  passwordForm.put(route('account.password'), {
    onSuccess: () => passwordForm.reset()
  })
}

const resetForm = useForm({ password: '' })

const submitReset = () => {
  resetForm.post(route('account.reset-data'), {
    onSuccess: () => { showResetModal.value = false; resetForm.reset() }
  })
}

const planLabel = (plan) => ({ trial: '🎁 Trial', monthly: '✅ Monthly', yearly: '⭐ Yearly' }[plan] ?? plan)
</script>

<style scoped>
.page-content { padding:20px; }
.page-header {
  background:var(--surface); border-radius:var(--radius-lg); padding:16px 18px;
  margin-bottom:12px; box-shadow:var(--shadow-card);
  position:sticky; top:0; z-index:40;
}
.page-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; font-weight:800; }

.sub-card { display:flex; justify-content:space-between; align-items:center; background:var(--surface); border-radius:var(--radius-xl); padding:18px; box-shadow:var(--shadow-card); margin-bottom:16px; }
.sub-name { font-size:15px; font-weight:700; }
.sub-email { font-size:12px; color:var(--text-secondary); margin-top:2px; }
.sub-wa { font-size:11px; color:var(--text-secondary); margin-top:2px; }
.sub-badge { font-size:11px; font-weight:700; padding:6px 12px; border-radius:99px; flex-shrink:0; }
.sub-badge.trial   { background:var(--amber-bg); color:#92600A; }
.sub-badge.monthly { background:var(--primary-bg); color:var(--primary-dark); }
.sub-badge.yearly  { background:var(--success-bg); color:#15803D; }

.section-title { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--text-secondary); margin-bottom:10px; }

.admin-shortcut-card { display:flex; align-items:center; gap:12px; background:var(--surface); border-radius:var(--radius-xl); padding:16px 18px; box-shadow:var(--shadow-card); text-decoration:none; color:var(--text-primary); margin-bottom:4px; border:1.5px solid var(--border); transition:all .15s; }
.admin-shortcut-card:active { transform:scale(.98); }
.asc-icon { width:40px; height:40px; border-radius:12px; background:var(--primary-bg); display:flex; align-items:center; justify-content:center; color:var(--primary); flex-shrink:0; }
.asc-icon svg { width:22px; height:22px; }
.asc-icon-img { width: 22px; height: 22px; object-fit: contain; }
.asc-info { flex:1; }
.asc-title { font-size:14px; font-weight:700; }
.asc-sub { font-size:11px; color:var(--text-secondary); margin-top:2px; }
.asc-arrow { color:var(--text-faint); flex-shrink:0; }
.asc-arrow svg { width:18px; height:18px; }

.form-group { margin-bottom:14px; }
.form-label { font-size:12px; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:6px; }
.hint-text { font-size:11px; color:var(--text-faint); margin-top:5px; }
.error-text { font-size:11px; color:var(--danger); margin-top:5px; font-weight:600; }

.theme-picker { display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; }
.theme-option { display:flex; flex-direction:column; align-items:center; gap:8px; padding:14px 10px; min-height:44px; border-radius:var(--radius-md); border:1.5px solid var(--border); background:var(--surface); cursor:pointer; font-family:inherit; }
.theme-option.active { border-color:var(--primary); background:var(--primary-bg); }
.theme-option:focus-visible { outline:none; box-shadow:var(--shadow-focus); }
.theme-swatch { width:32px; height:32px; border-radius:50%; border:1px solid var(--border); }
.theme-option-label { font-size:12px; font-weight:600; color:var(--text-secondary); }
.theme-option.active .theme-option-label { color:var(--primary); }

.toggle-list { margin:14px 0; }
.toggle-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
.toggle-row:last-child { border-bottom:none; }
.toggle-info { flex:1; padding-right:16px; }
.tl { font-size:13px; font-weight:500; }
.ts { font-size:11px; color:var(--text-secondary); margin-top:1px; }
.toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; }
.slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:var(--border); border-radius:99px; transition:.2s; }
.slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; border-radius:50%; transition:.2s; }
input:checked + .slider { background:var(--primary); }
input:checked + .slider:before { transform:translateX(20px); }

.bot-card { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius:var(--radius-xl); padding:18px; color:white; margin-bottom:4px; }
.bg-label { font-size:10px; font-weight:700; letter-spacing:.06em; color:rgba(255,255,255,.7); margin-bottom:4px; }
.bg-number { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; font-weight:800; }
.bg-meta { font-size:11px; color:rgba(255,255,255,.6); margin-top:4px; }

.danger-card { border:1.5px solid var(--danger-bg); background:var(--danger-bg); }
.dz-title { font-size:14px; font-weight:700; color:#991B1B; margin-bottom:6px; }
.dz-desc { font-size:12px; color:#991B1B; line-height:1.6; margin-bottom:14px; }
.btn-danger { width:100%; padding:13px; background:var(--danger); color:white; border:none; border-radius:var(--radius-md); font-size:14px; font-weight:700; cursor:pointer; }
.btn-danger:disabled { opacity:.5; }

.logout-btn { width:100%; padding:13px; background:none; border:1.5px solid var(--danger-bg); border-radius:var(--radius-md); color:#991B1B; font-size:14px; font-weight:600; cursor:pointer; text-align:center; font-family:inherit; }

.modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:500; display:flex; align-items:flex-end; justify-content:center; backdrop-filter:blur(4px); }
.modal-sheet { background:var(--surface); border-radius:28px 28px 0 0; width:100%; max-width:480px; padding:24px 20px 40px; }
.modal-handle { width:40px; height:4px; background:var(--border); border-radius:99px; margin:0 auto 20px; }
.modal-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:17px; font-weight:800; margin-bottom:14px; }
.warn-box { background:var(--danger-bg); color:#991B1B; border-radius:var(--radius-md); padding:12px 14px; font-size:12px; line-height:1.6; margin-bottom:16px; }
</style>
