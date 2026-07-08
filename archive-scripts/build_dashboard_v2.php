<?php

function writeFile(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_exists($path)) copy($path, $path . '.bak_' . date('Ymd_His'));
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

function patchFile(string $path, array $replacements): void {
    if (!file_exists($path)) { echo "SKIP (tidak ditemukan): $path\n"; return; }
    $content = file_get_contents($path);
    $backupMade = false; $changed = 0;
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            if (!$backupMade) { copy($path, $path . '.bak_' . date('Ymd_His')); $backupMade = true; }
            $content = str_replace($old, $new, $content);
            $changed++;
        }
    }
    if ($changed > 0) { file_put_contents($path, $content); echo "OK ($changed patch): $path\n"; }
    else { echo "SKIP (pattern tidak ketemu/sudah diterapkan): $path\n"; }
}

// ─────────────────────────────────────────────
// 1. Migration: tambah icon_path ke transaction_categories
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/database/migrations/2026_07_08_000003_add_icon_path_to_transaction_categories.php', <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('emoji');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};

EOT
);

// ─────────────────────────────────────────────
// 2. Model TransactionCategory.php — TIMPA, tambah icon_url accessor
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Models/TransactionCategory.php', <<<'EOT'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TransactionCategory extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','type','name','emoji','icon_path','is_system','sort_order'];
    protected function casts(): array { return ['is_system'=>'boolean']; }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class, 'category_id'); }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::url($this->icon_path) : null;
    }

    public static function forUser(?string $userId): \Illuminate\Database\Eloquent\Collection {
        return static::where(function($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })->orderBy('type')->orderBy('sort_order')->get();
    }
}

EOT
);

// ─────────────────────────────────────────────
// 3. CategoryAdminController.php — TIMPA, tambah upload/reset icon
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/Admin/CategoryAdminController.php', <<<'EOT'
<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryAdminController extends Controller
{
    public function index()
    {
        $categories = TransactionCategory::orderBy('type')->orderBy('sort_order')->get()->map(fn($c) => [
            'id'         => $c->id,
            'type'       => $c->type,
            'name'       => $c->name,
            'emoji'      => $c->emoji,
            'icon_url'   => $c->icon_url,
            'is_system'  => $c->is_system,
            'sort_order' => $c->sort_order,
        ]);

        return inertia('Admin/Categories', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'  => 'required|in:income,expense',
            'name'  => 'required|max:50',
            'emoji' => 'nullable|max:10',
        ]);

        TransactionCategory::create([
            'type'       => $request->type,
            'name'       => $request->name,
            'emoji'      => $request->emoji,
            'sort_order' => $request->sort_order ?? 99,
            'is_system'  => false,
        ]);

        return back()->with('success', 'Kategori ditambahkan!');
    }

    public function uploadIcon(Request $request, TransactionCategory $category)
    {
        $request->validate([
            'icon' => ['required', 'image', 'max:512', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }

        $path = $request->file('icon')->store('category-icons', 'public');
        $category->update(['icon_path' => $path]);

        return back()->with('success', "Icon kategori {$category->name} berhasil diganti!");
    }

    public function resetIcon(TransactionCategory $category)
    {
        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }
        $category->update(['icon_path' => null]);

        return back()->with('success', "Icon kategori {$category->name} dikembalikan ke emoji default.");
    }

    public function destroy(TransactionCategory $category)
    {
        if ($category->is_system) {
            return back()->with('error', 'Kategori sistem tidak bisa dihapus.');
        }
        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}

EOT
);

// ─────────────────────────────────────────────
// 4. routes/admin.php — TIMPA, tambah route upload/reset icon kategori
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
            Route::post('/categories/{category}/icon', [CategoryAdminController::class, 'uploadIcon'])->name('categories.icon.upload');
            Route::delete('/categories/{category}/icon', [CategoryAdminController::class, 'resetIcon'])->name('categories.icon.reset');
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
// 5. Admin/Categories.vue (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/Admin/Categories.vue', <<<'EOT'
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

