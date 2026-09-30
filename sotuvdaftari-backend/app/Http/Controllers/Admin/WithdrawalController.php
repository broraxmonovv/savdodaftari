<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Referral\BonusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private readonly BonusService $bonuses) {}

    public function index(Request $request)
    {
        $status = $request->query('status', Withdrawal::STATUS_PENDING);

        $withdrawals = Withdrawal::with('user')
            ->when(in_array($status, [Withdrawal::STATUS_PENDING, Withdrawal::STATUS_PAID, Withdrawal::STATUS_REJECTED], true),
                fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.withdrawals', compact('withdrawals', 'status'));
    }

    public function paid(Request $request, int $id): RedirectResponse
    {
        return $this->process($request, $id, 'markPaid', "So'rov to'langan deb belgilandi.");
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->process($request, $id, 'reject', "So'rov rad etildi, summa foydalanuvchi balansiga qaytdi.");
    }

    private function process(Request $request, int $id, string $method, string $message): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bonuses->{$method}(Withdrawal::findOrFail($id), $request->user(), $data['note'] ?? null);
        } catch (ApiException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
