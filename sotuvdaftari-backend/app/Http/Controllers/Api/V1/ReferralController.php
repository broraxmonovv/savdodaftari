<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Models\BonusTransaction;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Referal havola va bonus balansi */
class ReferralController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly ReferralService $referrals) {}

    /** GET /referral — kod, havola, foiz va statistika */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'code' => $this->referrals->codeFor($user),
            'link' => $this->referrals->link($user),
            'percent' => $this->referrals->percent(),
        ] + $this->referrals->stats($user));
    }

    /** GET /bonuses?per_page= — balans va bonus tarixi */
    public function bonuses(Request $request): JsonResponse
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $user = $request->user();

        $query = BonusTransaction::forUser($user)->with('fromUser')->latest('id');

        return $this->paginated(
            $query->paginate($data['per_page'] ?? 30),
            BonusTransactionResource::class,
            ['balance' => (float) $user->fresh()->bonus_balance],
        );
    }
}