EOT
);

// ─────────────────────────────────────────────
// 6. DashboardController.php — TIMPA, tambah data real (bulan lalu + ringkasan hari ini)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/App/DashboardController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    public function index(Request $request): \Inertia\Response
    {
        $user   = $request->user()->load(['profile', 'wallets.bank']);
        $period = now()->format('Y-m');
        $today  = now()->toDateString();

        $wallets = $user->wallets->where('is_active', true)->values()->map(fn($w) => [
            'id'           => $w->id,
            'display_name' => $w->display_name,
            'balance'      => (float) $w->balance,
            'is_saham'     => $w->is_saham,
            'bank_color'   => $w->bank?->logo_color ?? '#2563EB',
            'bank_initial' => $w->bank?->logo_initial ?? strtoupper(substr($w->display_name, 0, 1)),
        ]);

        $txThisMonth  = $user->transactions()->forPeriod($period)->get();
        $totalIncome  = (float) $txThisMonth->where('type', 'income')->sum('amount');
        $totalExpense = (float) $txThisMonth->where('type', 'expense')->sum('amount');
        $totalBalance = (float) $wallets->sum('balance');

        // ── Perbandingan vs bulan lalu ──
        $prevPeriod     = now()->subMonth()->format('Y-m');
        $prevMonthLabel = now()->subMonth()->translatedFormat('F Y');
        $txPrevMonth    = $user->transactions()->forPeriod($prevPeriod)->get();
        $prevIncome     = (float) $txPrevMonth->where('type', 'income')->sum('amount');
        $prevExpense    = (float) $txPrevMonth->where('type', 'expense')->sum('amount');
        $incomeTrendUp  = $totalIncome >= $prevIncome;
        $expenseTrendUp = $totalExpense >= $prevExpense;

        $totalSaving = (float) \DB::table('saving_deposits')
            ->join('saving_goals', 'saving_deposits.saving_goal_id', '=', 'saving_goals.id')
            ->where('saving_goals.user_id', $user->id)
            ->whereMonth('deposited_at', now()->month)
            ->whereYear('deposited_at', now()->year)
            ->sum('amount');

        // ── Ringkasan Hari Ini ──
        $txToday           = $user->transactions()->whereDate('transacted_at', $today)->get();
        $todayIncomeAmount = (float) $txToday->where('type', 'income')->sum('amount');
        $todayIncomeCount  = $txToday->where('type', 'income')->count();
        $todayExpense      = (float) $txToday->where('type', 'expense')->sum('amount');
        $todayExpenseCount = $txToday->where('type', 'expense')->count();

        $totalBudget = (float) \DB::table('budgets')
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->sum('amount');

        $sisaHari     = now()->daysInMonth - now()->day + 1;
        $budgetHarian = $totalBudget > 0 && $sisaHari > 0
            ? round(($totalBudget - $totalExpense) / $sisaHari)
            : null;

        $budgetPct = $totalBudget > 0
            ? min(100, round(($totalExpense / $totalBudget) * 100))
            : null;

        $recentTransactions = $user->transactions()
            ->with(['wallet:id,display_name', 'category:id,name,emoji,icon_path'])
            ->orderByDesc('transacted_at')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'type'             => $t->type,
                'amount'           => (float) $t->amount,
                'note'             => $t->note,
                'category'         => $t->category?->name,
                'category_emoji'   => $t->category?->emoji,
                'category_icon_url'=> $t->category?->icon_url,
                'wallet'           => $t->wallet?->display_name,
                'transacted_at'    => $t->transacted_at->format('d M'),
                'source'           => $t->source,
            ]);

        $topCategories = $txThisMonth
            ->where('type', 'expense')
            ->groupBy(fn($t) => $t->category?->name ?? 'Lainnya')
            ->map(fn($txs) => [
                'name'  => $txs->first()->category?->name ?? 'Lainnya',
                'emoji' => $txs->first()->category?->emoji ?? '✨',
                'total' => (float) $txs->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $topCategoriesSum = $topCategories->sum('total');
        $topCategories = $topCategories->map(fn($c) => [
            ...$c,
            'percent' => $topCategoriesSum > 0 ? round(($c['total'] / $topCategoriesSum) * 100) : 0,
        ]);

        $notifications = $user->appNotifications()
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($n) {
                $createdAt = $n->created_at instanceof \Carbon\Carbon
                    ? $n->created_at
                    : \Carbon\Carbon::parse($n->created_at);

                return [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'body'       => $n->body,
                    'created_at' => $createdAt->diffForHumans(),
                ];
            });

        $upcomingBills = $user->bills()
            ->where('is_active', true)
            ->get()
            ->filter(fn($b) => $b->days_until_due !== null && $b->days_until_due >= 0 && $b->days_until_due <= 7)
            ->values()
            ->map(fn($b) => [
                'name'           => $b->name,
                'emoji'          => $b->emoji,
                'amount'         => (float) $b->amount,
                'days_until_due' => $b->days_until_due,
            ]);

        $goals = $user->savingGoals()
            ->where('status', 'active')
            ->limit(5)
            ->get()
            ->map(fn($g) => [
                'id'      => $g->id,
                'name'    => $g->name,
                'percent' => $g->target_amount > 0
                    ? min(100, round(($g->current_amount / $g->target_amount) * 100))
                    : 0,
            ]);

        return Inertia::render('App/Dashboard', [
            'wallets'              => $wallets,
            'totalBalance'         => $totalBalance,
            'totalIncome'          => $totalIncome,
            'totalExpense'         => $totalExpense,
            'totalSaving'          => $totalSaving,
            'todayExpense'         => $todayExpense,
            'todayExpenseCount'    => $todayExpenseCount,
            'todayIncomeAmount'    => $todayIncomeAmount,
            'todayIncomeCount'     => $todayIncomeCount,
            'prevMonthLabel'       => $prevMonthLabel,
            'incomeTrendUp'        => $incomeTrendUp,
            'expenseTrendUp'       => $expenseTrendUp,
            'budgetHarian'         => $budgetHarian,
            'budgetPct'            => $budgetPct,
            'totalBudget'          => $totalBudget,
            'recentTransactions'   => $recentTransactions,
            'topCategories'        => $topCategories,
            'notifications'        => $notifications,
            'upcomingBills'        => $upcomingBills,
            'goals'                => $goals,
            'period'               => now()->translatedFormat('F Y'),
            'today'                => now()->translatedFormat('l, d F Y'),
            'greeting'             => $this->getGreeting(),
            'hide_balance'         => $user->profile?->hide_balance ?? false,
        ]);
    }

    public function toggleHideBalance(Request $request)
    {
        $user    = $request->user();
        $profile = $user->profile;

        if ($profile) {
            $profile->update(['hide_balance' => !$profile->hide_balance]);
            $hidden = $profile->hide_balance;
        } else {
            \App\Models\UserProfile::create([
                'user_id'      => $user->id,
                'hide_balance' => true,
            ]);
            $hidden = true;
        }

        return response()->json(['hidden' => $hidden]);
    }

    private function getGreeting(): string
    {
        $hour = now('Asia/Jakarta')->hour;
        if ($hour < 12) return 'Selamat Pagi';
        if ($hour < 15) return 'Selamat Siang';
        if ($hour < 18) return 'Selamat Sore';
        return 'Selamat Malam';
    }
}

