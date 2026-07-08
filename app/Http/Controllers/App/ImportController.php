<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImportSession;
use App\Services\TransactionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function __construct(private TransactionImportService $importService) {}

    public function index(Request $request): \Inertia\Response
    {
        $user    = $request->user();
        $history = ImportSession::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'id'            => $s->id,
                'filename'      => $s->filename,
                'source_label'  => $s->source_label,
                'status'        => $s->status,
                'total_rows'    => $s->total_rows,
                'imported_rows' => $s->imported_rows,
                'created_at'    => $s->created_at->diffForHumans(),
            ]);

        return Inertia::render('App/Import', [
            'history' => $history,
            'wallets' => $user->activeWallets(),
        ]);
    }

    // Step 1: Upload file → parse → return preview
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
        ]);

        $user     = $request->user();
        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();
        $path     = $file->store("imports/{$user->id}", 'public');

        $session = ImportSession::create([
            'user_id'  => $user->id,
            'filename' => $filename,
            'file_path'=> $path,
            'status'   => 'uploaded',
        ]);

        // Parse file
        $result = $this->importService->parseFile($session);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'session_id' => $session->id,
            'preview'    => $result['preview'],
            'mapping'    => $result['mapping'],
            'total'      => $result['total'],
            'source'     => $session->fresh()->source_label,
        ]);
    }

    // Step 2: Konfirmasi & import
    public function confirm(Request $request, ImportSession $session)
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $request->validate([
            'wallet_id' => 'required|exists:user_wallets,id',
        ]);

        if ($session->status !== 'preview') {
            return response()->json(['success' => false, 'message' => 'Session sudah tidak valid.'], 422);
        }

        $result = $this->importService->importConfirmed(
            $session,
            $request->wallet_id,
        );

        return response()->json([
            'success'  => true,
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
            'errors'   => $result['errors'],
            'message'  => "{$result['imported']} transaksi berhasil diimport!",
        ]);
    }

    // Download template Excel
    public function downloadTemplate(Request $request, string $type = 'generic')
    {
        $templates = [
            'generic'   => 'Template_Import_CatatCuan.xlsx',
            'bca'       => 'Template_BCA_Mobile.xlsx',
            'mandiri'   => 'Template_Mandiri_Online.xlsx',
            'gopay'     => 'Template_GoPay.xlsx',
        ];

        $filename = $templates[$type] ?? $templates['generic'];
        $path     = resource_path("templates/{$filename}");

        if (!file_exists($path)) {
            // Generate template on the fly kalau belum ada
            return $this->generateTemplate($type);
        }

        return response()->download($path);
    }

    private function generateTemplate(string $type)
    {
        // Generate CSV template sederhana
        $headers = match($type) {
            'bca'     => ['Tanggal', 'Keterangan', 'Cabang/ATM', 'Debet', 'Kredit', 'Saldo'],
            'mandiri' => ['Tanggal', 'Deskripsi', 'Nominal', 'Keterangan'],
            'gopay'   => ['Tanggal', 'Kategori', 'Deskripsi', 'Jenis', 'Jumlah'],
            default   => ['Tanggal', 'Keterangan', 'Jumlah (+ Masuk / - Keluar)'],
        };

        $examples = match($type) {
            'bca'     => [['01/06/2025','Transfer Gaji','KCU Jakarta','','8000000','8000000'],
                          ['02/06/2025','Indomaret Kemang','ATM','50000','','7950000']],
            'mandiri' => [['01/06/2025','Gaji Bulan Juni','8000000','Kredit'],
                          ['02/06/2025','Belanja Alfamart','50000','Debit']],
            'gopay'   => [['01/06/2025','Transfer','Top Up dari BCA','Masuk','100000'],
                          ['02/06/2025','Makanan','GoPay Food','Keluar','35000']],
            default   => [['01/06/2025','Gaji Juni','8000000'],
                          ['02/06/2025','Makan siang','-35000']],
        };

        $csv = implode(',', $headers) . "\n";
        foreach ($examples as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"template_{$type}.csv\"");
    }
}
