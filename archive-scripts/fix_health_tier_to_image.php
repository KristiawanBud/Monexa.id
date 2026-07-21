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

function patchFile(string $path, array $replacements): void
{
    if (! file_exists($path)) {
        echo "SKIP (tidak ditemukan): $path\n";

        return;
    }
    $content = file_get_contents($path);
    $backupMade = false;
    $changed = 0;
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            if (! $backupMade) {
                copy($path, $path.'.bak_'.date('Ymd_His'));
                $backupMade = true;
            }
            $content = str_replace($old, $new, $content);
            $changed++;
        }
    }
    if ($changed > 0) {
        file_put_contents($path, $content);
        echo "OK ($changed patch): $path\n";
    } else {
        echo "SKIP (pattern tidak ketemu/sudah diterapkan): $path\n";
    }
}

// ─────────────────────────────────────────────
// 1. IconController.php — TIMPA, ganti dari emoji-text jadi 3 slot gambar
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
        'admin_panel'     => 'Ikon shortcut Admin Panel (halaman Akun)',
        'nav_dashboard'   => 'Nav bawah — Dashboard',
        'nav_dompet'      => 'Nav bawah — Dompet',
        'nav_report'      => 'Nav bawah — Laporan',
        'nav_account'     => 'Nav bawah — Profil',
        'qa_pemasukan'    => 'Tambah Transaksi — Pemasukan',
        'qa_pengeluaran'  => 'Tambah Transaksi — Pengeluaran',
        'qa_scan'         => 'Tambah Transaksi — Scan Struk',
        'qa_saving'       => 'Tambah Transaksi — Setor Tabungan',
        'qa_bill'         => 'Tambah Transaksi — Bayar Tagihan',
        'qa_import'       => 'Tambah Transaksi — Import Excel',
        'qa_budget'       => 'Tambah Transaksi — Budget',
        'qa_aset'         => 'Tambah Transaksi — Aset',
        'dashboard_hero'  => 'Ilustrasi Card Utama Dashboard (pojok kanan bawah)',

        // ── Budget Health Score — Icon per tier ──
        'health_tier_sehat'           => 'Skor Kesehatan Budget — Tier Sehat',
        'health_tier_cukup'           => 'Skor Kesehatan Budget — Tier Cukup',
        'health_tier_perlu_perhatian' => 'Skor Kesehatan Budget — Tier Perlu Perhatian',
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
            'icon' => ['required', 'image', 'max:1024', 'mimes:png,jpg,jpeg,svg,webp'],
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
// 2. HandleInertiaRequests.php — TIMPA, hapus health_tier_emojis (nggak perlu lagi)
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
// 3. routes/admin.php — TIMPA, hapus route health-tier-emoji
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/routes/admin.php', <<<'EOT'
<?php

use App\Http\Controllers\Admin\WaGatewayController;
use App\Http\Controllers\Admin\IconController;
use App\Http\Controllers\Admin\CuanAiRulesController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
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
            Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

            Route::get('/subscriptions', [SubscriptionAdminController::class, 'index'])->name('subscriptions');
            Route::put('/subscriptions/{subscription}', [SubscriptionAdminController::class, 'update'])->name('subscriptions.update');

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
// 4. Admin/Icons.vue — TIMPA, balik ke versi generic (hapus section tier-emoji)
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
          Ganti ikon default di aplikasi dengan gambar sendiri. Format: PNG/JPG/SVG/WebP, maks 1MB.
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

.page-desc { font-size: 13px; color: var(--ink-muted); margin-bottom: 20px; }
.icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.icon-card { background: var(--white); border-radius: var(--radius); padding: 16px; box-shadow: var(--shadow); text-align: center; }
.icon-preview { width: 64px; height: 64px; margin: 0 auto 10px; border-radius: 12px; background: var(--off); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.icon-preview img { width: 100%; height: 100%; object-fit: contain; }
.preview-empty { font-size: 11px; color: var(--ink-muted); }
.icon-label { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
.icon-slug { font-size: 11px; color: var(--ink-muted); margin-bottom: 12px; font-family: monospace; }
.icon-actions { display: flex; gap: 6px; justify-content: center; }
.btn-upload { background: var(--ink); color: #fff; border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; }
.btn-reset { background: var(--off); color: var(--ink); border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; }
.flash-success { background: #E9FBEF; color: var(--green-dark); padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
</style>

EOT
);

// ─────────────────────────────────────────────
// 5. Report.vue — patch: pakai <AppIcon> gambar, bukan lookup emoji teks
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Report.vue', [
    "import AppLayout from '@/Layouts/AppLayout.vue'" => "import AppLayout from '@/Layouts/AppLayout.vue'\nimport AppIcon from '@/Components/AppIcon.vue'",

    '{{ $page.props.health_tier_emojis?.[healthStatus] ?? \'\' }} {{ healthLabel(healthStatus) }} · {{ healthScore }}' => '<AppIcon :slug="`health_tier_${healthStatus}`" class="health-tier-icon">{{ defaultTierEmoji(healthStatus) }}</AppIcon>
              {{ healthLabel(healthStatus) }} · {{ healthScore }}',

    "const healthLabel = (s) => ({ sehat: 'Sehat', cukup: 'Cukup', perlu_perhatian: 'Perlu Perhatian' }[s] ?? s)" => "const healthLabel = (s) => ({ sehat: 'Sehat', cukup: 'Cukup', perlu_perhatian: 'Perlu Perhatian' }[s] ?? s)\nconst defaultTierEmoji = (s) => ({ sehat: '✅', cukup: '⚠️', perlu_perhatian: '🔴' }[s] ?? '')",
]);

echo "\n=== SELESAI ===\n";