EOT
);

// ─────────────────────────────────────────────
// 7. App/Dashboard.vue — TIMPA total, redesign sesuai mockup
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/App/Dashboard.vue', <<<'EOT'
<template>
  <AppLayout>
    <div class="dash-hero-bg">
      <div class="dash-header">
        <div class="greeting-block">
          <div class="greeting-label">👋 Selamat {{ greetingTime }},</div>
          <div class="greeting-name">{{ firstName }}</div>
          <div class="greeting-sub">Semoga keuanganmu hari ini sehat selalu!</div>
        </div>
        <div class="header-actions">
          <button class="icon-btn-round" @click="showNotif = true">
            🔔
            <span v-if="notifications.length > 0" class="notif-badge">{{ notifications.length }}</span>
          </button>
          <Link :href="route('account')" class="avatar-btn">{{ initials }}</Link>
        </div>
      </div>

      <div class="hero-card">
        <AppIcon slug="dashboard_hero" class="hero-illustration">💼</AppIcon>

        <div class="hero-top">
          <div class="hero-label">TOTAL ASET BERSIH</div>
          <button class="hide-btn" @click="toggleBalance" :aria-label="balanceHidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'">
            <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </button>
        </div>

        <div class="hero-amount">
          <span v-if="!balanceHidden">{{ formatRupiah(totalBalance) }}</span>
          <span v-else class="hidden-text">••••••••••</span>
        </div>

        <div class="hero-compare" v-if="!balanceHidden">
          <span :class="['trend', incomeTrendUp ? 'up' : 'down']">{{ incomeTrendUp ? '↑' : '↓' }} {{ formatShort(totalIncome) }}</span>
          <span class="sep">|</span>
          <span :class="['trend', expenseTrendUp ? 'down' : 'up']">{{ expenseTrendUp ? '↓' : '↑' }} {{ formatShort(totalExpense) }}</span>
          <span class="vs-label">vs {{ prevMonthLabel }}</span>
        </div>

        <div class="hero-progress" v-if="totalBudget > 0">
          <div class="progress-label-row">
            <span>PROGRESS BULAN INI</span>
            <span class="progress-pct">{{ budgetPct ?? 0 }}%</span>
          </div>
          <div class="progress-bar-bg">
            <div class="progress-bar-fill" :style="`width:${Math.min(budgetPct ?? 0, 100)}%`"></div>
          </div>
          <div class="progress-sub">Budget digunakan: Rp {{ formatShort(totalExpense) }} / Rp {{ formatShort(totalBudget) }}</div>
        </div>
      </div>
    </div>

    <div class="page-content">

      <!-- Quick Actions -->
      <div class="qa-row">
        <Link :href="route('dompet.index')" :data="{ tab: 'in' }" class="qa-btn">
          <div class="qa-icon pemasukan"><AppIcon slug="qa_pemasukan">💵</AppIcon></div>
          <span>Pemasukan</span>
        </Link>
        <Link :href="route('dompet.index')" :data="{ tab: 'out' }" class="qa-btn">
          <div class="qa-icon pengeluaran"><AppIcon slug="qa_pengeluaran">🔥</AppIcon></div>
          <span>Pengeluaran</span>
        </Link>
        <Link :href="route('receipt.index')" class="qa-btn">
          <div class="qa-icon scan"><AppIcon slug="qa_scan">📷</AppIcon></div>
          <span>Scan Struk</span>
        </Link>
        <Link :href="route('asset.index')" class="qa-btn">
          <div class="qa-icon aset"><AppIcon slug="qa_aset">💎</AppIcon></div>
          <span>Aset</span>
        </Link>
      </div>

      <!-- Ringkasan Hari Ini -->
      <div class="section">
        <div class="card today-card">
          <div class="tc-head">
            <span class="section-title">RINGKASAN HARI INI</span>
            <Link :href="route('dompet.index')" class="see-all">Lihat semua →</Link>
          </div>
          <div class="tc-grid">
            <div class="tc-item">
              <div class="tc-icon up-bg">📈</div>
              <div class="tc-info">
                <div class="tc-label">Pemasukan</div>
                <div class="tc-value">Rp {{ formatShort(todayIncomeAmount) }}</div>
                <div class="tc-count">{{ todayIncomeCount }} transaksi</div>
              </div>
            </div>
            <div class="tc-item">
              <div class="tc-icon down-bg">📉</div>
              <div class="tc-info">
                <div class="tc-label">Pengeluaran</div>
                <div class="tc-value">Rp {{ formatShort(todayExpense) }}</div>
                <div class="tc-count">{{ todayExpenseCount }} transaksi</div>
              </div>
            </div>
            <div class="tc-item">
              <div class="tc-icon budget-bg">🥧</div>
              <div class="tc-info">
                <div class="tc-label">Budget Terpakai</div>
                <div class="tc-value">{{ budgetPct ?? 0 }}%</div>
                <div class="tc-count">Rp {{ formatShort(totalExpense) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Goals preview -->
      <div v-if="goals && goals.length > 0" class="section">
        <div class="section-head">
          <span class="section-title">🎯 Goals Tabungan</span>
          <Link :href="route('saving.index')" class="see-all">Lihat semua →</Link>
        </div>
        <div class="goal-scroll">
          <div v-for="g in goals" :key="g.id" class="goal-chip">
            <div class="goal-name">{{ g.name }}</div>
            <div class="goal-bar">
              <div class="goal-fill" :style="`width:${g.percent}%`"></div>
            </div>
            <div class="goal-pct">{{ g.percent }}%</div>
          </div>
        </div>
      </div>

      <!-- Upcoming bills -->
      <div v-if="upcomingBills.length > 0" class="section">
        <div class="section-head">
          <span class="section-title">📋 Tagihan Minggu Ini</span>
        </div>
        <div class="bill-list">
          <div v-for="bill in upcomingBills" :key="bill.name"
            :class="['bill-chip', bill.days_until_due === 0 ? 'today' : bill.days_until_due <= 3 ? 'soon' : '']">
            <span class="bc-emoji">{{ bill.emoji || '📋' }}</span>
            <span class="bc-name">{{ bill.name }}</span>
            <span class="bc-due">{{ bill.days_until_due === 0 ? 'Hari ini!' : `H-${bill.days_until_due}` }}</span>
            <span class="bc-amt">{{ formatShort(bill.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Transaksi Terbaru -->
      <div class="section">
        <div class="section-head">
          <span class="section-title">Transaksi Terbaru</span>
        </div>
        <div class="card tx-card">
          <div v-if="recentTransactions.length === 0" class="empty-state">
            <div class="empty-illust">📝</div>
            <div class="empty-text">Belum ada transaksi</div>
            <div class="empty-sub">Mulai catat transaksi pertamamu!</div>
            <Link :href="route('dompet.index')" class="btn-primary" style="margin-top:14px;max-width:200px;">
              + Catat Sekarang
            </Link>
          </div>

          <div v-for="tx in recentTransactions" :key="tx.id" class="tx-item">
            <div class="tx-icon" :style="`background:${tx.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)'}`">
              <img v-if="tx.category_icon_url" :src="tx.category_icon_url" class="tx-icon-img" alt="" />
              <span v-else>{{ tx.category_emoji || (tx.type === 'income' ? '💵' : '🛍️') }}</span>
            </div>
            <div class="tx-info">
              <div class="tx-name">{{ tx.note || tx.category || 'Transaksi' }}</div>
              <div class="tx-cat">{{ tx.category }} · {{ tx.wallet }}</div>
            </div>
            <div class="tx-right">
              <div :class="['tx-amt', tx.type === 'income' ? 'up' : 'down']">
                {{ tx.type === 'income' ? '+' : '−' }}{{ formatShort(tx.amount) }}
              </div>
              <div class="tx-time">{{ tx.transacted_at }}</div>
            </div>
          </div>

          <div v-if="recentTransactions.length > 0" class="see-all-row">
            <Link :href="route('dompet.index')" class="see-all">Lihat semua →</Link>
          </div>
        </div>
      </div>

      <!-- Kategori Pengeluaran Bulan Ini -->
      <div class="section" v-if="topCategories.length > 0">
        <div class="section-head">
          <span class="section-title">Kategori Pengeluaran</span>
        </div>
        <div class="card">
          <div v-for="cat in topCategories" :key="cat.name" class="cat-row">
            <div class="cat-emoji">{{ cat.emoji }}</div>
            <div class="cat-info">
              <div class="cat-name-row">
                <span class="cat-name">{{ cat.name }}</span>
                <span class="cat-amt">Rp {{ formatShort(cat.total) }}</span>
              </div>
              <div class="cat-bar-bg">
                <div class="cat-bar-fill" :style="`width:${cat.percent}%`"></div>
              </div>
            </div>
            <div class="cat-pct">{{ cat.percent }}%</div>
          </div>
          <div class="see-all-row">
            <Link :href="route('report')" class="see-all">Lihat laporan lengkap →</Link>
          </div>
        </div>
      </div>

    </div>

    <!-- Notification Panel -->
    <Teleport to="body">
      <div v-if="showNotif" class="notif-overlay" @click="showNotif = false"></div>
      <div :class="['notif-panel', { active: showNotif }]">
        <div class="notif-header">
          <h2>Notifikasi 🔔</h2>
          <button class="notif-close" @click="showNotif = false">✕</button>
        </div>
        <div v-if="notifications.length === 0" class="empty-state" style="padding:24px;">
          <div class="empty-illust">✅</div>
          <div class="empty-text">Tidak ada notifikasi baru</div>
        </div>
        <div v-for="notif in notifications" :key="notif.id" class="notif-item">
          <div class="notif-icon">🔔</div>
          <div class="notif-body">
            <div class="notif-title">{{ notif.title }}</div>
            <div class="notif-desc">{{ notif.body }}</div>
            <div class="notif-time">{{ notif.created_at }}</div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'

const props = defineProps({
  totalBalance: Number, totalIncome: Number, totalExpense: Number,
  todayExpense: Number, todayExpenseCount: Number,
  todayIncomeAmount: Number, todayIncomeCount: Number,
  prevMonthLabel: String, incomeTrendUp: Boolean, expenseTrendUp: Boolean,
  budgetHarian: Number, budgetPct: Number, totalBudget: Number,
  recentTransactions: Array, notifications: Array, upcomingBills: Array,
  goals: { type: Array, default: () => [] },
  topCategories: { type: Array, default: () => [] },
  period: String, hide_balance: Boolean,
})

const page = usePage()
const showNotif = ref(false)
const balanceHidden = ref(props.hide_balance)

const firstName = computed(() => page.props.auth.user?.name?.split(' ')[0] ?? '')
const initials = computed(() => {
  const name = page.props.auth.user?.name ?? ''
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})

const greetingTime = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return 'Pagi'
  if (h < 15) return 'Siang'
  if (h < 18) return 'Sore'
  return 'Malam'
})

const toggleBalance = async () => {
  balanceHidden.value = !balanceHidden.value
  try {
    await fetch(route('dashboard.toggle-balance'), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Content-Type': 'application/json',
      },
    })
  } catch {}
}

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000_000) return (n/1_000_000_000).toFixed(1) + 'M'
  if (n >= 1_000_000)     return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)         return (n/1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.dash-hero-bg {
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  padding: 20px 20px 0;
  position: relative;
  overflow: hidden;
}
.dash-hero-bg::before {
  content: '';
  position: absolute;
  top: -60px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
}
.dash-hero-bg::after {
  content: '';
  position: absolute;
  top: 60px; right: 90px;
  width: 90px; height: 90px;
  border-radius: 50%;
  background: rgba(255,255,255,.05);
}

.dash-header { display:flex; justify-content:space-between; align-items:flex-start; position:relative; z-index:2; margin-bottom:16px; }
.greeting-label { font-size:13px; color:rgba(255,255,255,.85); font-weight:500; }
.greeting-name { font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; font-weight:800; color:white; letter-spacing:-.02em; margin-top:2px; }
.greeting-sub { font-size:12px; color:rgba(255,255,255,.7); margin-top:4px; }
.header-actions { display:flex; gap:10px; align-items:center; flex-shrink:0; }
.icon-btn-round { position:relative; width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.18); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; }
.notif-badge { position:absolute; top:-3px; right:-3px; background:var(--danger); color:white; font-size:9px; font-weight:800; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--primary); }
.avatar-btn { width:38px; height:38px; border-radius:50%; background:white; color:var(--primary-dark); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; text-decoration:none; }

