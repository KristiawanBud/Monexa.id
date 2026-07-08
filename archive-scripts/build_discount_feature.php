<?php

function writeFile(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (file_exists($path)) copy($path, $path . '.bak_' . date('Ymd_His'));
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

// ─────────────────────────────────────────────
// 1. Migration: tambah kolom diskon ke packages
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/database/migrations/2026_07_08_000002_add_discount_to_packages_table.php', <<<'EOT'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('discount_type', ['percent', 'fixed'])->nullable()->after('price');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->string('discount_label', 60)->nullable()->after('discount_value');
            $table->timestamp('discount_starts_at')->nullable()->after('discount_label');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_label', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};

EOT
);

// ─────────────────────────────────────────────
// 2. Model Package.php (TIMPA — tambah accessor diskon & harga final)
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
        'discount_type', 'discount_value', 'discount_label',
        'discount_starts_at', 'discount_ends_at',
    ];

    protected $appends = ['has_active_discount', 'final_price'];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'discount_value'     => 'decimal:2',
            'features'           => 'array',
            'is_active'          => 'boolean',
            'discount_starts_at' => 'datetime',
            'discount_ends_at'   => 'datetime',
        ];
    }

    // ── Apakah diskonnya sedang aktif (cek periode kalau diisi) ──
    public function getHasActiveDiscountAttribute(): bool
    {
        if (!$this->discount_type || !$this->discount_value) {
            return false;
        }

        $now = now();

        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) {
            return false;
        }
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) {
            return false;
        }

        return true;
    }

    // ── Harga setelah dipotong diskon (kalau aktif) ──
    public function getFinalPriceAttribute(): float
    {
        if (!$this->has_active_discount) {
            return (float) $this->price;
        }

        $discountAmount = $this->discount_type === 'percent'
            ? (float) $this->price * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return max(0, (float) $this->price - $discountAmount);
    }
}

EOT
);

// ─────────────────────────────────────────────
// 3. PackageController.php (TIMPA — validasi field diskon)
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

            'discount_type'      => ['nullable', 'in:percent,fixed'],
            'discount_value'     => ['nullable', 'numeric', 'min:0'],
            'discount_label'     => ['nullable', 'string', 'max:60'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at'   => ['nullable', 'date', 'after_or_equal:discount_starts_at'],
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

            'discount_type'      => ['nullable', 'in:percent,fixed'],
            'discount_value'     => ['nullable', 'numeric', 'min:0'],
            'discount_label'     => ['nullable', 'string', 'max:60'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at'   => ['nullable', 'date', 'after_or_equal:discount_starts_at'],
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
// 4. Admin/Packages.vue (TIMPA — tambah UI diskon)
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
            <div v-if="pkg.has_active_discount" class="pc-ribbon">🔥 {{ pkg.discount_label || 'Promo' }}</div>

            <div class="pc-head">
              <div>
                <div class="pc-name">{{ pkg.name }}</div>
                <div class="pc-slug">{{ pkg.slug }} · {{ pkg.billing_period }}</div>
              </div>
              <span :class="['pc-badge', pkg.is_active ? 'active' : 'inactive']">
                {{ pkg.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>

            <div class="pc-price-wrap">
              <div v-if="pkg.has_active_discount" class="pc-price-old">Rp {{ formatRupiah(pkg.price) }}</div>
              <div class="pc-price">Rp {{ formatRupiah(pkg.final_price) }}</div>
              <div v-if="pkg.has_active_discount" class="pc-save-badge">
                {{ pkg.discount_type === 'percent' ? `-${pkg.discount_value}%` : `-Rp ${formatRupiah(pkg.discount_value)}` }}
              </div>
            </div>

            <div class="pc-duration" v-if="pkg.duration_days">{{ pkg.duration_days }} hari</div>

            <div v-if="pkg.discount_ends_at" class="pc-promo-period">
              ⏰ Promo s/d {{ formatDate(pkg.discount_ends_at) }}
            </div>

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
                <label>Harga Normal (Rp)</label>
                <input v-model.number="form.price" type="number" min="0" required />
              </div>
              <div class="form-group">
                <label>Durasi (hari)</label>
                <input v-model.number="form.duration_days" type="number" min="1" />
              </div>
            </div>

            <div class="discount-box">
              <div class="discount-box-title">🏷️ Diskon / Promo (opsional)</div>
              <div class="form-row">
                <div class="form-group">
                  <label>Tipe Diskon</label>
                  <select v-model="form.discount_type">
                    <option :value="null">Tidak ada diskon</option>
                    <option value="percent">Persen (%)</option>
                    <option value="fixed">Nominal (Rp)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Nilai Diskon</label>
                  <input v-model.number="form.discount_value" type="number" min="0" :disabled="!form.discount_type" />
                </div>
              </div>
              <div class="form-group" v-if="form.discount_type">
                <label>Label Promo (tampil di badge)</label>
                <input v-model="form.discount_label" type="text" placeholder="misal: Promo Kemerdekaan, Hemat 20%" />
              </div>
              <div class="form-row" v-if="form.discount_type">
                <div class="form-group">
                  <label>Mulai (opsional)</label>
                  <input v-model="form.discount_starts_at" type="datetime-local" />
                </div>
                <div class="form-group">
                  <label>Berakhir (opsional)</label>
                  <input v-model="form.discount_ends_at" type="datetime-local" />
                </div>
              </div>
              <div class="discount-hint" v-if="form.discount_type">
                Kosongkan tanggal kalau mau promo aktif terus sampai kamu matikan manual.
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
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({ packages: Array })

const sc = ref(false)
const showModal = ref(false)
const isEdit = ref(false)
const processing = ref(false)
const editingId = ref(null)

const emptyForm = () => ({
  name: '', slug: '', billing_period: 'monthly',
  price: 0, duration_days: 30, features: [], sort_order: 99, is_active: true,
  discount_type: null, discount_value: null, discount_label: '',
  discount_starts_at: '', discount_ends_at: '',
})

const form = reactive(emptyForm())
const featuresText = ref('')

function openCreate() {
  isEdit.value = false
  editingId.value = null
  Object.assign(form, emptyForm())
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
    discount_type: pkg.discount_type,
    discount_value: pkg.discount_value,
    discount_label: pkg.discount_label || '',
    discount_starts_at: toLocalInput(pkg.discount_starts_at),
    discount_ends_at: toLocalInput(pkg.discount_ends_at),
  })
  featuresText.value = (pkg.features || []).join('\n')
  showModal.value = true
}

function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function submit() {
  processing.value = true
  form.features = featuresText.value.split('\n').map(s => s.trim()).filter(Boolean)

  if (!form.discount_type) {
    form.discount_value = null
    form.discount_label = ''
    form.discount_starts_at = ''
    form.discount_ends_at = ''
  }

  if (isEdit.value) {
    router.put(route('admin.packages.update', editingId.value), form, {
      preserveScroll: true,
      onFinish: () => { processing.value = false; showModal.value = false },
    })
  } else {
    router.post(route('admin.packages.store'), form, {
      preserveScroll: true,
      onFinish: () => { processing.value = false; showModal.value = false },
    })
  }
}

function deletePackage(pkg) {
  if (!confirm(`Hapus paket "${pkg.name}"?`)) return
  router.delete(route('admin.packages.destroy', pkg.id), { preserveScroll: true })
}

const formatRupiah = (n) => Number(n || 0).toLocaleString('id-ID')
const formatDate = (iso) => new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
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

.package-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px; }
.package-card { position:relative;background:var(--white);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);border:1.5px solid var(--stone);overflow:hidden; }
.package-card.inactive { opacity:.55; }
.pc-ribbon { position:absolute;top:12px;right:-30px;background:var(--red);color:white;font-size:10px;font-weight:800;padding:4px 34px;transform:rotate(35deg);box-shadow:0 2px 6px rgba(0,0,0,.15); }
.pc-head { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px; }
.pc-name { font-weight:700;font-size:15px; }
.pc-slug { font-size:11px;color:var(--ink-muted);margin-top:2px; }
.pc-badge { font-size:10px;font-weight:700;padding:3px 8px;border-radius:99px; }
.pc-badge.active { background:var(--amber-light);color:var(--green-dark); }
.pc-badge.inactive { background:var(--stone);color:var(--ink-muted); }

