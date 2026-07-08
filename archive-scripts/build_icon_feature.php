<?php

function writeFile(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($path)) {
        copy($path, $path . '.bak_' . date('Ymd_His'));
    }
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

// ─────────────────────────────────────────────
// 1. IconController.php (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/Admin/IconController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IconController extends Controller
{
    public const SLOTS = [
        'admin_panel' => 'Ikon shortcut Admin Panel (halaman Akun)',
    ];

    public function index(): Response
    {
        $icons = collect(self::SLOTS)->map(function ($label, $slug) {
            $path = SystemSetting::get("icon:{$slug}");
            return [
                'slug'  => $slug,
                'label' => $label,
                'url'   => $path ? Storage::url($path) : null,
            ];
        })->values();

        return Inertia::render('Admin/Icons', ['icons' => $icons]);
    }

    public function upload(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, self::SLOTS), 404);

        $request->validate([
            'icon' => ['required', 'image', 'max:512', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $old = SystemSetting::get("icon:{$slug}");
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('icon')->store('icons', 'public');

        SystemSetting::set("icon:{$slug}", $path, self::SLOTS[$slug]);

        Cache::forget('app_icons_map');

        return back()->with('success', 'Ikon berhasil diganti!');
    }

    public function reset(string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, self::SLOTS), 404);

        $old = SystemSetting::get("icon:{$slug}");
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        SystemSetting::forget("icon:{$slug}");
        Cache::forget('app_icons_map');

        return back()->with('success', 'Ikon dikembalikan ke default.');
    }
}

EOT
);

// ─────────────────────────────────────────────
// 2. AppIcon.vue (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Components/AppIcon.vue', <<<'EOT'
<template>
  <img v-if="iconUrl" :src="iconUrl" :alt="slug" :class="$attrs.class" />
  <span v-else :class="$attrs.class"><slot /></span>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({ slug: { type: String, required: true } })

const page = usePage()
const iconUrl = computed(() => page.props.icons?.[props.slug] ?? null)
</script>

EOT
);

// ─────────────────────────────────────────────
// 3. Admin/Icons.vue (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/Admin/Icons.vue', <<<'EOT'
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
        <Link :href="route('admin.gateway.index')" class="nav-item" data-label="WA Gateway">
          <span class="ni-icon">📱</span><span v-if="!sc">WA Gateway</span>
        </Link>
        <Link :href="route('admin.cuan-ai-rules')" class="nav-item" data-label="CuanAI Rules">
          <span class="ni-icon">🤖</span><span v-if="!sc">CuanAI Rules</span>
        </Link>
        <Link :href="route('admin.icons')" class="nav-item active" data-label="Icons">
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
            <div class="topbar-title">Icon & Assets</div>
            <div class="topbar-breadcrumb">Admin → Icon & Assets</div>
          </div>
        </div>
      </div>

      <div class="admin-content">
        <div v-if="$page.props.flash?.success" class="flash-success">
          {{ $page.props.flash.success }}
        </div>

        <p class="page-desc">
          Ganti ikon default di aplikasi dengan gambar sendiri. Format: PNG/JPG/SVG/WebP, maks 512KB.
        </p>

        <div class="icon-grid">
          <div v-for="icon in icons" :key="icon.slug" class="icon-card">
            <div class="icon-preview">
              <img v-if="icon.url" :src="icon.url" alt="" />
              <span v-else class="preview-empty">Default</span>
            </div>
            <div class="icon-label">{{ icon.label }}</div>
            <div class="icon-slug">slug: {{ icon.slug }}</div>

            <input
              type="file"
              :ref="el => fileInputs[icon.slug] = el"
              accept="image/png,image/jpeg,image/svg+xml,image/webp"
              style="display:none"
              @change="e => onFileChange(e, icon.slug)"
            />

            <div class="icon-actions">
              <button class="btn-upload" @click="fileInputs[icon.slug]?.click()">📤 Upload</button>
              <button v-if="icon.url" class="btn-reset" @click="resetIcon(icon.slug)">🔄 Reset</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({ icons: Array })

const sc = ref(false)
const fileInputs = reactive({})

function onFileChange(e, slug) {
  const file = e.target.files[0]
  if (!file) return

  const form = new FormData()
  form.append('icon', file)

  router.post(route('admin.icons.upload', slug), form, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['icons'] }),
  })
}

function resetIcon(slug) {
  if (!confirm('Kembalikan ke ikon default?')) return
  router.delete(route('admin.icons.reset', slug), {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['icons'] }),
  })
}
</script>

