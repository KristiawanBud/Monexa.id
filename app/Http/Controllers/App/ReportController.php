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