.pc-price-wrap { display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:2px; }
.pc-price-old { font-size:13px;color:var(--ink-muted);text-decoration:line-through; }
.pc-price { font-family:"Syne",sans-serif;font-size:22px;font-weight:800; }
.pc-save-badge { background:#E9FBEF;color:var(--green-dark);font-size:10px;font-weight:800;padding:2px 8px;border-radius:99px; }
.pc-duration { font-size:11px;color:var(--ink-muted);margin-bottom:6px; }
.pc-promo-period { font-size:11px;color:var(--red-dark);font-weight:600;margin-bottom:12px; }

.pc-features { list-style:none;padding:0;margin:0 0 14px;font-size:12px;color:var(--ink-muted);line-height:1.9; }
.pc-actions { display:flex;gap:8px; }
.btn-edit { flex:1;background:var(--off);border:none;padding:8px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer; }
.btn-delete { background:#FDECEC;color:var(--red-dark);border:none;padding:8px 12px;border-radius:8px;cursor:pointer; }

.flash-success { background:#E9FBEF; color:var(--green-dark); padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; }

.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px; }
.modal-box { background:white;border-radius:16px;padding:24px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto; }
.modal-box h3 { margin:0 0 16px;font-size:16px;font-weight:800; }
.form-group { margin-bottom:12px; }
.form-group label { display:block;font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea { width:100%;padding:9px 12px;border:1px solid var(--stone);border-radius:8px;font-size:13px;font-family:inherit; }
.form-group input:disabled { background:var(--off);color:var(--ink-muted); }
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

.discount-box { background:#FFFBEB;border:1.5px dashed var(--amber);border-radius:10px;padding:12px;margin-bottom:14px; }
.discount-box-title { font-size:12px;font-weight:700;margin-bottom:10px;color:#92600A; }
.discount-hint { font-size:11px;color:var(--ink-muted);margin-top:2px; }
</style>

EOT
);

echo "\n=== SELESAI MENULIS FILE ===\n";
