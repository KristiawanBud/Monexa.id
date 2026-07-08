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
// 1. IconController.php — tambah slot 'dompet_hero'
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/app/Http/Controllers/Admin/IconController.php', [
    "'dashboard_hero'  => 'Ilustrasi Card Utama Dashboard (pojok kanan bawah)'," =>
    "'dashboard_hero'  => 'Ilustrasi Card Utama Dashboard (pojok kanan bawah)',\n        'dompet_hero'     => 'Ilustrasi Card Utama Halaman Dompet',",
]);

// ─────────────────────────────────────────────
// 2. TransactionController.php — TIMPA, tambah range filter + breakdown saldo
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/App/TransactionController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionEditLog;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();

        $range = in_array($request->range, ['today', 'week', 'month']) ? $request->range : 'today';

        $query = $user->transactions()
            ->with(['wallet:id,display_name', 'category:id,name,emoji,type,icon_path'])
            ->orderByDesc('transacted_at')
            ->orderByDesc('created_at');

        if ($request->period) {
            $query->forPeriod($request->period);
        } else {
            match ($range) {
                'week'  => $query->whereBetween('transacted_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->forPeriod(now()->format('Y-m')),
                default => $query->whereDate('transacted_at', now()->toDateString()),
            };
        }

        if ($request->wallet_id) $query->where('wallet_id', $request->wallet_id);
        if ($request->type)      $query->where('type', $request->type);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->search) {
            $query->where('note', 'like', '%' . $request->search . '%');
        }
        if ($request->min_amount) $query->where('amount', '>=', $request->min_amount);
        if ($request->max_amount) $query->where('amount', '<=', $request->max_amount);

        $transactions = $query->paginate(30)->through(fn($t) => [
            'id'                  => $t->id,
            'type'                => $t->type,
            'amount'              => (float) $t->amount,
            'note'                => $t->note,
            'category'            => $t->category?->name,
            'category_emoji'      => $t->category?->emoji,
            'category_icon_url'   => $t->category?->icon_path ? Storage::url($t->category->icon_path) : null,
            'wallet'              => $t->wallet?->display_name,
            'wallet_id'           => $t->wallet_id,
            'category_id'         => $t->category_id,
            'transacted_at'       => $t->transacted_at->format('Y-m-d'),
            'transacted_at_label' => $t->transacted_at->translatedFormat('d M Y'),
            'transacted_at_time'  => $t->created_at?->format('H:i'),
            'source'              => $t->source,
        ]);

        $period = $request->period ?? now()->format('Y-m');

        // ── Ringkasan sesuai range yang dipilih (Masuk / Keluar) ──
        $rangeSummaryQuery = $user->transactions();
        match ($range) {
            'week'  => $rangeSummaryQuery->whereBetween('transacted_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $rangeSummaryQuery->forPeriod(now()->format('Y-m')),
            default => $rangeSummaryQuery->whereDate('transacted_at', now()->toDateString()),
        };
        $rangeSummary = $rangeSummaryQuery->get();
        $rangeIncome  = (float) $rangeSummary->where('type', 'income')->sum('amount');
        $rangeExpense = (float) $rangeSummary->where('type', 'expense')->sum('amount');

        $rangeLabel = match ($range) {
            'week'  => 'Minggu Ini',
            'month' => 'Bulan Ini',
            default => 'Hari Ini',
        };

        // Dompet (wallets) dengan saldo — untuk tab "Dompet"
        $walletsRaw = $user->wallets()
            ->with('bank:id,short_name,logo_color,logo_initial,type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $wallets = $walletsRaw->map(fn($w) => [
            'id'             => $w->id,
            'display_name'   => $w->display_name,
            'account_number' => $w->account_number,
            'type'           => $w->type,
            'balance'        => (float) $w->balance,
            'is_saham'       => $w->is_saham,
            'bank_id'        => $w->bank_id,
            'bank_name'      => $w->bank?->short_name,
            'bank_color'     => $w->bank?->logo_color ?? '#2563EB',
            'bank_initial'   => $w->bank?->logo_initial ?? strtoupper(substr($w->display_name, 0, 1)),
            'logo_url'       => $w->bank?->logo_url ? Storage::url($w->bank->logo_url) : null,
        ]);

        // ── Breakdown saldo: Cash / Bank / E-Wallet ──
        $cashTotal    = (float) $walletsRaw->filter(fn($w) => !$w->bank_id)->sum('balance');
        $ewalletTotal = (float) $walletsRaw->filter(fn($w) => $w->bank?->type === 'digital')->sum('balance');
        $bankTotal    = (float) $walletsRaw->filter(fn($w) => $w->bank_id && $w->bank?->type !== 'digital')->sum('balance');

        // Tagihan aktif — untuk tab "Tagihan"
        $bills = $user->bills()
            ->where('is_active', true)
            ->orderBy('type')
            ->get()
            ->map(fn($b) => [
                'id'                 => $b->id,
                'name'               => $b->name,
                'emoji'              => $b->emoji,
                'type'               => $b->type,
                'amount'             => (float) $b->amount,
                'due_day'            => $b->due_day,
                'due_date'           => $b->due_date?->format('Y-m-d'),
                'remind_days'        => $b->remind_days,
                'days_until_due'     => $b->days_until_due,
                'status_color'       => $b->status_color,
                'last_paid_at'       => $b->last_paid_at?->format('d M Y'),
                'is_paid_this_month' => $b->last_paid_at?->isCurrentMonth() ?? false,
            ]);

        return Inertia::render('App/Dompet', [
            'transactions'         => $transactions,
            'wallets'              => $wallets,
            'bills'                => $bills,
            'banks'                => \App\Models\Bank::where('is_active', true)->orderBy('sort_order')->get(),
            'categories'           => TransactionCategory::forUser($user->id),
            'period'               => $period,
            'range'                => $range,
            'range_label'          => $rangeLabel,
            'total_income'         => $rangeIncome,
            'total_expense'        => $rangeExpense,
            'total_balance'        => (float) $wallets->sum('balance'),
            'active_wallets_count' => $wallets->count(),
            'cash_total'           => $cashTotal,
            'bank_total'           => $bankTotal,
            'ewallet_total'        => $ewalletTotal,
            'active_tab'           => $request->input('tab', 'transaksi'),
            'search_query'         => $request->search,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'          => 'required|in:income,expense',
            'amount'        => 'required|numeric|min:1',
            'wallet_id'     => 'required|exists:user_wallets,id',
            'category_id'   => 'nullable|exists:transaction_categories,id',
            'note'          => 'nullable|string|max:255',
            'transacted_at' => 'required|date',
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                $transaction = $user->transactions()->create([
                    'wallet_id'     => $request->wallet_id,
                    'category_id'   => $request->category_id,
                    'type'          => $request->type,
                    'amount'        => $request->amount,
                    'note'          => $request->note,
                    'transacted_at' => $request->transacted_at,
                    'source'        => 'manual',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                TransactionEditLog::create([
                    'transaction_id' => $transaction->id,
                    'edited_by'      => $user->id,
                    'action'         => 'create',
                    'old_data'       => [],
                    'new_data'       => $transaction->toArray(),
                    'edited_at'      => now(),
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi berhasil disimpan!');
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $request->validate([
            'type'          => 'required|in:income,expense',
            'amount'        => 'required|numeric|min:1',
            'wallet_id'     => 'required|exists:user_wallets,id',
            'category_id'   => 'nullable|exists:transaction_categories,id',
            'note'          => 'nullable|string|max:255',
            'transacted_at' => 'required|date',
        ]);

        $user = $request->user();
        $oldData = $transaction->toArray();

        try {
            DB::transaction(function () use ($request, $transaction, $user, $oldData) {
                $this->walletService->reverseTransaction($transaction);

                $transaction->update([
                    'wallet_id'     => $request->wallet_id,
                    'category_id'   => $request->category_id,
                    'type'          => $request->type,
                    'amount'        => $request->amount,
                    'note'          => $request->note,
                    'transacted_at' => $request->transacted_at,
                ]);

                $transaction->refresh();

                $this->walletService->applyTransaction($transaction);

                TransactionEditLog::create([
                    'transaction_id' => $transaction->id,
                    'edited_by'      => $user->id,
                    'action'         => 'update',
                    'old_data'       => $oldData,
                    'new_data'       => $transaction->toArray(),
                    'edited_at'      => now(),
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi berhasil diupdate!');
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $user = $request->user();

        DB::transaction(function () use ($transaction, $user) {
            $this->walletService->reverseTransaction($transaction);

            TransactionEditLog::create([
                'transaction_id' => $transaction->id,
                'edited_by'      => $user->id,
                'action'         => 'delete',
                'old_data'       => $transaction->toArray(),
                'new_data'       => [],
                'edited_at'      => now(),
            ]);

            $transaction->delete();
        });

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    public function editLogs(Transaction $transaction)
    {
        return response()->json(
            TransactionEditLog::where('transaction_id', $transaction->id)
                ->orderByDesc('created_at')
                ->get()
        );
    }
}

EOT
);

// ─────────────────────────────────────────────
// 3. Dompet.vue — patch header/hero + summary tab Transaksi + script + CSS
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Dompet.vue', [

    // ── A. Header lama -> Hero baru + Breakdown card ──
    '<!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Dompet 👛</h1>
        <button class="add-icon-btn" @click="openAddForTab">＋</button>
      </div>

      <!-- Page hint -->
      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">
          Kelola dompet, catat transaksi, dan bayar tagihan — semua dalam satu tempat.
        </span>
      </div>' =>
    '<!-- Hero -->
      <div class="dompet-hero-bg">
        <div class="hero-top-row">
          <div>
            <h1 class="hero-page-title">Dompet 👛</h1>
            <div class="hero-page-sub">Kelola semua rekening dan uangmu di sini.</div>
          </div>
          <button class="hero-add-btn" @click="openAddForTab">＋</button>
        </div>

        <AppIcon slug="dompet_hero" class="dompet-hero-illustration">👛</AppIcon>

        <div class="hero-saldo-row">
          <span class="hero-saldo-label">TOTAL SALDO</span>
          <button class="hero-eye-btn" @click="balanceHidden = !balanceHidden">
            <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </button>
        </div>
        <div class="hero-saldo-amount">
          <span v-if="!balanceHidden">{{ formatRupiah(total_balance) }}</span>
          <span v-else class="hidden-text">••••••••••</span>
        </div>
        <div class="hero-wallet-badge">● {{ active_wallets_count }} Dompet Aktif</div>
      </div>

      <!-- Breakdown Saldo -->
      <div class="card breakdown-card">
        <div class="breakdown-item">
          <div class="bd-icon cash">💵</div>
          <div class="bd-info">
            <div class="bd-label">Saldo Cash</div>
            <div class="bd-value cash">{{ formatShort(cash_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill cash" :style="`width:${total_balance ? Math.min(100, (cash_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
        <div class="breakdown-item">
          <div class="bd-icon bank">🏦</div>
          <div class="bd-info">
            <div class="bd-label">Saldo Bank</div>
            <div class="bd-value bank">{{ formatShort(bank_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill bank" :style="`width:${total_balance ? Math.min(100, (bank_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
        <div class="breakdown-item">
          <div class="bd-icon ewallet">👛</div>
          <div class="bd-info">
            <div class="bd-label">E-Wallet</div>
            <div class="bd-value ewallet">{{ formatShort(ewallet_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill ewallet" :style="`width:${total_balance ? Math.min(100, (ewallet_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
      </div>',

    // ── B. Summary lama di tab Transaksi -> Range filter + search ──
    '<!-- Summary -->
        <div class="summary-row">
          <div class="summary-item">
            <div class="summary-label">↑ Total Masuk</div>
            <div class="summary-val up">{{ formatRupiah(total_income) }}</div>
          </div>
          <div class="summary-item">
            <div class="summary-label">↓ Total Keluar</div>
            <div class="summary-val down">{{ formatRupiah(total_expense) }}</div>
          </div>
        </div>' =>
    '<!-- Filter periode + ringkasan -->
        <div class="range-filter-row">
          <div class="range-dropdown">
            <button class="range-btn" @click="showRangeMenu = !showRangeMenu">
              📅 {{ range_label }} <span class="range-caret">▾</span>
            </button>
            <div v-if="showRangeMenu" class="range-menu">
              <button @click="changeRange(\'today\')">Hari Ini</button>
              <button @click="changeRange(\'week\')">Minggu Ini</button>
              <button @click="changeRange(\'month\')">Bulan Ini</button>
            </div>
          </div>
          <div class="range-stat">
            <span class="rs-label">↓ Masuk</span>
            <span class="rs-val up">{{ formatShort(total_income) }}</span>
          </div>
          <div class="range-stat">
            <span class="rs-label">↑ Keluar</span>
            <span class="rs-val down">{{ formatShort(total_expense) }}</span>
          </div>
          <div class="range-stat">
            <span class="rs-label">Saldo</span>
            <span class="rs-val">{{ formatShort(total_balance) }}</span>
          </div>
        </div>

        <!-- Search + Filter -->
        <div class="search-row">
          <div class="search-box">
            <span class="search-icon">🔍</span>
            <input v-model="searchQuery" @keyup.enter="applySearch" type="text" placeholder="Cari transaksi..." />
          </div>
          <button class="filter-btn" @click="alert(\'Filter lanjutan segera hadir\')">▤ Filter</button>
        </div>

        <div class="tx-list-heading">
          <span class="section-title">Transaksi {{ range_label }}</span>
        </div>',

    // ── C. Tambah AppIcon import ──
    "import AppLayout from '@/Layouts/AppLayout.vue'
import EmojiPicker from '@/Components/EmojiPicker.vue'" =>
    "import AppLayout from '@/Layouts/AppLayout.vue'
import EmojiPicker from '@/Components/EmojiPicker.vue'
import AppIcon from '@/Components/AppIcon.vue'",

    // ── D. Tambah props baru ──
    'const props = defineProps({
  transactions: Object,
  wallets: Array,
  bills: Array,
  banks: Array,
  categories: Array,
  period: String,
  total_income: Number,
  total_expense: Number,
  total_balance: Number,
  active_tab: { type: String, default: \'transaksi\' },
})' =>
    'const props = defineProps({
  transactions: Object,
  wallets: Array,
  bills: Array,
  banks: Array,
  categories: Array,
  period: String,
  range: { type: String, default: \'today\' },
  range_label: { type: String, default: \'Hari Ini\' },
  total_income: Number,
  total_expense: Number,
  total_balance: Number,
  active_wallets_count: { type: Number, default: 0 },
  cash_total: { type: Number, default: 0 },
  bank_total: { type: Number, default: 0 },
  ewallet_total: { type: Number, default: 0 },
  search_query: String,
  active_tab: { type: String, default: \'transaksi\' },
})

const balanceHidden = ref(false)
const showRangeMenu = ref(false)
const searchQuery = ref(props.search_query || \'\')

function changeRange(newRange) {
  showRangeMenu.value = false
  router.get(route(\'dompet.index\'), { range: newRange, tab: \'transaksi\' }, {
    preserveState: true, preserveScroll: true,
  })
}

function applySearch() {
  router.get(route(\'dompet.index\'), { range: props.range, search: searchQuery.value, tab: \'transaksi\' }, {
    preserveState: true, preserveScroll: true,
  })
}',

    // ── E. CSS baru ──
    '.page-content { padding: 20px; }' =>
    '.page-content { padding: 20px; }

.dompet-hero-bg {
  position: relative; overflow: hidden;
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  margin: -20px -20px 0; padding: 20px 20px 24px;
  border-radius: 0 0 26px 26px;
}
.hero-top-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; position:relative; z-index:2; }
.hero-page-title { font-family:\'Plus Jakarta Sans\',sans-serif; font-size:22px; font-weight:800; color:white; }
.hero-page-sub { font-size:12px; color:rgba(255,255,255,.75); margin-top:4px; }
.hero-add-btn { width:44px; height:44px; border-radius:50%; background:white; color:var(--primary); border:none; font-size:20px; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,.15); flex-shrink:0; }
.dompet-hero-illustration { position:absolute; right:14px; top:50px; width:80px; height:80px; opacity:.95; pointer-events:none; z-index:1; }
.hero-saldo-row { display:flex; align-items:center; gap:8px; position:relative; z-index:2; }
.hero-saldo-label { font-size:11px; font-weight:700; letter-spacing:.06em; color:rgba(255,255,255,.8); }
.hero-eye-btn { background:rgba(255,255,255,.18); border:none; border-radius:50%; width:26px; height:26px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:white; }
.hero-eye-btn svg { width:14px; height:14px; }
.hero-saldo-amount { font-family:\'Plus Jakarta Sans\',sans-serif; font-size:28px; font-weight:800; color:white; margin:4px 0 10px; position:relative; z-index:2; }
.hidden-text { letter-spacing:.1em; color:rgba(255,255,255,.6); }
.hero-wallet-badge { display:inline-block; background:rgba(255,255,255,.18); color:white; font-size:11px; font-weight:600; padding:5px 12px; border-radius:99px; position:relative; z-index:2; }

.breakdown-card { display:flex; gap:14px; margin: -14px 0 16px; padding:16px; position:relative; z-index:3; }
.breakdown-item { flex:1; display:flex; gap:8px; align-items:flex-start; }
.bd-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.bd-icon.cash { background:var(--success-bg); }
.bd-icon.bank { background:var(--primary-bg); }
.bd-icon.ewallet { background:#F3E8FF; }
.bd-info { flex:1; min-width:0; }
.bd-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.bd-value { font-size:13px; font-weight:800; margin:2px 0 4px; }
.bd-value.cash { color:var(--success); }
.bd-value.bank { color:var(--primary); }
.bd-value.ewallet { color:#9333EA; }
.bd-bar-bg { height:4px; background:var(--background); border-radius:99px; overflow:hidden; }
.bd-bar-fill { height:100%; border-radius:99px; }
.bd-bar-fill.cash { background:var(--success); }
.bd-bar-fill.bank { background:var(--primary); }
.bd-bar-fill.ewallet { background:#9333EA; }

.range-filter-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:var(--surface); border-radius:var(--radius-lg); padding:12px 14px; box-shadow:var(--shadow-card); margin-bottom:12px; }
.range-dropdown { position:relative; }
.range-btn { background:var(--primary-bg); color:var(--primary); border:none; padding:8px 12px; border-radius:10px; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:4px; }
.range-menu { position:absolute; top:110%; left:0; background:var(--surface); border-radius:10px; box-shadow:var(--shadow-lg); z-index:50; overflow:hidden; min-width:130px; }
.range-menu button { display:block; width:100%; text-align:left; padding:10px 14px; background:none; border:none; font-size:12px; cursor:pointer; color:var(--text-primary); }
.range-menu button:hover { background:var(--background); }
.range-stat { display:flex; flex-direction:column; }
.rs-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.rs-val { font-size:13px; font-weight:800; }
.rs-val.up { color:var(--success); }
.rs-val.down { color:var(--danger); }

.search-row { display:flex; gap:8px; margin-bottom:16px; }
.search-box { flex:1; display:flex; align-items:center; gap:8px; background:var(--surface); border-radius:var(--radius-md); padding:10px 14px; box-shadow:var(--shadow-card); }
.search-box input { border:none; outline:none; background:none; font-size:13px; flex:1; font-family:inherit; }
.search-icon { font-size:14px; opacity:.6; }
.filter-btn { background:var(--surface); border:none; padding:10px 16px; border-radius:var(--radius-md); font-size:12px; font-weight:700; box-shadow:var(--shadow-card); cursor:pointer; white-space:nowrap; }

.tx-list-heading { margin-bottom:10px; }' ,
]);

echo "\n=== SELESAI ===\n";
