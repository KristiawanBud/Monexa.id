<?php

namespace App\Services;

use App\Models\User;
use App\Models\AiChatSession;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\SystemSetting;
use App\Models\TransactionCategory;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;

class CuanAiService
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService
    ) {}

    // ── Chat dengan CuanAI ────────────────────────
    public function chat(User $user, string $userMessage, AiChatSession $session, $previousLastMessageAt = null): array
    {
        $context = $this->buildFinancialContext($user);
        $history = $session->getLastNMessages(10);

        $actionNote  = $this->detectAndExecuteAction($user, $userMessage, $context);
        $actionTaken = $actionNote !== null && str_starts_with($actionNote, 'BERHASIL');

        $effectiveMessage = $actionNote
            ? "{$userMessage}\n\n[STATUS SISTEM — HASIL AKSI: {$actionNote} — sampaikan status ini ke user secara natural dan jujur, jangan mengarang hasil lain]"
            : $userMessage;

        // ── Tentukan boleh/nggaknya AI menyapa "Halo" dsb ──
        $allowGreeting = is_null($previousLastMessageAt)
            || \Carbon\Carbon::parse($previousLastMessageAt)->diffInMinutes(now()) >= 180;

        $greetingInstruction = $allowGreeting
            ? "Ini pesan pertama di sesi ini atau sudah lama tidak chat — boleh pakai sapaan pembuka (misal \"Halo!\") di awal balasan."
            : "User masih dalam sesi chat yang sama dengan pesan sebelumnya — JANGAN pakai sapaan pembuka seperti \"Halo\" lagi, langsung ke inti jawaban.";

        $context = $this->buildFinancialContext($user);
        $prompt  = $this->buildPrompt($context, $history, $effectiveMessage, $greetingInstruction);

        $result = $this->gemini->generate($prompt, 500);

        if (!$result['success']) {
            return [
                'reply'        => 'Maaf, CuanAI sedang tidak tersedia. Coba lagi sebentar ya! 🙏',
                'action_taken' => $actionTaken,
            ];
        }

        return [
            'reply'        => $result['text'],
            'action_taken' => $actionTaken,
        ];
    }

    // ────────────────────────────────────────────────
    // DETEKSI + EKSEKUSI AKSI
    // ────────────────────────────────────────────────
    private function detectAndExecuteAction(User $user, string $userMessage, array $ctx): ?string
    {
        $waContext = [
            'wallets'    => collect($ctx['wallets'])->pluck('display_name')->toArray(),
            'categories' => TransactionCategory::forUser($user->id)->pluck('name')->toArray(),
        ];

        $parsed = $this->gemini->parseWaMessage($userMessage, $waContext);
        $intent = $parsed['intent'] ?? 'unknown';

        return match ($intent) {
            'add_income'   => $this->executeAddTransaction($user, $parsed, 'income'),
            'add_expense'  => $this->executeAddTransaction($user, $parsed, 'expense'),
            'add_multiple' => $this->executeMultipleTransactions($user, $parsed),
            'pay_bill'     => $this->executePayBill($user, $parsed),
            'add_wallet'   => $this->executeAddWallet($user, $parsed),
            default        => null,
        };
    }

    // ── Eksekusi: Catat Pemasukan / Pengeluaran ───
    private function executeAddTransaction(User $user, array $parsed, string $type): string
    {
        $amount = $parsed['amount'] ?? null;

        if (!$amount || $amount <= 0) {
            return "GAGAL — nominal tidak jelas/tidak disebutkan user. Minta user sebutkan nominalnya dengan jelas.";
        }

        $wallet = $this->resolveWallet($user, $parsed['wallet'] ?? null);
        if (!$wallet) {
            return "GAGAL — user belum punya dompet aktif. Sarankan user tambah dompet dulu.";
        }

        $category     = $this->resolveCategory($user, $parsed['category'] ?? null, $type);
        $transactedAt = $parsed['date'] ?? now()->toDateString();

        try {
            DB::transaction(function () use ($user, $wallet, $category, $type, $amount, $parsed, $transactedAt, &$transaction) {
                $transaction = $user->transactions()->create([
                    'wallet_id'     => $wallet->id,
                    'category_id'   => $category?->id,
                    'type'          => $type,
                    'amount'        => $amount,
                    'note'          => $parsed['note'] ?? null,
                    'transacted_at' => $transactedAt,
                    'source'        => 'cuanai_chat',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);
            });
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return "GAGAL — {$e->getMessage()}";
        }

        $typeLabel  = $type === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $newBalance = number_format($wallet->fresh()->balance, 0, ',', '.');

        return "BERHASIL mencatat {$typeLabel} sebesar Rp " . number_format($amount, 0, ',', '.')
            . " ke dompet {$wallet->display_name}"
            . ($category ? ", kategori {$category->name}" : '')
            . ". Saldo {$wallet->display_name} sekarang Rp {$newBalance}.";
    }

    // ── Eksekusi: Transaksi GANDA (lebih dari 1 dalam 1 pesan) ──
    private function executeMultipleTransactions(User $user, array $parsed): string
    {
        $items = $parsed['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return "GAGAL — sistem mendeteksi ada beberapa transaksi tapi tidak bisa memisahkan detailnya. Minta user sebutkan tiap transaksi & nominalnya lebih jelas.";
        }

        $sharedWallet = $parsed['wallet'] ?? null;
        $sharedDate   = $parsed['date'] ?? null;

        $results      = [];
        $successCount = 0;

        foreach ($items as $item) {
            $type = ($item['type'] ?? 'expense') === 'income' ? 'income' : 'expense';

            $itemParsed = [
                'amount'   => $item['amount'] ?? null,
                'category' => $item['category'] ?? null,
                'note'     => $item['note'] ?? null,
                'wallet'   => $item['wallet'] ?? $sharedWallet,
                'date'     => $sharedDate,
            ];

            $itemResult = $this->executeAddTransaction($user, $itemParsed, $type);
            $results[]  = $itemResult;

            if (str_starts_with($itemResult, 'BERHASIL')) {
                $successCount++;
            }
        }

        $totalCount = count($items);
        $summary    = implode(' || ', $results);

        $status = $successCount > 0 ? 'BERHASIL' : 'GAGAL';

        return "{$status} memproses {$successCount} dari {$totalCount} transaksi terpisah yang diminta user. Rincian tiap transaksi: {$summary}";
    }

    // ── Eksekusi: Bayar Tagihan ────────────────────
    private function executePayBill(User $user, array $parsed): string
    {
        $billName = $parsed['bill_name'] ?? null;

        if (!$billName) {
            return "GAGAL — user tidak menyebutkan nama tagihan. Minta user sebutkan nama tagihannya.";
        }

        $bill = $user->bills()
            ->where('is_active', true)
            ->where('name', 'like', "%{$billName}%")
            ->first();

        if (!$bill) {
            return $this->executePayBillAsExpense($user, $parsed, $billName);
        }

        $forPeriod = now()->format('Y-m');

        $alreadyPaid = BillPayment::where('bill_id', $bill->id)
            ->where('for_period', $forPeriod)
            ->exists();

        if ($alreadyPaid) {
            return "INFO — tagihan \"{$bill->name}\" untuk bulan ini SUDAH LUNAS sebelumnya, jangan dicatat ulang.";
        }

        $wallet = $this->resolveWallet($user, $parsed['wallet'] ?? null);
        if (!$wallet) {
            return "GAGAL — user belum punya dompet aktif untuk membayar tagihan ini.";
        }

        $tagCategory = TransactionCategory::where('type', 'expense')
            ->where('is_system', true)
            ->whereRaw('LOWER(name) = ?', ['tagihan'])
            ->first();

        try {
            DB::transaction(function () use ($user, $bill, $wallet, $tagCategory, $forPeriod) {
                $transaction = $user->transactions()->create([
                    'wallet_id'     => $wallet->id,
                    'category_id'   => $tagCategory?->id,
                    'type'          => 'expense',
                    'amount'        => $bill->amount,
                    'note'          => 'Bayar tagihan: ' . $bill->name,
                    'transacted_at' => now(),
                    'source'        => 'cuanai_chat',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                BillPayment::create([
                    'bill_id'        => $bill->id,
                    'wallet_id'      => $wallet->id,
                    'transaction_id' => $transaction->id,
                    'amount_paid'    => $bill->amount,
                    'paid_at'        => now(),
                    'source'         => 'cuanai_chat',
                    'for_period'     => $forPeriod,
                ]);

                $bill->update(['last_paid_at' => now()]);
            });
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return "GAGAL — {$e->getMessage()}";
        }

        return "BERHASIL menandai tagihan \"{$bill->name}\" lunas sebesar Rp "
            . number_format($bill->amount, 0, ',', '.') . " dari dompet {$wallet->display_name}.";
    }

    // ── Eksekusi: Bayar "Tagihan" yang TIDAK terdaftar formal ──
    private function executePayBillAsExpense(User $user, array $parsed, string $billName): string
    {
        $amount = $parsed['amount'] ?? null;

        if (!$amount || $amount <= 0) {
            return "GAGAL — tagihan \"{$billName}\" tidak terdaftar sebagai tagihan rutin di aplikasi, dan user tidak menyebutkan nominal pembayarannya. Minta user sebutkan nominalnya, misal 'Bayar {$billName} 200rb'.";
        }

        $wallet = $this->resolveWallet($user, $parsed['wallet'] ?? null);
        if (!$wallet) {
            return "GAGAL — user belum punya dompet aktif.";
        }

        $tagCategory = TransactionCategory::where('type', 'expense')
            ->where('is_system', true)
            ->whereRaw('LOWER(name) = ?', ['tagihan'])
            ->first();

        try {
            DB::transaction(function () use ($user, $wallet, $tagCategory, $amount, $billName, &$transaction) {
                $transaction = $user->transactions()->create([
                    'wallet_id'     => $wallet->id,
                    'category_id'   => $tagCategory?->id,
                    'type'          => 'expense',
                    'amount'        => $amount,
                    'note'          => 'Bayar tagihan: ' . $billName,
                    'transacted_at' => now(),
                    'source'        => 'cuanai_chat',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);
            });
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return "GAGAL — {$e->getMessage()}";
        }

        $newBalance = number_format($wallet->fresh()->balance, 0, ',', '.');

        return "BERHASIL mencatat pengeluaran \"{$billName}\" sebesar Rp " . number_format($amount, 0, ',', '.')
            . " dari dompet {$wallet->display_name} (dicatat sebagai transaksi kategori Tagihan — tagihan ini belum terdaftar sebagai tagihan rutin di menu Tagihan aplikasi). Saldo {$wallet->display_name} sekarang Rp {$newBalance}.";
    }

    // ── Eksekusi: Tambah Dompet Baru ───────────────
    private function executeAddWallet(User $user, array $parsed): string
    {
        $walletName = trim($parsed['wallet_name'] ?? '');

        if (!$walletName) {
            return "GAGAL — user tidak menyebutkan nama dompet yang mau dibuat. Minta user sebutkan namanya, misal 'BCA' atau 'Dompet Belanja'.";
        }

        $exists = $user->wallets()
            ->where('is_active', true)
            ->where('display_name', 'like', $walletName)
            ->exists();

        if ($exists) {
            return "INFO — user sudah punya dompet bernama \"{$walletName}\". Jangan dibuatkan lagi, tanya apa mau pakai dompet yang sudah ada.";
        }

        $bank = Bank::where('is_active', true)
            ->where(function ($q) use ($walletName) {
                $q->where('name', 'like', "%{$walletName}%")
                  ->orWhere('short_name', 'like', "%{$walletName}%");
            })
            ->first();

        $allowedTypes = ['cash_flow', 'saving', 'both', 'investment'];
        $type = in_array($parsed['wallet_type'] ?? null, $allowedTypes)
            ? $parsed['wallet_type']
            : 'cash_flow';

        $initialBalance = (float) ($parsed['initial_balance'] ?? 0);
        $lastOrder      = $user->wallets()->max('sort_order') ?? 0;

        $wallet = $user->wallets()->create([
            'bank_id'         => $bank?->id,
            'display_name'    => $walletName,
            'account_number'  => null,
            'balance'         => $initialBalance,
            'initial_balance' => $initialBalance,
            'type'            => $type,
            'is_saham'        => false,
            'is_active'       => true,
            'sort_order'      => $lastOrder + 1,
        ]);

        return "BERHASIL membuat dompet baru \"{$wallet->display_name}\" dengan saldo awal Rp "
            . number_format($initialBalance, 0, ',', '.') . ".";
    }

    // ── PRIVATE HELPERS ────────────────────────────
    private function resolveWallet(User $user, ?string $walletName): ?UserWallet
    {
        $query = $user->wallets()->where('is_active', true);

        if ($walletName) {
            $byName = (clone $query)->where('display_name', 'like', "%{$walletName}%")->first();
            if ($byName) return $byName;
        }

        return $query->orderBy('sort_order')->first();
    }

    private function resolveCategory(User $user, ?string $categoryName, string $type): ?TransactionCategory
    {
        $categories = TransactionCategory::forUser($user->id)->where('type', $type);

        if ($categoryName) {
            $needle = mb_strtolower(trim($categoryName));

            $byName = $categories->first(function ($cat) use ($needle) {
                $catName = mb_strtolower($cat->name);
                return str_contains($catName, $needle) || str_contains($needle, $catName);
            });

            if ($byName) return $byName;
        }

        return $categories->first(fn($cat) => $cat->name === 'Lainnya');
    }

    // ── Build konteks keuangan user real-time ─────
    private function buildFinancialContext(User $user): array
    {
        $period       = now()->format('Y-m');
        $periodLabel  = now()->translatedFormat('F Y');

        $wallets = $user->wallets()
            ->where('is_active', true)
            ->get(['display_name', 'balance', 'is_saham']);

        $totalBalance = $wallets->sum('balance');

        $txThisMonth  = $user->transactions()->forPeriod($period)->get();
        $totalIncome  = $txThisMonth->where('type', 'income')->sum('amount');
        $totalExpense = $txThisMonth->where('type', 'expense')->sum('amount');

        $topCategories = $txThisMonth
            ->where('type', 'expense')
            ->groupBy(fn($t) => $t->category?->name ?? 'Lainnya')
            ->map(fn($txs) => [
                'name'  => $txs->first()->category?->name ?? 'Lainnya',
                'total' => $txs->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $goals = $user->savingGoals()
            ->where('status', 'active')
            ->get(['name', 'target_amount', 'current_amount']);

        $bills = $user->bills()
            ->where('is_active', true)
            ->get()
            ->filter(fn($b) => $b->days_until_due !== null && $b->days_until_due <= 7)
            ->values();

        $budgets = \App\Models\Budget::where('user_id', $user->id)
            ->where('period', $period)
            ->with('category:id,name')
            ->get()
            ->map(fn($b) => [
                'category' => $b->category?->name,
                'budget'   => $b->amount,
                'spent'    => $txThisMonth->where('type','expense')->where('category_id', $b->category_id)->sum('amount'),
            ]);

        return compact(
            'periodLabel', 'totalBalance', 'totalIncome', 'totalExpense',
            'wallets', 'topCategories', 'goals', 'bills', 'budgets'
        );
    }

    // ── Build full prompt ─────────────────────────
    private function buildPrompt(array $ctx, array $history, string $userMessage, string $greetingInstruction = ''): string
    {
        $walletList = collect($ctx['wallets'])->map(fn($w) =>
            "  - {$w->display_name}: Rp " . number_format($w->balance, 0, ',', '.')
            . ($w->is_saham ? ' (Saham)' : '')
        )->join("\n");

        $topCatList = collect($ctx['topCategories'])->map(fn($c) =>
            "  - {$c['name']}: Rp " . number_format($c['total'], 0, ',', '.')
        )->join("\n");

        $goalList = collect($ctx['goals'])->map(fn($g) =>
            "  - {$g->name}: " . number_format($g->current_amount, 0, ',', '.') . " / " . number_format($g->target_amount, 0, ',', '.')
        )->join("\n");

        $billList = collect($ctx['bills'])->map(fn($b) =>
            "  - {$b->name}: Rp " . number_format($b->amount, 0, ',', '.') . " (H-{$b->days_until_due})"
        )->join("\n");

        $budgetList = collect($ctx['budgets'])->map(function($b) {
            $pct = $b['budget'] > 0 ? round(($b['spent']/$b['budget'])*100) : 0;
            return "  - {$b['category']}: Rp " . number_format($b['spent'], 0, ',', '.') . " / Rp " . number_format($b['budget'], 0, ',', '.') . " ({$pct}%)";
        })->join("\n");

        $historyStr = '';
        foreach ($history as $msg) {
            $role       = $msg['role'] === 'user' ? 'User' : 'CuanAI';
            $historyStr .= "{$role}: {$msg['content']}\n";
        }

        $template = SystemSetting::get('cuan_ai_system_prompt') ?? static::defaultPromptTemplate();

        return strtr($template, [
            '{periodLabel}'         => $ctx['periodLabel'],
            '{totalBalance}'        => $this->fmt($ctx['totalBalance']),
            '{walletList}'          => $walletList ?: '  (belum ada dompet aktif)',
            '{totalIncome}'         => $this->fmt($ctx['totalIncome']),
            '{totalExpense}'        => $this->fmt($ctx['totalExpense']),
            '{topCategories}'       => $topCatList ?: '  (belum ada data pengeluaran)',
            '{goalList}'            => $goalList ?: '  (belum ada goal tabungan aktif)',
            '{billList}'            => $billList ?: '  (tidak ada tagihan jatuh tempo)',
            '{budgetList}'          => $budgetList ?: '  (belum ada budget diatur)',
            '{history}'             => $historyStr,
            '{userMessage}'         => $userMessage,
            '{greetingInstruction}' => $greetingInstruction,
        ]);
    }

    public static function defaultPromptTemplate(): string
    {
        return <<<PROMPT
Kamu adalah CuanAI, asisten keuangan pribadi yang cerdas dan friendly dari aplikasi CatatCuan.

ATURAN PENTING:
1. Kamu HANYA membahas topik keuangan pribadi (budgeting, tabungan, investasi, utang, pengeluaran, pemasukan, dll)
2. Jika pertanyaan di luar topik keuangan, jawab: "Maaf, saya hanya bisa membantu soal keuangan pribadi kamu. Ada yang mau ditanyakan soal keuangan? 😊"
3. Gunakan data keuangan user di bawah untuk jawaban yang personal dan spesifik
4. Kalau ada baris [STATUS SISTEM — HASIL AKSI: ...] di pesan user, itu artinya sistem BARU SAJA mencoba menjalankan aksi (catat transaksi/bayar tagihan/bikin dompet). Sampaikan hasilnya secara natural dan JUJUR ke user. Jangan mengarang hasil yang berbeda dari status sistem. Kalau ada beberapa transaksi yang diproses sekaligus, sebutkan rincian tiap transaksinya, bukan cuma jumlahnya doang.
5. Bahasa: Indonesia, friendly, pakai emoji secukupnya
6. Jawaban singkat dan langsung ke poin, max 3-4 paragraf
7. {greetingInstruction}

DATA KEUANGAN USER — {periodLabel}:
Total Saldo: Rp {totalBalance}

Dompet & Rekening:
{walletList}

Pemasukan bulan ini: Rp {totalIncome}
Pengeluaran bulan ini: Rp {totalExpense}

Top Pengeluaran:
{topCategories}

Goals Tabungan Aktif:
{goalList}

Tagihan Jatuh Tempo (7 hari):
{billList}

Budget vs Realisasi:
{budgetList}

RIWAYAT PERCAKAPAN:
{history}

User: {userMessage}
CuanAI:
PROMPT;
    }

    private function fmt(float $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