.hero-card {
  position: relative; z-index: 2; overflow: hidden;
  background: white; border-radius: 22px 22px 0 0;
  padding: 22px 20px 20px;
  box-shadow: 0 -4px 20px rgba(0,0,0,.04);
}
.hero-illustration { position:absolute; right:16px; top:18px; width:64px; height:64px; opacity:.95; pointer-events:none; }
.hero-top { display:flex; justify-content:space-between; align-items:center; }
.hero-label { font-size:11px; font-weight:700; letter-spacing:.06em; color:var(--text-secondary); }
.hide-btn { background:var(--background); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); }
.hide-btn svg { width:16px; height:16px; }
.hero-amount { font-family:'Plus Jakarta Sans',sans-serif; font-size:30px; font-weight:800; letter-spacing:-.02em; margin:6px 0 8px; color:var(--text-primary); }
.hidden-text { letter-spacing:.1em; color:var(--text-faint); font-size:24px; }

.hero-compare { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px; font-weight:700; padding-bottom:14px; border-bottom:1px solid var(--border); margin-bottom:14px; }
.hero-compare .trend.up { color: var(--success); }
.hero-compare .trend.down { color: var(--danger); }
.hero-compare .sep { color: var(--border); font-weight:400; }
.hero-compare .vs-label { font-size:11px; color:var(--text-secondary); font-weight:500; margin-left:auto; }

