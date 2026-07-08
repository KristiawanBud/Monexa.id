<?php

function writeFile(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_exists($path)) copy($path, $path . '.bak_' . date('Ymd_His'));
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

// ─────────────────────────────────────────────
// 1. Migration: packages table
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/database/migrations/2026_07_08_000001_create_packages_table.php', <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('slug', 30)->unique();
            $table->enum('billing_period', ['trial', 'monthly', 'yearly']);
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order')->default(99);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};

EOT
);

// ─────────────────────────────────────────────
// 2. Model Package.php
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Models/Package.php', <<<'EOT'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'slug', 'billing_period', 'price',
        'duration_days', 'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'features'  => 'array',
            'is_active' => 'boolean',
        ];
    }
}

EOT
);

// ─────────────────────────────────────────────
// 3. Seeder PackageSeeder.php
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/database/seeders/PackageSeeder.php', <<<'EOT'
<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'           => 'Trial 7 Hari',
                'slug'           => 'trial',
                'billing_period' => 'trial',
                'price'          => 0,
                'duration_days'  => 7,
                'features'       => ['Semua fitur dasar', 'CuanAI terbatas', 'WA Bot aktif'],
                'sort_order'     => 1,
            ],
            [
                'name'           => 'Bulanan',
                'slug'           => 'monthly',
                'billing_period' => 'monthly',
                'price'          => 29000,
                'duration_days'  => 30,
                'features'       => ['Semua fitur', 'CuanAI unlimited', 'WA Bot aktif', 'Export laporan'],
                'sort_order'     => 2,
            ],
            [
                'name'           => 'Tahunan',
                'slug'           => 'yearly',
                'billing_period' => 'yearly',
                'price'          => 290000,
                'duration_days'  => 365,
                'features'       => ['Semua fitur', 'CuanAI unlimited', 'WA Bot aktif', 'Export laporan', 'Hemat 2 bulan'],
                'sort_order'     => 3,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(['slug' => $pkg['slug']], $pkg);
        }
    }
}

EOT
);

// ─────────────────────────────────────────────
// 4. PackageController.php (TIMPA — dari stub jadi CRUD beneran)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/Admin/PackageController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $packages = Package::orderBy('sort_order')->get();

        return Inertia::render('Admin/Packages', ['packages' => $packages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'slug'           => ['required', 'string', 'max:30', 'alpha_dash', 'unique:packages,slug'],
            'billing_period' => ['required', 'in:trial,monthly,yearly'],
            'price'          => ['required', 'numeric', 'min:0'],
            'duration_days'  => ['nullable', 'integer', 'min:1'],
            'features'       => ['nullable', 'array'],
            'features.*'     => ['string', 'max:150'],
            'sort_order'     => ['nullable', 'integer'],
        ]);

        Package::create($validated);

        return back()->with('success', 'Paket berhasil ditambahkan!');
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'billing_period' => ['required', 'in:trial,monthly,yearly'],
            'price'          => ['required', 'numeric', 'min:0'],
            'duration_days'  => ['nullable', 'integer', 'min:1'],
            'features'       => ['nullable', 'array'],
            'features.*'     => ['string', 'max:150'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['nullable', 'integer'],
        ]);

        $package->update($validated);

        return back()->with('success', "Paket {$package->name} berhasil diupdate!");
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();

        return back()->with('success', 'Paket berhasil dihapus.');
    }
}

EOT
);

// ─────────────────────────────────────────────
// 5. SubscriptionAdminController.php (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/Admin/SubscriptionAdminController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Subscription::with('user:id,name,email')->orderByDesc('created_at');

        if ($request->status) $query->where('status', $request->status);
        if ($request->plan)   $query->where('plan', $request->plan);
        if ($request->search) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            );
        }

        $subscriptions = $query->paginate(30)->through(fn($s) => [
            'id'             => $s->id,
            'user_id'        => $s->user_id,
            'user_name'      => $s->user?->name,
            'user_email'     => $s->user?->email,
            'plan'           => $s->plan,
            'status'         => $s->status,
            'starts_at'      => $s->starts_at?->format('Y-m-d'),
            'ends_at'        => $s->ends_at?->format('Y-m-d'),
            'trial_ends_at'  => $s->trial_ends_at?->format('d M Y'),
            'amount'         => (float) $s->amount,
            'payment_method' => $s->payment_method,
        ]);

        $summary = [
            'total_active'  => Subscription::where('status', 'active')->count(),
            'total_trial'   => Subscription::where('plan', 'trial')->where('status', 'active')->count(),
            'total_paid'    => Subscription::whereIn('plan', ['monthly', 'yearly'])->where('status', 'active')->count(),
            'total_expired' => Subscription::where('status', 'expired')->count(),
        ];

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
            'summary'       => $summary,
            'filters'       => $request->only('status', 'plan', 'search'),
        ]);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'plan'      => ['required', 'in:trial,monthly,yearly'],
            'status'    => ['required', 'in:active,expired,cancelled'],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['nullable', 'date'],
            'amount'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $subscription->update($validated);

        return back()->with('success', "Subscription {$subscription->user?->name} berhasil diupdate!");
    }
}

