<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\ReceiptScan;
use App\Models\TransactionCategory;
use App\Services\GeminiService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptScanController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scans = ReceiptScan::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'image_url' => $s->image_url,
                'status' => $s->status,
                'parsed_result' => $s->parsed_result,
                'created_at' => $s->created_at->diffForHumans(),
            ]);

        return Inertia::render('App/ReceiptScan', [
            'scans' => $scans,
            'wallets' => $user->activeWallets(),
            'categories' => TransactionCategory::forUser($user->id)->where('type', 'expense'),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'receipt' => 'required|image|max:5120',
        ]);

        $user = $request->user();

        $path = $request->file('receipt')->store("receipts/{$user->id}", 'public');

        $scan = ReceiptScan::create([
            'user_id' => $user->id,
            'image_url' => $path,
            'status' => 'pending',
            'ai_provider' => 'gemini',
        ]);

        $base64 = base64_encode(file_get_contents(Storage::disk('public')->path($path)));
        $mime = $request->file('receipt')->getMimeType();

        $result = $this->gemini->parseReceipt($base64, $mime);

        if ($result['success'] && isset($result['data'])) {
            $scan->update([
                'status' => 'parsed',
                'parsed_result' => $result['data'],
            ]);

            return response()->json([
                'success' => true,
                'scan_id' => $scan->id,
                'data' => $result['data'],
                'message' => 'Struk berhasil dibaca!',
            ]);
        }

        $scan->update([
            'status' => 'failed',
            'error_message' => $result['error'] ?? 'Gagal membaca struk',
        ]);

        return response()->json([
            'success' => false,
            'scan_id' => $scan->id,
            'message' => 'Gagal membaca struk. Coba foto yang lebih jelas.',
        ], 422);
    }

    public function confirm(Request $request, ReceiptScan $scan)
    {
        abort_if($scan->user_id !== $request->user()->id, 403);

        $request->validate([
            'wallet_id' => 'required|exists:user_wallets,id',
            'amount' => 'required|numeric|min:1',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'note' => 'nullable|string|max:255',
            'transacted_at' => 'required|date',
        ]);

        $user = $request->user();

        try {
            \DB::transaction(function () use ($request, $scan, $user) {
                $transaction = $user->transactions()->create([
                    'wallet_id' => $request->wallet_id,
                    'category_id' => $request->category_id,
                    'type' => 'expense',
                    'amount' => $request->amount,
                    'note' => $request->note ?? ($scan->parsed_result['merchant'] ?? 'Struk scan'),
                    'transacted_at' => $request->transacted_at,
                    'source' => 'wa_receipt',
                    'created_by' => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                $scan->update([
                    'status' => 'confirmed',
                    'transaction_id' => $transaction->id,
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi dari struk berhasil disimpan!');
    }
}
