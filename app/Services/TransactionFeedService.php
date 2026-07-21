<?php

namespace App\Services;

use App\Http\Requests\App\DompetFilterRequest;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gabungkan feed transaksi (income/expense dari `transactions`) + transfer antar dompet
 * (dari `wallet_transfers`) jadi satu query terurut sesuai filter dompet (§2, §3, §4 spec
 * redesign halaman dompet mobile). Dipakai bareng oleh TransactionController@index
 * (paginated) dan @exportCsv (limit tanpa paginasi) supaya hasilnya konsisten.
 */
class TransactionFeedService
{
    public function buildQuery(User $user, DompetFilterRequest $request, string $range): Builder
    {
        $types = (array) $request->input('type', []);
        $hasTypeFilter = ! empty($types);
        $txTypes = $hasTypeFilter ? array_values(array_intersect($types, ['income', 'expense'])) : ['income', 'expense'];
        $includeIncomeExpense = ! empty($txTypes);
        $includeTransfer = ! $hasTypeFilter || in_array('transfer', $types, true);

        $queries = [];

        if ($includeIncomeExpense) {
            $queries[] = $this->buildTransactionQuery($user, $request, $range, $txTypes);
        }

        if ($includeTransfer) {
            $queries[] = $this->buildTransferQuery($user, $request, $range);
        }

        // Tidak pernah kosong dalam praktiknya (type.* divalidasi in:income,expense,transfer),
        // tapi fallback ke feed income/expense kalau suatu saat ada kombinasi filter yang lolos
        // validasi tanpa menyisakan satupun tipe.
        if (empty($queries)) {
            $queries[] = $this->buildTransactionQuery($user, $request, $range, ['income', 'expense']);
        }

        $query = array_shift($queries);

        foreach ($queries as $unionQuery) {
            $query->unionAll($unionQuery);
        }

        return $query->orderByDesc('event_at')->orderByDesc('event_time_hint');
    }

    /**
     * Petakan 1 baris hasil union (stdClass dari query builder) ke shape response
     * kontrak API (sama untuk item `transactions` biasa maupun transfer, lihat §4).
     */
    public function mapRow(object $row): array
    {
        $isTransfer = $row->type === 'transfer';
        $transactedAt = Carbon::parse($row->event_at);
        $timeHint = $row->event_time_hint ? Carbon::parse($row->event_time_hint) : null;

        return [
            'id' => $isTransfer ? 'wt_'.$row->id : $row->id,
            'type' => $row->type,
            'amount' => (float) $row->amount,
            'note' => $row->note,
            'category' => $row->category_name,
            'category_emoji' => $row->category_emoji,
            'category_icon_url' => $row->category_icon_path ? Storage::url($row->category_icon_path) : null,
            'wallet' => $isTransfer
                ? "{$row->from_wallet_label} → {$row->to_wallet_label}"
                : $row->wallet_label,
            'wallet_id' => $row->wallet_id,
            'category_id' => $row->category_id,
            'transacted_at' => $transactedAt->format('Y-m-d'),
            'transacted_at_label' => $transactedAt->translatedFormat('d M Y'),
            'transacted_at_time' => $timeHint?->format('H:i'),
            'source' => $row->source,
        ];
    }

    private function buildTransactionQuery(User $user, DompetFilterRequest $request, string $range, array $types): Builder
    {
        $query = DB::table('transactions as t')
            ->leftJoin('user_wallets as w', 'w.id', '=', 't.wallet_id')
            ->leftJoin('banks as b', 'b.id', '=', 'w.bank_id')
            ->leftJoin('transaction_categories as c', 'c.id', '=', 't.category_id')
            ->where('t.user_id', $user->id)
            ->whereNull('t.deleted_at')
            ->whereIn('t.type', $types);

        $this->applyDateFilter($query, 't.transacted_at', $request, $range);

        if ($request->wallet_id) {
            $query->where('t.wallet_id', $request->wallet_id);
        }

        $categoryIds = (array) $request->input('category_id', []);
        if (! empty($categoryIds)) {
            $query->whereIn('t.category_id', $categoryIds);
        }

        if ($request->balance_group) {
            $this->applySingleWalletBalanceGroup($query, 'w', 'b', $request->balance_group);
        }

        $this->applyCommonFilters($query, 't.note', 't.amount', $request);

        return $query->select([
            't.id as id',
            't.type as type',
            't.amount as amount',
            't.note as note',
            't.category_id as category_id',
            'c.name as category_name',
            'c.emoji as category_emoji',
            'c.icon_path as category_icon_path',
            't.wallet_id as wallet_id',
            'w.display_name as wallet_label',
            DB::raw('NULL as from_wallet_label'),
            DB::raw('NULL as to_wallet_label'),
            DB::raw('DATE(t.transacted_at) as event_at'),
            't.created_at as event_time_hint',
            't.source as source',
        ]);
    }

