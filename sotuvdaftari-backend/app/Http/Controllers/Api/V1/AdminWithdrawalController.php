<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminWithdrawalResource;
use App\Models\Withdrawal;
use App\Services\Referral\BonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin: bonusni yechib olish so'rovlarini ko'rish va yopish */
class AdminWithdrawalController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly BonusService $bonuses) {}

    /** GET /admin/withdrawals?status=pending|paid|rejected */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([Withdrawal::STATUS_PENDING, Withdrawal::STATUS_PAID, Withdrawal::STATUS_REJECTED])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Withdrawal::query()->with('user')->latest('id');

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $this->paginated($query->paginate($data['per_page'] ?? 30), AdminWithdrawalResource::class);
    }

    /** POST /admin/withdrawals/{id}/paid {note?} — pul kartaga o'tkazildi */
    public function paid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $withdrawal = $this->bonuses->markPaid(Withdrawal::findOrFail($id), $request->user(), $data['note'] ?? null);

        return $this->success(new AdminWithdrawalResource($withdrawal->load('user')), __('messages.bonus.marked_paid'));
    }

    /** POST /admin/withdrawals/{id}/reject {note?} — summa foydalanuvchi balansiga qaytadi */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $withdrawal = $this->bonuses->reject(Withdrawal::findOrFail($id), $request->user(), $data['note'] ?? null);

        return $this->success(new AdminWithdrawalResource($withdrawal->load('user')), __('messages.bonus.rejected'));
    }
}