<style scoped>
.page-desc { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
.icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.icon-card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
.icon-preview { width: 64px; height: 64px; margin: 0 auto 10px; border-radius: 12px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.icon-preview img { width: 100%; height: 100%; object-fit: contain; }
.preview-empty { font-size: 11px; color: #9ca3af; }
.icon-label { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
.icon-slug { font-size: 11px; color: #9ca3af; margin-bottom: 12px; font-family: monospace; }
.icon-actions { display: flex; gap: 6px; justify-content: center; }
.btn-upload { background: #2563eb; color: #fff; border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; }
.btn-reset { background: #f3f4f6; color: #374151; border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; }
.flash-success { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
</style>

EOT
);

// ─────────────────────────────────────────────
// 4. HandleInertiaRequests.php (TIMPA)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Middleware/HandleInertiaRequests.php', <<<'EOT'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
                'subscription' => $request->user()?->subscription?->only('plan', 'status', 'trial_ends_at'),
            ],
            'branding' => [
                'app_name' => $request->user()?->profile?->app_name ?? config('app.name'),
                'app_logo' => $request->user()?->profile?->app_logo_url,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'unread_notifications' => $request->user()
                ?->appNotifications()->where('is_read', false)->count() ?? 0,
            'icons' => Cache::remember('app_icons_map', 300, function () {
                return collect(\App\Http\Controllers\Admin\IconController::SLOTS)->keys()->mapWithKeys(function ($slug) {
                    $path = \App\Models\SystemSetting::get("icon:{$slug}");
                    return [$slug => $path ? \Illuminate\Support\Facades\Storage::url($path) : null];
                })->toArray();
            }),
        ]);
    }
}

EOT
);

// ─────────────────────────────────────────────
// 5. routes/admin.php (TIMPA)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/routes/admin.php', <<<'EOT'
<?php

use App\Http\Controllers\Admin\WaGatewayController;
use App\Http\Controllers\Admin\IconController;
use App\Http\Controllers\Admin\CuanAiRulesController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\BankAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\BrandingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::put('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');

        Route::middleware('super_admin')->group(function () {
            Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');
            Route::get('/packages', [PackageController::class, 'index'])->name('packages');
            Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
            Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
            Route::get('/banks', [BankAdminController::class, 'index'])->name('banks');
            Route::post('/banks', [BankAdminController::class, 'store'])->name('banks.store');
            Route::put('/banks/{bank}', [BankAdminController::class, 'update'])->name('banks.update');
            Route::delete('/banks/{bank}/logo', [BankAdminController::class, 'removeLogo'])->name('banks.remove-logo');
            Route::get('/categories', [CategoryAdminController::class, 'index'])->name('categories');
            Route::post('/categories', [CategoryAdminController::class, 'store'])->name('categories.store');
            Route::delete('/categories/{category}', [CategoryAdminController::class, 'destroy'])->name('categories.destroy');
            Route::put('/branding', [BrandingController::class, 'update'])->name('branding');

            Route::get('/cuan-ai-rules', [CuanAiRulesController::class, 'index'])->name('cuan-ai-rules');
            Route::put('/cuan-ai-rules', [CuanAiRulesController::class, 'update'])->name('cuan-ai-rules.update');
            Route::delete('/cuan-ai-rules', [CuanAiRulesController::class, 'reset'])->name('cuan-ai-rules.reset');

            Route::get('/icons', [IconController::class, 'index'])->name('icons');
            Route::post('/icons/{slug}', [IconController::class, 'upload'])->name('icons.upload');
            Route::delete('/icons/{slug}', [IconController::class, 'reset'])->name('icons.reset');
        });

    });

Route::middleware('super_admin')->prefix('wa-gateway')->name('admin.gateway.')->group(function () {
    Route::get('/',                              [WaGatewayController::class, 'index'])->name('index');
    Route::post('/',                             [WaGatewayController::class, 'store'])->name('store');
    Route::put('/{gateway}',                     [WaGatewayController::class, 'update'])->name('update');
    Route::delete('/{gateway}',                  [WaGatewayController::class, 'destroy'])->name('destroy');
    Route::post('/{gateway}/test',               [WaGatewayController::class, 'test'])->name('test');
    Route::get('/{gateway}/users',               [WaGatewayController::class, 'users'])->name('users');
    Route::post('/reassign',                     [WaGatewayController::class, 'reassign'])->name('reassign');
    Route::post('/release-inactive',             [WaGatewayController::class, 'releaseInactive'])->name('release-inactive');
    Route::post('/recalculate',                  [WaGatewayController::class, 'recalculate'])->name('recalculate');
    Route::post('/owner-number',                 [WaGatewayController::class, 'saveOwnerNumber'])->name('owner-number');
    Route::post('/ping-all',                     [WaGatewayController::class, 'pingAll'])->name('ping-all');
});

EOT
);

// ─────────────────────────────────────────────
// 6. App/Account.vue (TIMPA — versi bersih, tanpa duplikat)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/App/Account.vue', <<<'EOT'
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
.page-header { margin-bottom:12px; }
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

EOT
);

// ─────────────────────────────────────────────
// 7. Sisipkan link "Icon & Assets" ke 3 sidebar admin lain
//    (skip otomatis kalau linknya udah ada)
// ─────────────────────────────────────────────
$navFiles = [
    '/var/www/monexa/resources/js/Pages/Admin/Dashboard.vue',
    '/var/www/monexa/resources/js/Pages/Admin/Users.vue',
    '/var/www/monexa/resources/js/Pages/Admin/WaGateway.vue',
];

$newLink = <<<'LINK'
        <Link :href="route('admin.icons')" class="nav-item" data-label="Icons">
          <span class="ni-icon">🖼️</span><span v-if="!sc">Icon & Assets</span>
        </Link>
LINK;

foreach ($navFiles as $file) {
    if (!file_exists($file)) {
        echo "SKIP (tidak ditemukan): $file\n";
        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, "route('admin.icons')") !== false) {
        echo "SKIP (link sudah ada): $file\n";
        continue;
    }

    if (strpos($content, '</nav>') === false) {
        echo "⚠️  Tidak ketemu </nav> di: $file — tambah manual\n";
        continue;
    }

    copy($file, $file . '.bak_' . date('Ymd_His'));
    $newContent = str_replace('</nav>', $newLink . "\n      </nav>", $content);
    file_put_contents($file, $newContent);

    echo "OK (nav link ditambah): $file\n";
}

echo "\n=== SELESAI ===\n";
