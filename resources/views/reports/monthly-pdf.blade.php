<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $periodLabel }}</title>
    <style>
        @page { margin: 30px; }
        body { font-family: 'Helvetica', sans-serif; color: #0F172A; font-size: 12px; }
        .header { background: #2563EB; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; font-size: 12px; opacity: .85; }
        .summary { display: table; width: 100%; margin-bottom: 20px; }
        .summary-item { display: table-cell; width: 33%; padding: 12px; background: #F8FAFC; border-radius: 8px; }
        .summary-label { font-size: 10px; color: #64748B; text-transform: uppercase; }
        .summary-val { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .income { color: #22C55E; }
        .expense { color: #EF4444; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #F1F5F9; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; color: #64748B; }
        td { padding: 8px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
        .amount-col { text-align: right; font-weight: bold; }
        .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 10px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #94A3B8; }
    </style>
</head>
<body>

    <div class="header">
        <h1>📊 Laporan Keuangan</h1>
        <p>{{ $periodLabel }} · {{ $user->name }}</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Pemasukan</div>
            <div class="summary-val income">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Pengeluaran</div>
            <div class="summary-val expense">Rp {{ number_format($totalExpense, 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Selisih</div>
            <div class="summary-val">Rp {{ number_format($selisih, 0, ',', '.') }}</div>
        </div>
    </div>

    @if($byCategory->count() > 0)
        <div class="section-title">Pengeluaran per Kategori</div>
        <table>
            <thead>
                <tr><th>Kategori</th><th class="amount-col">Jumlah</th></tr>
            </thead>
            <tbody>
                @foreach($byCategory as $cat)
                    <tr>
                        <td>{{ $cat['emoji'] }} {{ $cat['name'] }}</td>
                        <td class="amount-col">Rp {{ number_format($cat['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Detail Transaksi</div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th><th>Kategori</th><th>Catatan</th><th class="amount-col">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
                <tr>
                    <td>{{ $t->transacted_at->format('d/m/Y') }}</td>
                    <td>{{ $t->category?->emoji }} {{ $t->category?->name ?? '-' }}</td>
                    <td>{{ $t->note ?? '-' }}</td>
                    <td class="amount-col {{ $t->type === 'income' ? 'income' : 'expense' }}">
                        {{ $t->type === 'income' ? '+' : '-' }}Rp {{ number_format($t->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak dari CatatCuan pada {{ now()->translatedFormat('d F Y, H:i') }} WIB
    </div>

</body>
</html>
