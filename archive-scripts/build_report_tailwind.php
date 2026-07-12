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

// ─────────────────────────────────────────────
// 1. tailwind.config.js (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/tailwind.config.js', <<<'EOT'
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.vue',
    './resources/**/*.js',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#2F6BFF',
          dark: '#1E4FD6',
          light: '#EAF0FF',
        },
      },
      borderRadius: {
        xl2: '20px',
        xl3: '24px',
      },
      boxShadow: {
        soft: '0 4px 20px rgba(15,23,42,0.06)',
        card: '0 2px 12px rgba(15,23,42,0.05)',
      },
    },
  },
  corePlugins: {
    // Preflight dimatikan supaya CSS Tailwind TIDAK bentrok/reset
    // styling halaman lain yang belum migrasi ke Tailwind.
    preflight: false,
  },
  plugins: [],
}

EOT
);

// ─────────────────────────────────────────────
// 2. postcss.config.js (BARU)
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/postcss.config.js', <<<'EOT'
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}

EOT
);

// ─────────────────────────────────────────────
// 3. resources/css/app.css — tambah @tailwind di paling atas (append, bukan timpa isi lama)
// ─────────────────────────────────────────────
$cssFile = '/var/www/monexa/resources/css/app.css';
if (file_exists($cssFile)) {
    $existing = file_get_contents($cssFile);
    if (strpos($existing, '@tailwind base') === false) {
        copy($cssFile, $cssFile.'.bak_'.date('Ymd_His'));
        $newContent = "@tailwind base;\n@tailwind components;\n@tailwind utilities;\n\n".$existing;
        file_put_contents($cssFile, $newContent);
        echo "OK: @tailwind directive ditambahkan ke app.css\n";
    } else {
        echo "SKIP: @tailwind sudah ada di app.css\n";
    }
} else {
    echo "SKIP: app.css tidak ditemukan\n";
}

