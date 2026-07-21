<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\TransactionCategory;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $bills = $user->bills()
            ->where('is_active', true)
            ->orderBy('type')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'emoji' => $b->emoji,
                'type' => $b->type,
                'amount' => (float) $b->amount,
                'due_day' => $b->due_day,
                'due_date' => $b->due_date?->format('Y-m-d'),
                'remind_days' => $b->remind_days,
                'days_until_due' => $b->days_until_due,
                'status_color' => $b->status_color,
                'last_paid_at' => $b->last_paid_at?->format('d M Y'),
                'is_paid_this_month' => $b->last_paid_at?->isCurrentMonth() ?? false,
            ]);

        return Inertia::render('App/Bills', [
            'bills' => $bills,
            'wallets' => $user->activeWallets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'type' => ['required', 'in:recurring,one_time'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['nullable', 'date'],
            'remind_days' => ['required', 'array'],
            'remind_days.*' => ['integer', 'min:0', 'max:30'],
            'notif_wa_enabled' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'type' => ['required', 'in:recurring,one_time'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['nullable', 'date'],
            'remind_days' => ['required', 'array'],
            'remind_days.*' => ['integer', 'min:0', 'max:30'],
            'notif_wa_enabled' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->bills()->create($validated);

        return back()->with('success', 'Tagihan berhasil ditambahkan!');
    }

    public function update(Request $request, Bill $bill): RedirectResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['nullable', 'date'],
            'remind_days' => ['required', 'array'],
            'notif_wa_enabled' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['nullable', 'date'],
            'remind_days' => ['required', 'array'],
            'notif_wa_enabled' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $bill->update($validated);

        return back()->with('success', 'Tagihan berhasil diupdate!');
    }

    public function pay(Request $request, Bill $bill): RedirectResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $request->validate([
            'wallet_id' => ['required', 'exists:user_wallets,id'],
            'amount_paid' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['required', 'date'],
        ]);

        $user = $request->user();
        $forPeriod = now()->format('Y-m');

        $alreadyPaid = BillPayment::where('bill_id', $bill->id)
            ->where('for_period', $forPeriod)
            ->exists();

        if ($alreadyPaid) {
            return back()->with(
                'error',
                'Tagihan untuk periode bulan ini sudah dilunasi!'
            );
        }

        $tagCategory = TransactionCategory::where('type', 'expense')
            ->where('is_system', true)
            ->where(function ($q) {
                $q->where('name', 'Tagihan')
                    ->orWhere('name', 'tagihan')
                    ->orWhereRaw('LOWER(name) = ?', ['tagihan']);
            })
            ->first();

        $categoryId = $tagCategory?->id;

        try {
            DB::transaction(function () use ($request, $bill, $user, $categoryId, $forPeriod) {
                $transaction = $user->transactions()->create([
                    'wallet_id' => $request->wallet_id,
                    'category_id' => $categoryId,
                    'type' => 'expense',
                    'amount' => $request->amount_paid,
                    'note' => 'Bayar tagihan: '.$bill->name,
                    'transacted_at' => $request->paid_at,
                    'source' => 'bill_payment',
                    'created_by' => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                BillPayment::create([
                    'bill_id' => $bill->id,
                    'wallet_id' => $request->wallet_id,
                    'transaction_id' => $transaction->id,
                    'amount_paid' => $request->amount_paid,
                    'paid_at' => $request->paid_at,
                    'source' => 'manual',
                    'for_period' => $forPeriod,
                ]);

                $bill->update(['last_paid_at' => $request->paid_at]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Tagihan {$bill->name} berhasil ditandai lunas!");
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $bill->update(['is_active' => false]);

        return back()->with('success', 'Tagihan berhasil dihapus.');
    }
}
