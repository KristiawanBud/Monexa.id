<?php

$files = [];

$files['/var/www/monexa/app/Services/GeminiService.php'] = <<<'GEMINI_SVC_EOT'
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private ?string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');
    }

    // ── Parse Pesan WA Natural Language ──────────────
    public function parseWaMessage(string $message, array $context = []): array
    {
        $systemPrompt = $this->getWaParserPrompt($context);

        $result = $this->generate($systemPrompt . "\n\nPesan user: " . $message);

        if (!$result['success']) {
            return ['intent' => 'unknown', 'error' => $result['error']];
        }

        try {
            $text    = $result['text'];
            $cleaned = preg_replace('/```json|```/i', '', $text);
            $cleaned = trim($cleaned);
            return json_decode($cleaned, true) ?? ['intent' => 'unknown'];
        } catch (\Exception $e) {
            Log::error("GeminiService: Gagal parse response: {$e->getMessage()}");
            return ['intent' => 'unknown'];
        }
    }

    // ── Parse Struk / Receipt dari Gambar ─────────────
    public function parseReceipt(string $base64Image, string $mimeType = 'image/jpeg'): array
    {
        $prompt = "Kamu adalah parser struk belanja Indonesia.\n"
            . "Analisa struk ini dan kembalikan HANYA JSON (tanpa teks lain):\n"
            . "{\n"
            . '  "merchant": "nama toko",'."\n"
            . '  "total": 0,'."\n"
            . '  "date": "YYYY-MM-DD atau null",'."\n"
            . '  "items": ['."\n"
            . '    {"name": "nama item", "qty": 1, "price": 0}'."\n"
            . '  ],'."\n"
            . '  "tax": 0,'."\n"
            . '  "confidence": 0.0'."\n"
            . "}\n\n"
            . "Jika tidak jelas, isi dengan nilai null/0. confidence antara 0.0-1.0.";

        $result = $this->generateWithImage($prompt, $base64Image, $mimeType);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        try {
            $text    = $result['text'];
            $cleaned = preg_replace('/```json|```/i', '', $text);
            $cleaned = trim($cleaned);
            $parsed  = json_decode($cleaned, true);

            return ['success' => true, 'data' => $parsed];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal membaca struk'];
        }
    }

    // ── AI Advisor: Analisa keuangan user ─────────────
    public function analyzeFinance(array $financialData): string
    {
        $prompt = $this->getAdvisorPrompt($financialData);
        $result = $this->generate($prompt);

        return $result['success']
            ? $result['text']
            : 'Maaf, AI Advisor sedang tidak tersedia.';
    }

    // ── Core: Generate Text (dengan retry otomatis kalau 503/429) ──
    public function generate(string $prompt, int $maxTokens = 1000): array
    {
        $maxRetries = 2;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]]
                        ],
                        'generationConfig' => [
                            'maxOutputTokens' => $maxTokens,
                            'temperature'     => 0.1,
                        ],
                    ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                    return ['success' => true, 'text' => trim($text)];
                }

                if (in_array($response->status(), [503, 429]) && $attempt < $maxRetries) {
                    Log::warning("GeminiService: {$response->status()} — retry ke-" . ($attempt + 1));
                    usleep(800000);
                    continue;
                }

                Log::error("GeminiService: API error {$response->status()}: {$response->body()}");
                return ['success' => false, 'error' => "API error: {$response->status()}"];

            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    usleep(800000);
                    continue;
                }
                Log::error("GeminiService generate error: {$e->getMessage()}");
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => false, 'error' => 'Gagal setelah beberapa percobaan'];
    }

    // ── Core: Generate dengan Image ───────────────────
    public function generateWithImage(string $prompt, string $base64Image, string $mimeType = 'image/jpeg', int $maxTokens = 1000): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => $base64Image,
                                    ]
                                ],
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $maxTokens,
                        'temperature'     => 0.1,
                    ],
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => "API error: {$response->status()}"];
            }

            $text = $response->json('candidates.0.content.parts.0.text') ?? '';
            return ['success' => true, 'text' => trim($text)];

        } catch (\Exception $e) {
            Log::error("GeminiService image error: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── WA Parser System Prompt ───────────────────────
    private function getWaParserPrompt(array $context = []): string
    {
        $wallets      = implode(', ', $context['wallets'] ?? ['BCA', 'Mandiri', 'Cash']);
        $categoryList = $context['categories'] ?? ['Makan & Minum', 'Transport', 'Belanja Harian', 'Tagihan', 'Hiburan'];
        $categories   = implode(', ', $categoryList);

        return "Kamu adalah parser transaksi keuangan untuk CatatCuan.\n"
            . "Kembalikan HANYA JSON, tidak ada teks lain:\n"
            . "{\n"
            . '  "intent": "add_income"|"add_expense"|"add_multiple"|"pay_bill"|"add_wallet"|"get_balance"|"get_report"|"get_bills"|"help"|"unknown",'."\n"
            . '  "amount": number|null,'."\n"
            . '  "category": string|null,'."\n"
            . '  "note": string|null,'."\n"
            . '  "date": "YYYY-MM-DD"|null,'."\n"
            . '  "wallet": string|null,'."\n"
            . '  "bill_name": string|null,'."\n"
            . '  "wallet_name": string|null,'."\n"
            . '  "wallet_type": "cash_flow"|"saving"|"both"|"investment"|null,'."\n"
            . '  "initial_balance": number|null,'."\n"
            . '  "items": [{"type":"income"|"expense","amount":number,"category":string|null,"note":string|null}]|null'."\n"
            . "}\n\n"
            . "Dompet user: {$wallets}\n"
            . "Kategori yang TERSEDIA (pilih PERSIS salah satu nama ini, jangan diparafrase/disingkat/diterjemahkan): {$categories}\n"
            . "PENTING soal \"category\": HARUS persis sama dengan salah satu nama di atas (boleh beda besar-kecil huruf). "
            . "Kalau nggak ada yang cocok sama sekali, isi null — jangan mengarang nama kategori baru.\n\n"
            . "PENTING soal transaksi GANDA: kalau user menyebutkan LEBIH DARI SATU transaksi terpisah dalam 1 pesan "
            . "(misal 'parkir 5rb dan makan 75rb', 'beli baju 100rb sama makan siang 30rb'), JANGAN digabung jadi 1 transaksi. "
            . "Gunakan intent \"add_multiple\", isi field \"items\" dengan array berisi transaksi masing-masing "
            . "(type, amount, category, note terpisah per item), dan field \"wallet\" di level atas untuk dompet yang dipakai semua item. "
            . "Kalau cuma 1 transaksi, TETAP pakai intent add_income/add_expense biasa, JANGAN pakai add_multiple.\n\n"
            . "Contoh:\n"
            . '"Makan siang 35rb" → {"intent":"add_expense","amount":35000,"category":"Makan & Minum","note":"makan siang"}'."\n"
            . '"Bensin motor 50rb" → {"intent":"add_expense","amount":50000,"category":"Transport","note":"bensin motor"}'."\n"
            . '"Gaji Juni 8 juta ke BCA" → {"intent":"add_income","amount":8000000,"category":"Gaji","note":"gaji juni","wallet":"BCA"}'."\n"
            . '"Saldo" → {"intent":"get_balance"}'."\n"
            . '"Lunas tagihan Listrik PLN" → {"intent":"pay_bill","bill_name":"Listrik PLN"}'."\n"
            . '"Bayar tagihan Listrik Juli 1 juta via Mandiri" → {"intent":"pay_bill","bill_name":"Listrik Juli","amount":1000000,"wallet":"Mandiri"}'."\n"
            . '"Tambah dompet baru BCA" → {"intent":"add_wallet","wallet_name":"BCA","wallet_type":"cash_flow","initial_balance":0}'."\n"
            . '"Buat dompet Tabungan Emas saldo awal 500rb" → {"intent":"add_wallet","wallet_name":"Tabungan Emas","wallet_type":"saving","initial_balance":500000}'."\n"
            . '"Parkir motor 5000 dan makan 75000 Via Mandiri" → {"intent":"add_multiple","wallet":"Mandiri","items":[{"type":"expense","amount":5000,"category":"Transport","note":"parkir motor"},{"type":"expense","amount":75000,"category":"Makan & Minum","note":"makan"}]}';
    }

    // ── AI Advisor Prompt ─────────────────────────────
    private function getAdvisorPrompt(array $data): string
    {
        $income  = number_format($data['total_income'] ?? 0, 0, ',', '.');
        $expense = number_format($data['total_expense'] ?? 0, 0, ',', '.');
        $saving  = number_format($data['total_saving'] ?? 0, 0, ',', '.');
        $period  = $data['period'] ?? 'bulan ini';

        $topCats = collect($data['top_categories'] ?? [])->take(5)->map(fn($c) =>
            "- {$c['category']}: Rp " . number_format($c['total'], 0, ',', '.')
        )->join("\n");

        return "Kamu adalah AI Financial Advisor CatatCuan. Analisa keuangan user ini dan berikan saran singkat dalam bahasa Indonesia yang friendly.\n\n"
            . "Data keuangan {$period}:\n"
            . "Pemasukan: Rp {$income}\n"
            . "Pengeluaran: Rp {$expense}\n"
            . "Tabungan: Rp {$saving}\n"
            . "Pengeluaran terbesar:\n{$topCats}\n\n"
            . "Berikan:\n"
            . "1. Kesimpulan kondisi keuangan (2-3 kalimat)\n"
            . "2. 2-3 saran spesifik berdasarkan data\n"
            . "3. Satu motivasi singkat\n\n"
            . "Format: teks biasa, gunakan emoji secukupnya. Maksimal 200 kata.";
    }
}

GEMINI_SVC_EOT;

$files['/var/www/monexa/app/Services/CuanAiService.php'] = <<<'CUANAI_SVC_EOT'
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

CUANAI_SVC_EOT;

$files['/var/www/monexa/app/Services/WaParserService.php'] = <<<'WAPARSER_SVC_EOT'
<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\UserWallet;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\WaMessageLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaParserService
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService,
        private WaGatewayService $gatewayService
    ) {}

    public function handleIncomingMessage(User $user, string $message, ?string $imageUrl = null): string
    {
        if ($imageUrl) {
            return $this->handleReceiptImage($user, $imageUrl);
        }

        $context = [
            'wallets'    => $user->wallets()->where('is_active', true)->pluck('display_name')->toArray(),
            'categories' => TransactionCategory::forUser($user->id)->pluck('name')->toArray(),
        ];

        $parsed = $this->gemini->parseWaMessage($message, $context);
        $intent = $parsed['intent'] ?? 'unknown';

        Log::info("WaParserService: Intent terdeteksi '{$intent}' dari user {$user->id}: {$message}");

        return match ($intent) {
            'add_income'   => $this->handleAddTransaction($user, $parsed, 'income'),
            'add_expense'  => $this->handleAddTransaction($user, $parsed, 'expense'),
            'add_multiple' => $this->handleMultipleTransactions($user, $parsed),
            'pay_bill'     => $this->handlePayBill($user, $parsed),
            'get_balance'  => $this->handleGetBalance($user),
            'get_report'   => $this->handleGetReport($user),
            'get_bills'    => $this->handleGetBills($user),
            'help'         => $this->handleHelp(),
            default        => $this->handleUnknown($message),
        };
    }

    // ────────────────────────────────────────────────
    // INTENT: Tambah Pemasukan / Pengeluaran
    // ────────────────────────────────────────────────
    private function handleAddTransaction(User $user, array $parsed, string $type): string
    {
        $amount = $parsed['amount'] ?? null;

        if (!$amount || $amount <= 0) {
            return "❌ Maaf, saya tidak bisa membaca nominalnya. Coba ulangi dengan format:\n\n"
                . "_\"Makan siang 35rb\"_ atau _\"Gaji 8 juta\"_";
        }

        $wallet = $this->resolveWallet($user, $parsed['wallet'] ?? null);

        if (!$wallet) {
            return "❌ Kamu belum punya dompet aktif. Tambahkan dompet dulu di aplikasi ya!";
        }

        $category = $this->resolveCategory($user, $parsed['category'] ?? null, $type);

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
                    'source'        => 'wa_bot',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);
            });
        } catch (InsufficientBalanceException $e) {
            return "❌ {$e->getMessage()}";
        }

        $typeLabel = $type === 'income' ? '💵 Pemasukan' : '💸 Pengeluaran';
        $emoji     = $category?->emoji ?? '✨';

        return "✅ *{$typeLabel} Tercatat!*\n\n"
            . "{$emoji} " . ($category?->name ?? 'Lainnya') . "\n"
            . "💰 Rp " . number_format($amount, 0, ',', '.') . "\n"
            . "🏦 {$wallet->display_name}\n"
            . ($parsed['note'] ? "📝 {$parsed['note']}\n" : '')
            . "\n💼 Saldo {$wallet->display_name} sekarang: Rp " . number_format($wallet->fresh()->balance, 0, ',', '.');
    }

    // ────────────────────────────────────────────────
    // INTENT: Transaksi GANDA (lebih dari 1 dalam 1 pesan)
    // ────────────────────────────────────────────────
    private function handleMultipleTransactions(User $user, array $parsed): string
    {
        $items = $parsed['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return "❌ Sepertinya ada beberapa transaksi di pesanmu, tapi saya tidak bisa memisahkannya dengan jelas. "
                . "Coba sebutkan tiap transaksi & nominalnya, misal:\n\n_\"Parkir 5rb dan makan 75rb\"_";
        }

        $sharedWallet = $parsed['wallet'] ?? null;
        $sharedDate   = $parsed['date'] ?? null;

        $lines        = [];
        $successCount = 0;

        foreach ($items as $item) {
            $type   = ($item['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
            $amount = $item['amount'] ?? null;

            if (!$amount || $amount <= 0) {
                $lines[] = "❌ " . ($item['note'] ?? 'Transaksi') . ": nominal tidak jelas";
                continue;
            }

            $wallet = $this->resolveWallet($user, $item['wallet'] ?? $sharedWallet);
            if (!$wallet) {
                $lines[] = "❌ " . ($item['note'] ?? 'Transaksi') . ": belum ada dompet aktif";
                continue;
            }

            $category     = $this->resolveCategory($user, $item['category'] ?? null, $type);
            $transactedAt = $sharedDate ?? now()->toDateString();

            try {
                DB::transaction(function () use ($user, $wallet, $category, $type, $amount, $item, $transactedAt, &$transaction) {
                    $transaction = $user->transactions()->create([
                        'wallet_id'     => $wallet->id,
                        'category_id'   => $category?->id,
                        'type'          => $type,
                        'amount'        => $amount,
                        'note'          => $item['note'] ?? null,
                        'transacted_at' => $transactedAt,
                        'source'        => 'wa_bot',
                        'created_by'    => $user->id,
                    ]);

                    $this->walletService->applyTransaction($transaction);
                });

                $emoji = $category?->emoji ?? '✨';
                $sign  = $type === 'income' ? '+' : '-';
                $lines[] = "✅ {$emoji} " . ($item['note'] ?? $category?->name ?? 'Transaksi')
                    . " ({$category?->name}): {$sign}Rp " . number_format($amount, 0, ',', '.');
                $successCount++;

            } catch (InsufficientBalanceException $e) {
                $lines[] = "❌ " . ($item['note'] ?? 'Transaksi') . ": {$e->getMessage()}";
            }
        }

        $totalCount = count($items);
        $summary    = implode("\n", $lines);

        return "📝 *{$successCount}/{$totalCount} Transaksi Tercatat!*\n\n{$summary}";
    }

    // ────────────────────────────────────────────────
    // INTENT: Bayar Tagihan
    // ────────────────────────────────────────────────
    private function handlePayBill(User $user, array $parsed): string
    {
        $billName = $parsed['bill_name'] ?? null;

        if (!$billName) {
            return "❌ Sebutkan nama tagihan yang mau dibayar. Contoh: _\"Lunas tagihan Listrik\"_";
        }

        $bill = $user->bills()
            ->where('is_active', true)
            ->where('name', 'like', "%{$billName}%")
            ->first();

        if (!$bill) {
            return "❌ Tagihan \"{$billName}\" tidak ditemukan. Cek daftar tagihanmu di aplikasi ya!";
        }

        $forPeriod = now()->format('Y-m');

        $alreadyPaid = BillPayment::where('bill_id', $bill->id)
            ->where('for_period', $forPeriod)
            ->exists();

        if ($alreadyPaid) {
            return "ℹ️ Tagihan *{$bill->name}* untuk bulan ini sudah lunas!";
        }

        $wallet = $this->resolveWallet($user, $parsed['wallet'] ?? null);
        if (!$wallet) {
            return "❌ Kamu belum punya dompet aktif untuk membayar tagihan ini.";
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
                    'source'        => 'wa_bot',
                    'created_by'    => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                BillPayment::create([
                    'bill_id'        => $bill->id,
                    'wallet_id'      => $wallet->id,
                    'transaction_id' => $transaction->id,
                    'amount_paid'    => $bill->amount,
                    'paid_at'        => now(),
                    'source'         => 'wa_bot',
                    'for_period'     => $forPeriod,
                ]);

                $bill->update(['last_paid_at' => now()]);
            });
        } catch (InsufficientBalanceException $e) {
            return "❌ {$e->getMessage()}";
        }

        return "✅ *Tagihan Lunas!*\n\n"
            . "{$bill->emoji} {$bill->name}\n"
            . "💰 Rp " . number_format($bill->amount, 0, ',', '.') . "\n"
            . "🏦 Dibayar dari {$wallet->display_name}";
    }

    // ────────────────────────────────────────────────
    // INTENT: Cek Saldo
    // ────────────────────────────────────────────────
    private function handleGetBalance(User $user): string
    {
        $wallets = $user->wallets()->where('is_active', true)->orderBy('sort_order')->get();

        if ($wallets->isEmpty()) {
            return "Kamu belum punya dompet aktif.";
        }

        $total = $wallets->sum('balance');
        $lines = $wallets->map(fn($w) =>
            "🏦 {$w->display_name}: Rp " . number_format($w->balance, 0, ',', '.')
        )->join("\n");

        return "💰 *Saldo Kamu*\n\n{$lines}\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "*Total: Rp " . number_format($total, 0, ',', '.') . "*";
    }

    // ────────────────────────────────────────────────
    // INTENT: Laporan Bulan Ini
    // ────────────────────────────────────────────────
    private function handleGetReport(User $user): string
    {
        $period = now()->format('Y-m');
        $txs    = $user->transactions()->forPeriod($period)->get();

        $income  = $txs->where('type', 'income')->sum('amount');
        $expense = $txs->where('type', 'expense')->sum('amount');

        $topCategories = $txs->where('type', 'expense')
            ->groupBy(fn($t) => $t->category?->name ?? 'Lainnya')
            ->map(fn($group) => [
                'name'  => $group->first()->category?->name ?? 'Lainnya',
                'total' => $group->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(3);

        $topLines = $topCategories->map(fn($c) =>
            "  • {$c['name']}: Rp " . number_format($c['total'], 0, ',', '.')
        )->join("\n");

        return "📊 *Laporan " . now()->translatedFormat('F Y') . "*\n\n"
            . "↑ Pemasukan: Rp " . number_format($income, 0, ',', '.') . "\n"
            . "↓ Pengeluaran: Rp " . number_format($expense, 0, ',', '.') . "\n"
            . "💰 Selisih: Rp " . number_format($income - $expense, 0, ',', '.') . "\n\n"
            . ($topLines ? "*Top Pengeluaran:*\n{$topLines}" : '');
    }

    // ────────────────────────────────────────────────
    // INTENT: Lihat Tagihan Aktif
    // ────────────────────────────────────────────────
    private function handleGetBills(User $user): string
    {
        $bills = $user->bills()->where('is_active', true)->get();

        if ($bills->isEmpty()) {
            return "Kamu belum punya tagihan aktif.";
        }

        $lines = $bills->map(function ($b) {
            $due = $b->days_until_due;
            $dueLabel = $due === null ? '' : ($due === 0 ? ' (Hari ini!)' : " (H-{$due})");
            return "{$b->emoji} {$b->name}: Rp " . number_format($b->amount, 0, ',', '.') . $dueLabel;
        })->join("\n");

        return "📋 *Tagihan Aktif Kamu*\n\n{$lines}";
    }

    // ────────────────────────────────────────────────
    // INTENT: Bantuan / Help
    // ────────────────────────────────────────────────
    private function handleHelp(): string
    {
        return "🤖 *Panduan CatatCuan Bot*\n\n"
            . "Kirim pesan natural untuk mencatat:\n\n"
            . "💵 *\"Gaji Juni 8 juta\"* → catat pemasukan\n"
            . "💸 *\"Makan siang 35rb\"* → catat pengeluaran\n"
            . "📝 *\"Parkir 5rb dan makan 75rb\"* → catat beberapa transaksi sekaligus\n"
            . "💰 *\"Saldo\"* → cek saldo semua dompet\n"
            . "📊 *\"Laporan bulan ini\"* → ringkasan keuangan\n"
            . "📋 *\"Tagihan\"* → lihat tagihan aktif\n"
            . "✅ *\"Lunas tagihan Listrik\"* → tandai tagihan lunas\n"
            . "📷 Kirim *foto struk* → otomatis dibaca AI\n\n"
            . "_CatatCuan — Catat keuangan, hidup lebih tenang_ ✨";
    }

    // ────────────────────────────────────────────────
    // INTENT: Tidak Dikenali
    // ────────────────────────────────────────────────
    private function handleUnknown(string $message): string
    {
        return "🤔 Maaf, saya belum paham maksudnya.\n\n"
            . "Coba ketik *\"Bantuan\"* untuk lihat semua command yang bisa dipakai.";
    }

    // ────────────────────────────────────────────────
    // Handle Foto Struk via WA
    // ────────────────────────────────────────────────
    private function handleReceiptImage(User $user, string $imageUrl): string
    {
        try {
            $imageData = file_get_contents($imageUrl);
            if (!$imageData) {
                return "❌ Gagal mengunduh gambar. Coba kirim ulang struknya.";
            }

            $base64 = base64_encode($imageData);
            $result = $this->gemini->parseReceipt($base64);

            if (!$result['success'] || !isset($result['data'])) {
                return "❌ Gagal membaca struk. Coba foto yang lebih jelas dan terang.";
            }

            $data   = $result['data'];
            $wallet = $this->resolveWallet($user, null);

            if (!$wallet) {
                return "❌ Kamu belum punya dompet aktif untuk mencatat struk ini.";
            }

            $category = $this->resolveCategory($user, null, 'expense');

            $transaction = $user->transactions()->create([
                'wallet_id'     => $wallet->id,
                'category_id'   => $category?->id,
                'type'          => 'expense',
                'amount'        => $data['total'] ?? 0,
                'note'          => $data['merchant'] ?? 'Struk WA',
                'transacted_at' => $data['date'] ?? now()->toDateString(),
                'source'        => 'wa_receipt',
                'created_by'    => $user->id,
            ]);

            $this->walletService->applyTransaction($transaction);

            return "📷 *Struk Berhasil Dibaca!*\n\n"
                . "🏪 " . ($data['merchant'] ?? 'Tidak diketahui') . "\n"
                . "💰 Rp " . number_format($data['total'] ?? 0, 0, ',', '.') . "\n"
                . "🏦 Dicatat ke {$wallet->display_name}\n\n"
                . "_Cek dan edit di aplikasi jika ada yang kurang tepat._";

        } catch (InsufficientBalanceException $e) {
            return "❌ {$e->getMessage()}";
        } catch (\Exception $e) {
            Log::error("WaParserService handleReceiptImage error: {$e->getMessage()}");
            return "❌ Terjadi kesalahan saat memproses struk. Coba lagi nanti.";
        }
    }

    // ────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ────────────────────────────────────────────────
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
}

WAPARSER_SVC_EOT;

foreach ($files as $path => $content) {
    if (file_exists($path)) {
        $backup = $path.'.bak_'.date('Ymd_His');
        copy($path, $backup);
        echo "Backup: $backup\n";
    }

    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

echo "\nSelesai menulis 3 file.\n";