EOT
);

// ─────────────────────────────────────────────
// 6. routes/admin.php (TIMPA — tambah route packages.destroy + subscriptions)
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
// 7. Admin/Packages.vue (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/Admin/Packages.vue', <<<'EOT'
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
        <Link :href="route('admin.packages')" class="nav-item active" data-label="Paket">
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
            <div class="topbar-title">Paket & Harga 💳</div>
            <div class="topbar-breadcrumb">Admin → Paket & Harga</div>
          </div>
        </div>
        <button class="btn-add" @click="openCreate">+ Tambah Paket</button>
      </div>

      <div class="admin-content">
        <div v-if="$page.props.flash?.success" class="flash-success">
          {{ $page.props.flash.success }}
        </div>

        <div class="package-grid">
          <div v-for="pkg in packages" :key="pkg.id" :class="['package-card', { inactive: !pkg.is_active }]">
            <div class="pc-head">
              <div>
                <div class="pc-name">{{ pkg.name }}</div>
                <div class="pc-slug">{{ pkg.slug }} · {{ pkg.billing_period }}</div>
              </div>
              <span :class="['pc-badge', pkg.is_active ? 'active' : 'inactive']">
                {{ pkg.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <div class="pc-price">Rp {{ formatRupiah(pkg.price) }}</div>
            <div class="pc-duration" v-if="pkg.duration_days">{{ pkg.duration_days }} hari</div>
            <ul class="pc-features">
              <li v-for="(f, i) in (pkg.features || [])" :key="i">✓ {{ f }}</li>
            </ul>
            <div class="pc-actions">
              <button class="btn-edit" @click="openEdit(pkg)">✏️ Edit</button>
              <button class="btn-delete" @click="deletePackage(pkg)">🗑️</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <h3>{{ isEdit ? 'Edit Paket' : 'Tambah Paket Baru' }}</h3>
          <form @submit.prevent="submit">
            <div class="form-group">
              <label>Nama Paket</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="form-group" v-if="!isEdit">
              <label>Slug (unik, tidak bisa diubah nanti)</label>
              <input v-model="form.slug" type="text" required placeholder="misal: monthly-promo" />
            </div>
            <div class="form-group">
              <label>Tipe Billing</label>
              <select v-model="form.billing_period" required>
                <option value="trial">Trial</option>
                <option value="monthly">Bulanan</option>
                <option value="yearly">Tahunan</option>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Harga (Rp)</label>
                <input v-model.number="form.price" type="number" min="0" required />
              </div>
              <div class="form-group">
                <label>Durasi (hari)</label>
                <input v-model.number="form.duration_days" type="number" min="1" />
              </div>
            </div>
            <div class="form-group">
              <label>Fitur (1 baris = 1 fitur)</label>
              <textarea v-model="featuresText" rows="5" placeholder="Semua fitur&#10;CuanAI unlimited&#10;WA Bot aktif"></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Urutan Tampil</label>
                <input v-model.number="form.sort_order" type="number" />
              </div>
              <div class="form-group toggle-group" v-if="isEdit">
                <label>Status</label>
                <label class="toggle-switch">
                  <input type="checkbox" v-model="form.is_active" />
                  <span class="slider"></span>
                </label>
              </div>
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
import { ref, reactive, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({ packages: Array })

const sc = ref(false)
const showModal = ref(false)
const isEdit = ref(false)
const processing = ref(false)
const editingId = ref(null)

const form = reactive({
  name: '', slug: '', billing_period: 'monthly',
  price: 0, duration_days: 30, features: [], sort_order: 99, is_active: true,
})

const featuresText = ref('')

function openCreate() {
  isEdit.value = false
  editingId.value = null
  Object.assign(form, { name: '', slug: '', billing_period: 'monthly', price: 0, duration_days: 30, features: [], sort_order: 99, is_active: true })
  featuresText.value = ''
  showModal.value = true
}

function openEdit(pkg) {
  isEdit.value = true
  editingId.value = pkg.id
  Object.assign(form, {
    name: pkg.name, slug: pkg.slug, billing_period: pkg.billing_period,
    price: pkg.price, duration_days: pkg.duration_days, sort_order: pkg.sort_order,
    is_active: pkg.is_active,
  })
  featuresText.value = (pkg.features || []).join('\n')
  showModal.value = true
}

function submit() {
  processing.value = true
  form.features = featuresText.value.split('\n').map(s => s.trim()).filter(Boolean)

  const action = isEdit.value
    ? router.put(route('admin.packages.update', editingId.value), form, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; showModal.value = false },
      })
    : router.post(route('admin.packages.store'), form, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; showModal.value = false },
      })
}