// ─────────────────────────────────────────────
// 4. ReportController.php — TIMPA, tambah range chart + insight
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/App/ReportController.php', <<<'EOT'
<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\TransactionCategory;
use App\Services\WaGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private WaGatewayService $gatewayService) {}

    public function index(Request $request): \Inertia\Response
    {
        $user   = $request->user();
        $period = $request->period ?? now()->format('Y-m');
        [$year, $month] = explode('-', $period);

        $range = in_array($request->range, ['6months', '12months', 'year', 'all']) ? $request->range : '6months';

        $monthsCount = match ($range) {
            '12months' => 12,
            'year'     => (int) now()->format('n'),
            'all'      => $this->monthsSinceFirstTransaction($user),
            default    => 6,
        };
        $monthsCount = max(1, min($monthsCount, 60));

        $barData = [];
        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $d   = now()->subMonths($i);
            $p   = $d->format('Y-m');
            $txs = $user->transactions()->forPeriod($p)->get();
            $barData[] = [
                'period'  => $d->format('M'),
                'income'  => (float) $txs->where('type', 'income')->sum('amount'),
                'expense' => (float) $txs->where('type', 'expense')->sum('amount'),
                'active'  => $p === $period,
            ];
        }

        $expenseByCategory = $user->transactions()
            ->forPeriod($period)
            ->where('type', 'expense')
            ->with('category:id,name,emoji')
            ->get()
            ->groupBy(fn($t) => $t->category?->name ?? 'Lainnya')
            ->map(fn($txs) => [
                'category' => $txs->first()->category?->name ?? 'Lainnya',
                'emoji'    => $txs->first()->category?->emoji ?? '✨',
                'total'    => (float) $txs->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();

        $totalExpenseCat = $expenseByCategory->sum('total');
        $donutData = $expenseByCategory->map(fn($cat) => [
            ...$cat,
            'percent' => $totalExpenseCat > 0 ? round(($cat['total'] / $totalExpenseCat) * 100, 1) : 0,
        ]);

        $budgets = Budget::where('user_id', $user->id)
            ->where('period', $period)
            ->with('category:id,name,emoji')
            ->get()
            ->map(function ($b) use ($user, $period) {
                $spent = $user->transactions()
                    ->forPeriod($period)
                    ->where('type', 'expense')
                    ->where('category_id', $b->category_id)
                    ->sum('amount');
                $percent = $b->amount > 0 ? round(($spent / $b->amount) * 100) : 0;
                return [
                    'category' => $b->category?->name,
                    'emoji'    => $b->category?->emoji,
                    'budget'   => (float) $b->amount,
                    'spent'    => (float) $spent,
                    'percent'  => $percent,
                    'status'   => $percent >= 100 ? 'over' : ($percent >= 80 ? 'warn' : 'ok'),
                ];
            });

        $txPeriod      = $user->transactions()->forPeriod($period)->get();
        $totalIncome   = (float) $txPeriod->where('type', 'income')->sum('amount');
        $totalExpenseP = (float) $txPeriod->where('type', 'expense')->sum('amount');

        $prevPeriod    = now()->subMonth()->format('Y-m');
        $prevTxs       = $user->transactions()->forPeriod($prevPeriod)->get();
        $prevIncome    = (float) $prevTxs->where('type', 'income')->sum('amount');
        $prevExpense   = (float) $prevTxs->where('type', 'expense')->sum('amount');
        $incomeChange  = $prevIncome > 0 ? round((($totalIncome - $prevIncome) / $prevIncome) * 100, 1) : 0;
        $expenseChange = $prevExpense > 0 ? round((($totalExpenseP - $prevExpense) / $prevExpense) * 100, 1) : 0;

        $totalSaving = (float) DB::table('saving_deposits')
            ->join('saving_goals', 'saving_deposits.saving_goal_id', '=', 'saving_goals.id')
            ->where('saving_goals.user_id', $user->id)
            ->whereYear('deposited_at', $year)
            ->whereMonth('deposited_at', $month)
            ->sum('amount');

        $savingRatio = $totalIncome > 0 ? round(($totalSaving / $totalIncome) * 100) : 0;

        $totalBudgetAmount = Budget::where('user_id', $user->id)->where('period', $period)->sum('amount');
        $budgetDiscipline  = $totalBudgetAmount > 0
            ? max(0, 100 - round((max(0, $totalExpenseP - $totalBudgetAmount) / $totalBudgetAmount) * 100))
            : 100;

        $avgExpense3M = 0;
        for ($i = 0; $i < 3; $i++) {
            $d = now()->subMonths($i);
            $avgExpense3M += (float) $user->transactions()
                ->where('type', 'expense')
                ->whereYear('transacted_at', $d->year)
                ->whereMonth('transacted_at', $d->month)
                ->sum('amount');
        }
        $avgExpense3M = $avgExpense3M / 3;

        $totalLiquid  = (float) $user->wallets()->where('is_active', true)->where('is_saham', false)->sum('balance');
        $runwayMonths = $avgExpense3M > 0 ? round($totalLiquid / $avgExpense3M, 1) : null;
        $runwayScore  = $runwayMonths === null ? 0 : min(100, round(($runwayMonths / 6) * 100));

        $healthScore  = round(($savingRatio + $budgetDiscipline + $runwayScore) / 3);
        $healthStatus = $healthScore >= 70 ? 'sehat' : ($healthScore >= 40 ? 'cukup' : 'perlu_perhatian');

        // ── Insight Bulan Ini ──
        $biggestExpenseTx = $txPeriod->where('type', 'expense')->sortByDesc('amount')->first();
        $biggestIncomeTx  = $txPeriod->where('type', 'income')->sortByDesc('amount')->first();

        $isCurrentMonth = $period === now()->format('Y-m');
        $daysElapsed    = $isCurrentMonth ? now()->day : \Carbon\Carbon::createFromFormat('Y-m', $period)->daysInMonth;
        $dailyAverage   = $daysElapsed > 0 ? $totalExpenseP / $daysElapsed : 0;

        $mostWastefulDay = $txPeriod->where('type', 'expense')
            ->groupBy(fn($t) => $t->transacted_at->format('Y-m-d'))
            ->map(fn($txs, $date) => ['date' => $date, 'total' => (float) $txs->sum('amount')])
            ->sortByDesc('total')
            ->first();

        $insight = [
            'biggest_expense' => $biggestExpenseTx ? [
                'amount' => (float) $biggestExpenseTx->amount,
                'note'   => $biggestExpenseTx->note ?: ($biggestExpenseTx->category?->name ?? 'Transaksi'),
            ] : null,
            'biggest_income' => $biggestIncomeTx ? [
                'amount' => (float) $biggestIncomeTx->amount,
                'note'   => $biggestIncomeTx->note ?: ($biggestIncomeTx->category?->name ?? 'Transaksi'),
            ] : null,
            'daily_average' => $dailyAverage,
            'most_wasteful_day' => $mostWastefulDay ? [
                'date'  => \Carbon\Carbon::parse($mostWastefulDay['date'])->translatedFormat('d M Y'),
                'total' => $mostWastefulDay['total'],
            ] : null,
        ];

        return Inertia::render('App/Report', [
            'period'          => $period,
            'periodLabel'     => \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y'),
            'range'           => $range,
            'barData'         => $barData,
            'donutData'       => $donutData,
            'budgets'         => $budgets,
            'totalIncome'     => $totalIncome,
            'totalExpense'    => $totalExpenseP,
            'incomeChange'    => $incomeChange,
            'expenseChange'   => $expenseChange,
            'savingRatio'     => $savingRatio,
            'budgetDiscipline'=> $budgetDiscipline,
            'runwayMonths'    => $runwayMonths,
            'runwayScore'     => $runwayScore,
            'healthScore'     => $healthScore,
            'healthStatus'    => $healthStatus,
            'insight'         => $insight,
            'months'          => $this->getLast12Months(),
        ]);
    }

    private function monthsSinceFirstTransaction($user): int
    {
        $first = $user->transactions()->orderBy('transacted_at')->first();
        if (!$first) return 6;
        return max(1, $first->transacted_at->diffInMonths(now()) + 1);
    }

    private function getLast12Months(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $months[] = ['value' => $d->format('Y-m'), 'label' => $d->format('M Y')];
        }
        return $months;
    }

    public function exportPdf(Request $request)
    {
        $user   = $request->user();
        $period = $request->period ?? now()->format('Y-m');
        $data   = $this->buildReportData($user, $period);

        $pdf = \PDF::loadView('reports.monthly-pdf', $data);

        return $pdf->download("Laporan-CatatCuan-{$period}.pdf");
    }

    public function exportExcel(Request $request)
    {
        $user   = $request->user();
        $period = $request->period ?? now()->format('Y-m');

        $transactions = $user->transactions()
            ->forPeriod($period)
            ->with(['wallet:id,display_name', 'category:id,name'])
            ->orderBy('transacted_at')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan ' . $period);

        $headers = ['Tanggal', 'Tipe', 'Kategori', 'Dompet', 'Catatan', 'Jumlah'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $row = 2;
        foreach ($transactions as $t) {
            $sheet->setCellValue("A{$row}", $t->transacted_at->format('d/m/Y'));
            $sheet->setCellValue("B{$row}", $t->type === 'income' ? 'Pemasukan' : 'Pengeluaran');
            $sheet->setCellValue("C{$row}", $t->category?->name ?? '-');
            $sheet->setCellValue("D{$row}", $t->wallet?->display_name ?? '-');
            $sheet->setCellValue("E{$row}", $t->note ?? '-');
            $sheet->setCellValue("F{$row}", (float) $t->amount);
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Laporan-CatatCuan-{$period}.xlsx";
        $tempPath = storage_path("app/temp/{$filename}");

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    public function sendToWhatsApp(Request $request)
    {
        $user   = $request->user();
        $period = $request->period ?? now()->format('Y-m');
        $data   = $this->buildReportData($user, $period);

        if (! $user->wa_number) {
            return back()->with('error', 'Nomor WA kamu belum terdaftar. Isi dulu di halaman Profil.');
        }

        $message = $this->formatReportForWhatsApp($data);
        $sent = $this->gatewayService->sendToUser($user, $message, 'monthly_report');

        if (! $sent) {
            return back()->with('error', 'Gagal mengirim laporan ke WA. Coba lagi nanti.');
        }

        return back()->with('success', "Laporan {$data['periodLabel']} berhasil dikirim ke WhatsApp kamu! 📲");
    }

    private function buildReportData($user, string $period): array
    {
        $txs = $user->transactions()->forPeriod($period)->with('category:id,name,emoji')->get();

        $totalIncome  = (float) $txs->where('type', 'income')->sum('amount');
        $totalExpense = (float) $txs->where('type', 'expense')->sum('amount');

        $byCategory = $txs->where('type', 'expense')
            ->groupBy(fn($t) => $t->category?->name ?? 'Lainnya')
            ->map(fn($group) => [
                'name'  => $group->first()->category?->name ?? 'Lainnya',
                'emoji' => $group->first()->category?->emoji ?? '✨',
                'total' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'user'         => $user,
            'period'       => $period,
            'periodLabel'  => \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y'),
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'selisih'      => $totalIncome - $totalExpense,
            'byCategory'   => $byCategory,
            'transactions' => $txs,
        ];
    }

    private function formatReportForWhatsApp(array $data): string
    {
        $topCategories = collect($data['byCategory'])->take(5)->map(fn($c) =>
            "  {$c['emoji']} {$c['name']}: Rp " . number_format($c['total'], 0, ',', '.')
        )->join("\n");

        return "📊 *Laporan Keuangan {$data['periodLabel']}*\n\n"
            . "↑ Pemasukan: Rp " . number_format($data['totalIncome'], 0, ',', '.') . "\n"
            . "↓ Pengeluaran: Rp " . number_format($data['totalExpense'], 0, ',', '.') . "\n"
            . "💰 Selisih: Rp " . number_format($data['selisih'], 0, ',', '.') . "\n\n"
            . ($topCategories ? "*Top 5 Pengeluaran:*\n{$topCategories}\n\n" : '')
            . "_Laporan lengkap bisa dilihat di aplikasi CatatCuan_ ✨";
    }
}

EOT
);

// ─────────────────────────────────────────────
// 5. Report.vue — TIMPA total, Tailwind + Lucide
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Pages/App/Report.vue', <<<'EOT'
<template>
  <AppLayout>
    <div class="bg-slate-50 min-h-screen pb-6">

      <!-- Hero -->
      <div class="bg-gradient-to-br from-brand to-brand-dark px-5 pt-5 pb-8 rounded-b-3xl relative overflow-hidden">
        <div class="absolute -top-10 -right-8 w-40 h-40 rounded-full bg-white/10"></div>
        <div class="absolute top-16 right-24 w-16 h-16 rounded-full bg-white/10"></div>

        <div class="flex justify-between items-start relative z-10 mb-4">
          <div>
            <h1 class="text-2xl font-extrabold text-white flex items-center gap-2">📊 Laporan</h1>
            <p class="text-white/80 text-sm mt-1 max-w-[220px]">Pantau perkembangan keuanganmu setiap bulan.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="showExportMenu = true"
              class="flex items-center gap-1.5 bg-white text-brand font-bold text-xs px-4 py-2.5 rounded-full shadow-soft">
              <Download :size="14" /> Export
            </button>
            <button class="relative w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white">
              <Bell :size="17" />
              <span v-if="$page.props.unread_notifications > 0"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-brand">
                {{ $page.props.unread_notifications }}
              </span>
            </button>
          </div>
        </div>

        <!-- Month dropdown -->
        <div class="relative inline-block z-10 mb-5">
          <button @click="showMonthMenu = !showMonthMenu"
            class="flex items-center gap-2 bg-white/20 text-white text-sm font-semibold px-4 py-2 rounded-full">
            <Calendar :size="15" /> {{ periodLabel }} <ChevronDown :size="15" />
          </button>
          <div v-if="showMonthMenu" class="absolute top-full left-0 mt-2 bg-white rounded-xl shadow-soft py-2 w-40 max-h-64 overflow-y-auto z-20">
            <button v-for="m in months" :key="m.value" @click="changePeriod(m.value)"
              :class="['block w-full text-left px-4 py-2 text-sm', period === m.value ? 'text-brand font-bold bg-brand-light' : 'text-slate-700 hover:bg-slate-50']">
              {{ m.label }}
            </button>
          </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 gap-3 relative z-10 -mb-14">
          <div class="bg-white rounded-2xl p-4 shadow-soft">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center mb-2">
              <TrendingUp :size="16" class="text-emerald-500" />
            </div>
            <div class="text-xs text-slate-500 font-medium">Total Pemasukan</div>
            <div class="text-lg font-extrabold text-emerald-500 mt-0.5">{{ formatRupiah(totalIncome) }}</div>
            <div :class="['text-[11px] font-semibold mt-1', incomeChange >= 0 ? 'text-emerald-500' : 'text-red-500']">
              {{ incomeChange >= 0 ? '↑' : '↓' }} {{ Math.abs(incomeChange) }}% vs bulan lalu
            </div>
          </div>
          <div class="bg-white rounded-2xl p-4 shadow-soft">
            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center mb-2">
              <TrendingDown :size="16" class="text-red-500" />
            </div>
            <div class="text-xs text-slate-500 font-medium">Total Pengeluaran</div>
            <div class="text-lg font-extrabold text-red-500 mt-0.5">{{ formatRupiah(totalExpense) }}</div>
            <div :class="['text-[11px] font-semibold mt-1', expenseChange <= 0 ? 'text-emerald-500' : 'text-red-500']">
              {{ expenseChange >= 0 ? '↑' : '↓' }} {{ Math.abs(expenseChange) }}% vs bulan lalu
            </div>
          </div>
        </div>
      </div>

      <div class="px-5 pt-16">

        <!-- Segmented control -->
        <div class="relative bg-white rounded-full p-1 shadow-card flex mb-5">
          <div class="absolute top-1 bottom-1 rounded-full bg-brand transition-all duration-300"
            :style="segmentStyle"></div>
          <button v-for="(opt, i) in rangeOptions" :key="opt.value"
            @click="changeRange(opt.value)"
            :class="['relative z-10 flex-1 text-center text-xs font-bold py-2.5 rounded-full transition-colors', range === opt.value ? 'text-white' : 'text-slate-500']">
            {{ opt.label }}
          </button>
        </div>

        <!-- Bar Chart -->
        <div class="bg-white rounded-2xl p-5 shadow-card mb-4">
          <div class="flex justify-between items-center mb-4">
            <div class="text-sm font-bold text-slate-800">Pemasukan vs Pengeluaran</div>
            <div class="flex items-center gap-1 bg-slate-50 text-slate-500 text-xs font-semibold px-3 py-1.5 rounded-full">
              Bulanan <ChevronDown :size="13" />
            </div>
          </div>

          <div class="flex items-end gap-2 overflow-x-auto pb-2" style="height:130px;">
            <div v-for="item in barData" :key="item.period" class="flex flex-col items-center flex-shrink-0" style="min-width:32px;">
              <div class="flex items-end gap-1" style="height:100px;">
                <div class="w-2.5 rounded-t bg-emerald-500 transition-all" :style="`height:${barHeight(item.income)}px`" :title="formatRupiah(item.income)"></div>
                <div class="w-2.5 rounded-t bg-red-500 transition-all" :style="`height:${barHeight(item.expense)}px`" :title="formatRupiah(item.expense)"></div>
              </div>
              <div :class="['text-[10px] mt-1.5', item.active ? 'text-brand font-bold' : 'text-slate-400']">{{ item.period }}</div>
            </div>
          </div>

          <div class="flex gap-4 justify-center mt-3">
            <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Pemasukan</div>
            <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="w-2 h-2 rounded-full bg-red-500"></span> Pengeluaran</div>
          </div>
        </div>

        <!-- Health cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div class="bg-white rounded-2xl p-4 shadow-card">
            <div class="flex justify-between items-start mb-3">
              <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                <Activity :size="16" class="text-brand" /> Budget Health Score
              </div>
              <button class="w-7 h-7 rounded-full bg-slate-50 flex items-center justify-center">
                <ArrowRight :size="14" class="text-slate-400" />
              </button>
            </div>
            <div class="flex items-baseline gap-1 mb-1">
              <span class="text-2xl font-extrabold text-brand">{{ healthScore }}</span>
              <span class="text-xs text-slate-400">/100</span>
            </div>
            <div :class="['text-xs font-bold mb-3 flex items-center gap-1', healthColorText]">
              <AppIcon :slug="`health_tier_${healthStatus}`" class="w-3.5 h-3.5 inline-block">{{ defaultTierEmoji(healthStatus) }}</AppIcon>
              {{ healthLabel(healthStatus) }}
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-24 text-slate-500 flex-shrink-0">Rasio Tabungan</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" :style="`width:${Math.min(savingRatio,100)}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ savingRatio }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-24 text-slate-500 flex-shrink-0">Disiplin Anggaran</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-purple-500 rounded-full" :style="`width:${budgetDiscipline}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ budgetDiscipline }}%</span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <span class="w-24 text-slate-500 flex-shrink-0">Runway</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-brand rounded-full" :style="`width:${runwayScore}%`"></div></div>
                <span class="w-8 text-right font-bold">{{ runwayScore }}%</span>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-4 shadow-card relative overflow-hidden">
            <div class="flex justify-between items-start mb-3 relative z-10">
              <div class="flex items-center gap-2 text-sm font-bold">
                <Shield :size="16" class="text-emerald-400" /> Dana Darurat
              </div>
              <button class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <ArrowRight :size="14" class="text-white/60" />
              </button>
            </div>
            <template v-if="runwayMonths !== null">
              <div class="flex items-baseline gap-1 mb-3 relative z-10">
                <span class="text-2xl font-extrabold">{{ runwayMonths }}</span>
                <span class="text-xs text-white/60">Bulan dari target 6 bulan</span>
              </div>
              <div class="h-2 bg-white/15 rounded-full overflow-hidden mb-2 relative z-10">
                <div class="h-full bg-amber-400 rounded-full" :style="`width:${Math.min((runwayMonths/6)*100,100)}%`"></div>
              </div>
              <div class="text-[11px] text-white/50 relative z-10">Target ideal: 6 bulan pengeluaran</div>
            </template>
            <div v-else class="text-xs text-white/60 relative z-10">Belum cukup data.</div>
            <PiggyBank :size="70" class="absolute -bottom-3 -right-3 text-white/10" />
          </div>
        </div>

        <!-- Donut Chart -->
        <div v-if="donutData.length > 0" class="bg-white rounded-2xl p-5 shadow-card mb-4">
          <div class="text-sm font-bold text-slate-800 mb-4">Kategori Pengeluaran</div>
          <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="relative w-40 h-40 flex-shrink-0">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <circle v-for="(seg, i) in donutSegments" :key="i" cx="50" cy="50" r="40" fill="transparent"
                  :stroke="donutColors[i % donutColors.length]" stroke-width="16"
                  :stroke-dasharray="`${seg.length} ${251.2 - seg.length}`" :stroke-dashoffset="-seg.offset" />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-[10px] text-slate-400">Total</div>
                <div class="text-base font-extrabold">{{ formatShort(totalExpense) }}</div>
              </div>
            </div>
            <div class="flex-1 w-full space-y-2.5">
              <div v-for="(cat, i) in donutData" :key="cat.category" class="flex items-center gap-2 text-xs">
                <span class="w-2 h-2 rounded-full flex-shrink-0" :style="`background:${donutColors[i % donutColors.length]}`"></span>
                <span class="flex-1 text-slate-700">{{ cat.emoji }} {{ cat.category }}</span>
                <span class="text-slate-400">Rp {{ formatShort(cat.total) }}</span>
                <span class="font-bold text-slate-800 w-10 text-right">{{ cat.percent }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Budget vs Realisasi -->
        <div v-if="budgets.length > 0" class="bg-white rounded-2xl p-5 shadow-card mb-4">
          <div class="text-sm font-bold text-slate-800 mb-4">Budget vs Realisasi</div>
          <div v-for="b in budgets" :key="b.category" class="mb-3.5 last:mb-0">
            <div class="flex justify-between text-xs mb-1.5">
              <span class="font-semibold text-slate-700">{{ b.emoji }} {{ b.category }}</span>
              <span class="text-slate-400">Rp {{ formatShort(b.spent) }} / Rp {{ formatShort(b.budget) }}</span>
            </div>
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div :class="['h-full rounded-full', b.status === 'over' ? 'bg-red-500' : b.status === 'warn' ? 'bg-amber-500' : 'bg-emerald-500']" :style="`width:${Math.min(b.percent,100)}%`"></div>
            </div>
          </div>
        </div>

        <!-- Insight Bulan Ini -->
        <div class="mb-4">
          <div class="text-sm font-bold text-brand mb-3">Insight Bulan Ini</div>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-xl p-3 shadow-card">
              <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center mb-2"><TrendingUp :size="14" class="text-emerald-500" /></div>
              <div class="text-[10px] text-slate-500">Pemasukan tertinggi</div>
              <div class="text-sm font-extrabold mt-0.5">{{ insight.biggest_income ? formatShort(insight.biggest_income.amount) : '-' }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 shadow-card">
              <div class="w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center mb-2"><TrendingDown :size="14" class="text-red-500" /></div>
              <div class="text-[10px] text-slate-500">Pengeluaran tertinggi</div>
              <div class="text-sm font-extrabold mt-0.5">{{ insight.biggest_expense ? formatShort(insight.biggest_expense.amount) : '-' }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 shadow-card">
              <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center mb-2"><Users :size="14" class="text-purple-500" /></div>
              <div class="text-[10px] text-slate-500">Rata-rata harian</div>
              <div class="text-sm font-extrabold mt-0.5">Rp {{ formatShort(insight.daily_average) }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 shadow-card">
              <div class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center mb-2"><CalendarDays :size="14" class="text-amber-500" /></div>
              <div class="text-[10px] text-slate-500">Hari paling boros</div>
              <div class="text-sm font-extrabold mt-0.5">{{ insight.most_wasteful_day?.date ?? '-' }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Export Menu Bottom Sheet -->
    <Teleport to="body">
      <div v-if="showExportMenu" class="fixed inset-0 bg-slate-900/45 backdrop-blur-sm z-[500] flex items-end justify-center" @click.self="showExportMenu = false">
        <div class="bg-white rounded-t-3xl w-full max-w-md p-5 pb-10">
          <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-5"></div>
          <div class="text-base font-extrabold mb-4">📤 Export Laporan {{ periodLabel }}</div>

          <a :href="route('report.export-pdf', { period })" class="flex items-center gap-3 w-full p-3.5 bg-slate-50 rounded-2xl mb-2.5 no-underline">
            <span class="text-2xl">📄</span>
            <div>
              <div class="text-sm font-bold text-slate-800">Export PDF</div>
              <div class="text-[11px] text-slate-500">Laporan rapi untuk dicetak atau dibagikan</div>
            </div>
          </a>

          <a :href="route('report.export-excel', { period })" class="flex items-center gap-3 w-full p-3.5 bg-slate-50 rounded-2xl mb-2.5 no-underline">
            <span class="text-2xl">📊</span>
            <div>
              <div class="text-sm font-bold text-slate-800">Export Excel</div>
              <div class="text-[11px] text-slate-500">Detail transaksi dalam format spreadsheet</div>
            </div>
          </a>

          <button class="flex items-center gap-3 w-full p-3.5 bg-slate-50 rounded-2xl mb-2.5 disabled:opacity-60" @click="sendWhatsApp" :disabled="sendingWa">
            <span class="text-2xl">💬</span>
            <div class="text-left">
              <div class="text-sm font-bold text-slate-800">{{ sendingWa ? 'Mengirim...' : 'Kirim ke WhatsApp' }}</div>
              <div class="text-[11px] text-slate-500">Ringkasan laporan dikirim ke nomor WA kamu</div>
            </div>
          </button>

          <button class="w-full py-3 border-1.5 border-slate-200 rounded-xl text-sm font-semibold text-slate-500" @click="showExportMenu = false">Batal</button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'
import axios from 'axios'
import {
  TrendingUp, TrendingDown, Download, Bell, ChevronDown, Calendar,
  Activity, Shield, PiggyBank, ArrowRight, Users, CalendarDays,
} from 'lucide-vue-next'

const props = defineProps({
  period: String, periodLabel: String, range: String,
  barData: Array, donutData: Array, budgets: Array,
  totalIncome: Number, totalExpense: Number,
  incomeChange: Number, expenseChange: Number,
  savingRatio: Number, budgetDiscipline: Number,
  runwayMonths: Number, runwayScore: Number,
  healthScore: Number, healthStatus: String,
  insight: Object,
  months: Array,
})

const showExportMenu = ref(false)
const showMonthMenu  = ref(false)
const sendingWa       = ref(false)

const rangeOptions = [
  { value: '6months', label: '6 Bulan' },
  { value: '12months', label: '12 Bulan' },
  { value: 'year', label: 'Tahun Ini' },
  { value: 'all', label: 'Semua' },
]
const activeIndex = computed(() => rangeOptions.findIndex(o => o.value === props.range))
const segmentStyle = computed(() => ({
  width: `${100 / rangeOptions.length}%`,
  left: `${(100 / rangeOptions.length) * activeIndex.value}%`,
}))

const changeRange = (r) => router.get(route('report'), { period: props.period, range: r }, { preserveState: true, preserveScroll: true })
const changePeriod = (p) => { showMonthMenu.value = false; router.get(route('report'), { period: p, range: props.range }, { preserveState: true }) }

const sendWhatsApp = async () => {
  sendingWa.value = true
  try {
    await axios.post(route('report.send-whatsapp'), { period: props.period })
    showExportMenu.value = false
    router.reload({ only: ['flash'] })
  } catch {
  } finally {
    sendingWa.value = false
  }
}

const donutColors = ['#2F6BFF','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#84CC16']

const maxBarVal = computed(() => Math.max(...props.barData.flatMap(d => [d.income, d.expense]), 1))
const barHeight = (v) => Math.max(4, (v / maxBarVal.value) * 90)

const donutSegments = computed(() => {
  let offset = 0
  return props.donutData.map(d => {
    const length = (d.percent / 100) * 251.2
    const seg = { length, offset }
    offset += length
    return seg
  })
})

const healthColorText = computed(() => ({
  sehat: 'text-emerald-500', cukup: 'text-amber-500', perlu_perhatian: 'text-red-500',
}[props.healthStatus] ?? 'text-slate-500'))

const healthLabel = (s) => ({ sehat: 'Sehat', cukup: 'Cukup', perlu_perhatian: 'Perlu Perhatian' }[s] ?? s)
const defaultTierEmoji = (s) => ({ sehat: '✅', cukup: '⚠️', perlu_perhatian: '🔴' }[s] ?? '')

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return (n/1_000).toFixed(0) + 'rb'
  return String(Math.round(n))
}
</script>

EOT
);

echo "\n=== SELESAI MENULIS FILE ===\n";
