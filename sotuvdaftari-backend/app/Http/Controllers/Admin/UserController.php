<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusTransaction;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $plan = $request->query('plan');
        $status = $request->query('status');

        $users = User::query()
            ->where('is_admin', false)
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('phone', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('shop_name', 'like', "%{$q}%");
            }))
            ->when(in_array($plan, ['standard', 'pro'], true), fn ($query) => $query->whereHas(
                'subscriptions', fn ($s) => $s->active()->where('plan', $plan)
            ))
            ->when($plan === 'free', fn ($query) => $query->whereDoesntHave('subscriptions', fn ($s) => $s->active()))
            ->when($status === 'blocked', fn ($query) => $query->whereNotNull('blocked_at'))
            ->when($status === 'active', fn ($query) => $query->whereNull('blocked_at'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'plan', 'status'));
    }

    /** Foydalanuvchi ma'lumotlari va uning ilovadagi ma'lumotlari (faqat ko'rish) */
    public function show(int $id)
    {
        $user = User::where('is_admin', false)->findOrFail($id);

        $counts = [
            'customers' => Customer::forUser($user)->count(),
            'debts_open' => (float) Debt::forUser($user)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as o')->value('o'),
            'products' => Product::forUser($user)->count(),
            'sales' => Sale::forUser($user)->count(),
            'sales_total' => (float) Sale::forUser($user)->sum('total'),
            'expenses_total' => (float) Expense::forUser($user)->sum('amount'),
        ];

        return view('admin.users.show', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'subscription' => $user->activeSubscription(),
            'subscriptions' => $user->subscriptions()->latest('id')->limit(10)->get(),
            'payments' => Payment::forUser($user)->latest('id')->limit(10)->get(),
            'bonuses' => BonusTransaction::forUser($user)->latest('id')->limit(10)->get(),
            'referrals' => User::where('referred_by_id', $user->id)->latest('id')->limit(10)->get(),
            'referrer' => $user->referrer,
            'withdrawals' => $user->withdrawals()->latest('id')->limit(10)->get(),
            'customers' => Customer::forUser($user)->latest('id')->limit(10)->get(),
            'debts' => Debt::forUser($user)->with('customer')->latest('id')->limit(10)->get(),
            'products' => Product::forUser($user)->latest('id')->limit(10)->get(),
            'sales' => Sale::forUser($user)->latest('id')->limit(10)->get(),
            'expenses' => Expense::forUser($user)->latest('id')->limit(10)->get(),
            'tokens' => $user->tokens()->latest('id')->limit(5)->get(),
            'counts' => $counts,
        ]);
    }

    public function block(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $user = User::where('is_admin', false)->findOrFail($id);

        $user->forceFill(['blocked_at' => now(), 'block_reason' => $data['reason'] ?? null])->save();
        $user->tokens()->delete(); // barcha qurilmalardan chiqarib yuboriladi

        return back()->with('status', 'Foydalanuvchi bloklandi.');
    }

    public function unblock(int $id): RedirectResponse
    {
        User::where('is_admin', false)->findOrFail($id)
            ->forceFill(['blocked_at' => null, 'block_reason' => null])->save();

        return back()->with('status', 'Foydalanuvchi blokdan chiqarildi.');
    }

    /** Admin qo'lda tarif beradi (sinov, kompensatsiya): shu tarif faol bo'lsa muddati uzayadi */
    public function grantPlan(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(Subscription::PAID_PLANS)],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);
        $user = User::where('is_admin', false)->findOrFail($id);

        $active = $user->subscriptions()->active()->where('plan', $data['plan'])->orderByDesc('expires_at')->first();

        if ($active !== null) {
            $active->update(['expires_at' => $active->expires_at->copy()->addDays((int) $data['days'])]);
        } else {
            Subscription::create([
                'user_id' => $user->id,
                'plan' => $data['plan'],
                'status' => Subscription::STATUS_ACTIVE,
                'started_at' => now(),
                'expires_at' => now()->addDays((int) $data['days']),
            ]);
        }

        return back()->with('status', "{$data['plan']} tarifi {$data['days']} kunga berildi.");
    }
}