.hero-progress { }
.progress-label-row { display:flex; justify-content:space-between; font-size:10px; font-weight:700; letter-spacing:.05em; color:var(--text-secondary); margin-bottom:6px; }
.progress-pct { color:var(--primary); }
.progress-bar-bg { height:8px; background:var(--background); border-radius:99px; overflow:hidden; }
.progress-bar-fill { height:100%; background:linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius:99px; transition:width .5s; }
.progress-sub { font-size:11px; color:var(--text-secondary); margin-top:6px; }

.page-content { padding: 16px 20px 0; }

/* Quick actions */
.qa-row { display:flex; gap:8px; margin-bottom:18px; }
.qa-btn { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; background:var(--surface); border-radius:var(--radius-lg); padding:14px 6px; box-shadow:var(--shadow-card); text-decoration:none; color:var(--text-primary); font-size:10.5px; font-weight:600; transition:all .15s; }
.qa-btn:active { transform:scale(.95); }
.qa-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; }
.qa-icon.pemasukan   { background:var(--success-bg); }
.qa-icon.pengeluaran { background:var(--danger-bg); }
.qa-icon.scan        { background:var(--primary-bg); }
.qa-icon.aset         { background:var(--amber-bg); }

/* Today summary */
.today-card { padding:16px 18px; }
.tc-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
.tc-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:14px; }
.tc-item { display:flex; align-items:center; gap:10px; }
.tc-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.tc-icon.up-bg { background:var(--success-bg); }
.tc-icon.down-bg { background:var(--danger-bg); }
.tc-icon.budget-bg { background:var(--amber-bg); }
.tc-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.tc-value { font-size:13px; font-weight:800; }
.tc-count { font-size:10px; color:var(--text-faint); }

