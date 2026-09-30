<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = now()->startOfDay();
        $paid = fn () => Payment::where('status', Payment::STATUS_PAID)->where('provider', '!=', Payment::PROVIDER_BONUS);

        $stats = [
            'users_total' => User::where('is_admin', false)->count(),
            'users_today' => User::where('is_admin', false)->where('created_at', '>=', $today)->count(),
            'users_week' => User::where('is_admin', false)->where('created_at', '>=', now()->subDays(7))->count(),
            'users_active_week' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
            'users_blocked' => User::whereNotNull('blocked_at')->count(),
            'standard_active' => Subscription::active()->where('plan', Subscription::PLAN_STANDARD)->distinct('user_id')->count('user_id'),
            'pro_active' => Subscription::active()->where('plan', Subscription::PLAN_PRO)->distinct('user_id')->count('user_id'),
            'revenue_today' => (float) $paid()->where('paid_at', '>=', $today)->sum('amount'),
            'revenue_month' => (float) $paid()->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'revenue_total' => (float) $paid()->sum('amount'),
            'withdrawals_pending' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->count(),
            'withdrawals_pending_sum' => (float) Withdrawal::where('status', Withdrawal::STATUS_PENDING)->sum('amount'),
            'bonus_liability' => (float) User::sum('bonus_balance'),
            'leads_new' => \App\Models\Lead::where('status', 'new')->count(),
            'customers' => Customer::count(),
            'products' => Product::count(),
            'sales_count' => Sale::count(),
            'sales_total' => (float) Sale::sum('total'),
            'debts_open' => (float) Debt::query()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as open')->value('open'),
        ];

        // Oxirgi 14 kun: ro'yxatdan o'tganlar va tushum
        $days = collect(range(13, 0))->map(fn (int $i) => Carbon::today()->subDays($i));
        $signups = User::where('is_admin', false)->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $revenue = $paid()->where('paid_at', '>=', $days->first())
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as s')->groupBy('d')->pluck('s', 'd');

        $chart = $days->map(fn (Carbon $day) => [
            'label' => $day->format('d.m'),
            'signups' => (int) ($signups[$day->toDateString()] ?? 0),
            'revenue' => (float) ($revenue[$day->toDateString()] ?? 0),
        ]);

        $latestUsers = User::where('is_admin', false)->latest('id')->limit(8)->get();
        $latestPayments = Payment::with('user')->where('status', Payment::STATUS_PAID)->latest('id')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'chart', 'latestUsers', 'latestPayments'));
    }
}