function deletePackage(pkg) {
  if (!confirm(`Hapus paket "${pkg.name}"?`)) return
  router.delete(route('admin.packages.destroy', pkg.id), { preserveScroll: true })
}

const formatRupiah = (n) => Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
/* ── Admin Shell / Sidebar / Topbar ── */
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

/* ── Package Grid ── */
.package-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px; }
.package-card { background:var(--white);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);border:1.5px solid var(--stone); }
.package-card.inactive { opacity:.55; }
.pc-head { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px; }
.pc-name { font-weight:700;font-size:15px; }
.pc-slug { font-size:11px;color:var(--ink-muted);margin-top:2px; }
.pc-badge { font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px; }
.pc-badge.active { background:var(--amber-light);color:var(--green-dark); }
.pc-badge.inactive { background:var(--stone);color:var(--ink-muted); }
.pc-price { font-family:"Syne",sans-serif;font-size:22px;font-weight:800;margin-bottom:2px; }
.pc-duration { font-size:11px;color:var(--ink-muted);margin-bottom:12px; }
.pc-features { list-style:none;padding:0;margin:0 0 14px;font-size:12px;color:var(--ink-muted);line-height:1.9; }
.pc-actions { display:flex;gap:8px; }
.btn-edit { flex:1;background:var(--off);border:none;padding:8px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer; }
.btn-delete { background:#FDECEC;color:var(--red-dark);border:none;padding:8px 12px;border-radius:8px;cursor:pointer; }

.flash-success { background:#E9FBEF; color:var(--green-dark); padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; }

/* ── Modal ── */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px; }
.modal-box { background:white;border-radius:16px;padding:24px;width:100%;max-width:440px;max-height:90vh;overflow-y:auto; }
.modal-box h3 { margin:0 0 16px;font-size:16px;font-weight:800; }
.form-group { margin-bottom:12px; }
.form-group label { display:block;font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea { width:100%;padding:9px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px;font-family:inherit; }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.toggle-group { display:flex;flex-direction:column;justify-content:center; }
.toggle-switch { position:relative;width:40px;height:22px; }
.toggle-switch input { opacity:0;width:0;height:0; }
.slider { position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--stone);border-radius:99px;transition:.2s; }
.slider:before { position:absolute;content:"";height:16px;width:16px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.2s; }
input:checked + .slider { background:var(--green); }
input:checked + .slider:before { transform:translateX(18px); }
.modal-actions { display:flex;gap:8px;margin-top:16px; }
.btn-cancel { flex:1;background:var(--off);border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
.btn-save { flex:1;background:var(--green);color:white;border:none;padding:11px;border-radius:8px;font-weight:600;cursor:pointer; }
</style>

EOT
);

// ─────────────────────────────────────────────
// 8. Admin/Subscriptions.vue (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/Admin/Subscriptions.vue', <<<'EOT'
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

EOT
);

// ─────────────────────────────────────────────
// 9. Sisip nav link "Paket" + "Subscription" ke 5 sidebar admin lain
// ─────────────────────────────────────────────
$navFiles = [
    '/var/www/monexa/resources/js/Pages/Admin/Dashboard.vue',
    '/var/www/monexa/resources/js/Pages/Admin/Users.vue',
    '/var/www/monexa/resources/js/Pages/Admin/WaGateway.vue',
    '/var/www/monexa/resources/js/Pages/Admin/CuanAiRules.vue',
    '/var/www/monexa/resources/js/Pages/Admin/Icons.vue',
];

$newLinks = <<<'LINK'
        <Link :href="route('admin.packages')" class="nav-item" data-label="Paket">
          <span class="ni-icon">💳</span><span v-if="!sc">Paket & Harga</span>
        </Link>
        <Link :href="route('admin.subscriptions')" class="nav-item" data-label="Subscription">
          <span class="ni-icon">📋</span><span v-if="!sc">Subscription User</span>
        </Link>
LINK;

foreach ($navFiles as $file) {
    if (!file_exists($file)) { echo "SKIP (tidak ditemukan): $file\n"; continue; }

    $content = file_get_contents($file);

    if (strpos($content, "route('admin.packages')") !== false) {
        echo "SKIP (link sudah ada): $file\n";
        continue;
    }
    if (strpos($content, '</nav>') === false) {
        echo "⚠️  Tidak ketemu </nav> di: $file\n";
        continue;
    }

    copy($file, $file . '.bak_' . date('Ymd_His'));
    $newContent = str_replace('</nav>', $newLinks . "\n      </nav>", $content);
    file_put_contents($file, $newContent);

    echo "OK (nav link ditambah): $file\n";
}

echo "\n=== SELESAI MENULIS FILE ===\n";