    private function buildTransferQuery(User $user, DompetFilterRequest $request, string $range): Builder
    {
        $query = DB::table('wallet_transfers as wt')
            ->leftJoin('user_wallets as fw', 'fw.id', '=', 'wt.from_wallet_id')
            ->leftJoin('user_wallets as tw', 'tw.id', '=', 'wt.to_wallet_id')
            ->leftJoin('banks as fb', 'fb.id', '=', 'fw.bank_id')
            ->leftJoin('banks as tb', 'tb.id', '=', 'tw.bank_id')
            ->where('wt.user_id', $user->id);

        $this->applyDateFilter($query, 'wt.transferred_at', $request, $range);

        if ($request->wallet_id) {
            $walletId = $request->wallet_id;
            $query->where(function ($q) use ($walletId) {
                $q->where('wt.from_wallet_id', $walletId)->orWhere('wt.to_wallet_id', $walletId);
            });
        }

        // category_id sengaja tidak diterapkan ke sub-query transfer (transfer tidak
        // punya kategori) — lihat §2 "Kalau type hanya berisi transfer, abaikan category_id".

        if ($request->balance_group) {
            $this->applyTransferBalanceGroup($query, $request->balance_group);
        }

        $this->applyCommonFilters($query, 'wt.note', 'wt.amount', $request);

        return $query->select([
            'wt.id as id',
            DB::raw("'transfer' as type"),
            'wt.amount as amount',
            'wt.note as note',
            DB::raw('NULL as category_id'),
            DB::raw('NULL as category_name'),
            DB::raw('NULL as category_emoji'),
            DB::raw('NULL as category_icon_path'),
            DB::raw('NULL as wallet_id'),
            DB::raw('NULL as wallet_label'),
            'fw.display_name as from_wallet_label',
            'tw.display_name as to_wallet_label',
            DB::raw('DATE(wt.transferred_at) as event_at'),
            'wt.transferred_at as event_time_hint',
            DB::raw("'wallet_transfer' as source"),
        ]);
    }

    private function applyCommonFilters(Builder $query, string $noteColumn, string $amountColumn, DompetFilterRequest $request): void
    {
        if ($request->search) {
            $query->where($noteColumn, 'like', '%'.$request->search.'%');
        }
        if ($request->min_amount) {
            $query->where($amountColumn, '>=', $request->min_amount);
        }
        if ($request->max_amount) {
            $query->where($amountColumn, '<=', $request->max_amount);
        }
    }

    private function applyDateFilter(Builder $query, string $column, DompetFilterRequest $request, string $range): void
    {
        if ($request->start_date && $request->end_date) {
            $query->whereDate($column, '>=', $request->start_date)
                ->whereDate($column, '<=', $request->end_date);
        } elseif ($request->period) {
            [$year, $month] = explode('-', $request->period);
            $query->whereYear($column, $year)->whereMonth($column, $month);
        } else {
            match ($range) {
                'week' => $query->whereDate($column, '>=', now()->startOfWeek()->toDateString())
                    ->whereDate($column, '<=', now()->endOfWeek()->toDateString()),
                'month' => $query->whereYear($column, now()->year)->whereMonth($column, now()->month),
                default => $query->whereDate($column, now()->toDateString()),
            };
        }
    }

    /**
     * Klasifikasi kelompok saldo untuk 1 dompet (baris `transactions`), konsisten dengan
     * cash_total/bank_total/ewallet_total di TransactionController@index (§3).
     */
    private function applySingleWalletBalanceGroup(Builder $query, string $walletAlias, string $bankAlias, string $group): void
    {
        match ($group) {
            'cash' => $query->whereNull("{$walletAlias}.bank_id"),
            'bank' => $query->whereNotNull("{$walletAlias}.bank_id")->where("{$bankAlias}.type", '!=', 'digital'),
            'ewallet' => $query->where("{$bankAlias}.type", 'digital'),
            default => null,
        };
    }

    /**
     * Untuk transfer (§4): cocokkan bila salah satu dari from_wallet/to_wallet masuk
     * kelompok saldo yang dipilih.
     */
    private function applyTransferBalanceGroup(Builder $query, string $group): void
    {
        $query->where(function ($q) use ($group) {
            match ($group) {
                'cash' => $q->whereNull('fw.bank_id')->orWhereNull('tw.bank_id'),
                'bank' => $q->where(fn ($qq) => $qq->whereNotNull('fw.bank_id')->where('fb.type', '!=', 'digital'))
                    ->orWhere(fn ($qq) => $qq->whereNotNull('tw.bank_id')->where('tb.type', '!=', 'digital')),
                'ewallet' => $q->where('fb.type', 'digital')->orWhere('tb.type', 'digital'),
                default => null,
            };
        });
    }
}
