<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(): \Inertia\Response
    {
        $totalUsers     = User::where('role', 'user')->count();
        $activeUsers    = User::whereHas('subscription', fn($q) => $q->where('status','active'))->count();
        $trialUsers     = User::whereHas('subscription', fn($q) => $q->where('plan','trial')->where('status','active'))->count();
        $paidUsers      = User::whereHas('subscription', fn($q) => $q->whereIn('plan',['monthly','yearly'])->where('status','active'))->count();
        $totalTx        = Transaction::count();
        $newUsersToday  = User::whereDate('created_at', today())->count();

        // Revenue bulan ini
        $revenueThisMonth = Subscription::whereIn('plan',['monthly','yearly'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // Bar chart 6 bulan
        $revenueChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $revenueChart[] = [
                'month'   => $d->format('M'),
                'revenue' => (float) Subscription::whereIn('plan',['monthly','yearly'])
                    ->whereMonth('created_at', $d->month)
                    ->whereYear('created_at', $d->year)
                    ->sum('amount'),
                'users'   => (int) User::whereMonth('created_at', $d->month)
                    ->whereYear('created_at', $d->year)
                    ->count(),
            ];
        }

        // Recent activity
        $recentUsers = User::where('role','user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id','name','email','created_at']);

        return Inertia::render('Admin/Dashboard', compact(
            'totalUsers','activeUsers','trialUsers','paidUsers',
            'totalTx','newUsersToday','revenueThisMonth',
            'revenueChart','recentUsers'
        ));
    }
}