/* Section */
.section { margin-bottom:18px; }
.section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.section-title { font-size:13px; font-weight:700; }
.see-all { font-size:12px; color:var(--primary); text-decoration:none; font-weight:600; }
.see-all-row { text-align:center; padding-top:10px; border-top:1px solid var(--border); margin-top:4px; }

/* Goals */
.goal-scroll { display:flex; gap:10px; overflow-x:auto; scrollbar-width:none; }
.goal-chip { flex-shrink:0; min-width:140px; background:var(--surface); border-radius:var(--radius-lg); padding:14px; box-shadow:var(--shadow-card); }
.goal-name { font-size:12px; font-weight:600; margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.goal-bar { height:6px; background:var(--background); border-radius:99px; overflow:hidden; margin-bottom:4px; }
.goal-fill { height:100%; background:var(--secondary); border-radius:99px; }
.goal-pct { font-size:11px; color:var(--text-secondary); font-weight:600; }

/* Bills */
.bill-list { display:flex; flex-direction:column; gap:8px; }
.bill-chip { display:flex; align-items:center; gap:10px; background:var(--surface); border-radius:var(--radius-md); padding:12px 14px; box-shadow:var(--shadow-card); border-left:3px solid var(--success); }
.bill-chip.today { border-left-color:var(--danger); }
.bill-chip.soon  { border-left-color:var(--amber); }
.bc-emoji { font-size:18px; }
.bc-name { flex:1; font-size:13px; font-weight:500; }
.bc-due { font-size:11px; font-weight:700; color:var(--success); }
.bill-chip.today .bc-due { color:var(--danger); }
.bill-chip.soon .bc-due { color:var(--amber); }
.bc-amt { font-family:'Plus Jakarta Sans',sans-serif; font-size:12px; font-weight:800; }

/* Transactions */
.tx-card { padding:8px 16px; }
.tx-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
.tx-item:last-of-type { border-bottom:none; }
.tx-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; overflow:hidden; }
.tx-icon-img { width:100%; height:100%; object-fit:contain; }
.tx-info { flex:1; min-width:0; }
.tx-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tx-cat { font-size:11px; color:var(--text-secondary); }
.tx-right { text-align:right; flex-shrink:0; }
.tx-amt { font-size:13px; font-weight:700; }
.tx-amt.up { color:var(--success); }
.tx-amt.down { color:var(--danger); }
.tx-time { font-size:10px; color:var(--text-faint); }

.empty-state { text-align:center; padding:24px 0; }
.empty-illust { font-size:36px; margin-bottom:8px; }
.empty-text { font-size:14px; font-weight:600; margin-bottom:2px; }
.empty-sub { font-size:12px; color:var(--text-secondary); }

/* Kategori Pengeluaran */
.cat-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.cat-row:last-of-type { border-bottom: none; }
.cat-emoji { font-size: 20px; width: 32px; text-align: center; flex-shrink: 0; }
.cat-info { flex: 1; min-width: 0; }
.cat-name-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
.cat-name { font-weight: 600; color: var(--text-primary); }
.cat-amt { color: var(--text-secondary); }
.cat-bar-bg { height: 6px; background: var(--background); border-radius: 99px; overflow: hidden; }
.cat-bar-fill { height: 100%; background: var(--primary); border-radius: 99px; }
.cat-pct { font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 36px; text-align: right; flex-shrink: 0; }

/* Notif panel */
.notif-overlay { position:fixed; inset:0; background:rgba(15,23,42,.4); z-index:400; backdrop-filter:blur(3px); }
.notif-panel { position:fixed; top:0; right:0; width:100%; max-width:480px; height:100vh; background:var(--surface); z-index:401; transform:translateX(100%); transition:transform .3s ease; overflow-y:auto; }
.notif-panel.active { transform:translateX(0); }
.notif-header { padding:20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--surface); }
.notif-header h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; font-weight:800; }
.notif-close { font-size:18px; background:var(--background); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; }
.notif-item { display:flex; gap:12px; padding:14px 20px; border-bottom:1px solid var(--border); background:var(--primary-bg); }
.notif-icon { font-size:20px; flex-shrink:0; }
.notif-title { font-size:13px; font-weight:600; margin-bottom:2px; }
.notif-desc { font-size:12px; color:var(--text-secondary); line-height:1.5; }
.notif-time { font-size:10px; color:var(--text-faint); margin-top:4px; }
</style>

EOT
);

echo "\n=== SELESAI MENULIS FILE ===\n";
