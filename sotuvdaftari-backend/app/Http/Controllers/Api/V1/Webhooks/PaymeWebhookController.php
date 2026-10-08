<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * TZ 36: Payme Merchant API (JSON-RPC 2.0) webhook.
 *
 * Payme barcha so'rovlarni bitta endpoint'ga Basic auth (login: Paycom,
 * parol: kassa kaliti) bilan yuboradi. Javob har doim HTTP 200,
 * xatolik JSON ichidagi `error` obyektida qaytadi.
 */
class PaymeWebhookController extends Controller
{
    public function __invoke(Request $request, BillingService $billing): JsonResponse
    {
        $id = $request->input('id');

        if (! $this->authorized($request)) {
            return $this->error($id, -32504, 'Avtorizatsiya xato.');
        }

        $params = (array) $request->input('params', []);

        return match ((string) $request->input('method')) {
            'CheckPerformTransaction' => $this->checkPerform($id, $params),
            'CreateTransaction' => $this->create($id, $params, $billing),
            'PerformTransaction' => $this->perform($id, $params, $billing),
            'CancelTransaction' => $this->cancel($id, $params, $billing),
            'CheckTransaction' => $this->check($id, $params),
            'GetStatement' => $this->statement($id, $params),
            'ChangePassword' => $this->changePassword($id, $params),
            default => $this->error($id, -32601, 'Metod topilmadi.'),
        };
    }

    private const TIMEOUT_MS = 43_200_000;

    private function expired(Payment $payment): bool
    {
        $created = (int) data_get($payment->meta, 'server_time', 0);

        return $created > 0 && ((int) (microtime(true) * 1000) - $created) > self::TIMEOUT_MS;
    }

    private function checkPerform(mixed $id, array $params): JsonResponse
    {
        $payment = $this->findByOrder($params);

        if ($payment === null) {
            return $this->error($id, -31050, 'Buyurtma topilmadi.', $this->accountField());
        }

        if ((int) ($params['amount'] ?? 0) !== $payment->amountInTiyin()) {
            return $this->error($id, -31001, 'To\'lov summasi noto\'g\'ri.');
        }

        if (! $payment->isPending()) {
            return $this->error($id, -31051, 'Buyurtma holati to\'lovga ruxsat bermaydi.', $this->accountField());
        }

        return $this->result($id, ['allow' => true]);
    }

    private function create(mixed $id, array $params, BillingService $billing): JsonResponse
    {
        $transactionId = (string) ($params['id'] ?? '');

        $existing = Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existing !== null) {
            // 12 soatdan oshgan kutilayotgan tranzaksiya bekor qilinadi (sabab 4)
            if ((int) $existing->provider_state === 1 && $this->expired($existing)) {
                $billing->cancelPayme($existing, 4, (int) (microtime(true) * 1000));

                return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
            }

            // Takroriy so'rov — idempotent javob (faqat faol holatda)
            if ((int) $existing->provider_state !== 1) {
                return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
            }

            return $this->result($id, [
                'create_time' => (int) data_get($existing->meta, 'create_time'),
                'transaction' => (string) $existing->id,
                'state' => 1,
            ]);
        }

        $payment = $this->findByOrder($params);

        if ($payment === null) {
            return $this->error($id, -31050, 'Buyurtma topilmadi.', $this->accountField());
        }

        if ((int) ($params['amount'] ?? 0) !== $payment->amountInTiyin()) {
            return $this->error($id, -31001, 'To\'lov summasi noto\'g\'ri.');
        }

        // Buyurtma to'lovga yaroqsiz (to'langan/bekor qilingan) — account xatosi (-31050..-31099 oralig'i)
        if (! $payment->isPending() && $payment->transaction_id === null) {
            return $this->error($id, -31051, 'Buyurtma holati to\'lovga ruxsat bermaydi.', $this->accountField());
        }

        // Bitta buyurtma bo'yicha faqat bitta faol tranzaksiya (TZ 36.2). Payme spetsifikatsiyasi:
        // buyurtma boshqa tranzaksiya to'lovini kutayotgan bo'lsa -31099 qaytariladi.
        if ($payment->transaction_id !== null) {
            return $this->error($id, -31099, 'Buyurtma boshqa tranzaksiya to\'lovini kutmoqda.', $this->accountField());
        }

        $createTime = (int) ($params['time'] ?? (int) (microtime(true) * 1000));

        $payment->update([
            'transaction_id' => $transactionId,
            'provider_state' => 1,
            'meta' => array_merge($payment->meta ?? [], ['create_time' => $createTime, 'server_time' => (int) (microtime(true) * 1000)]),
        ]);

        return $this->result($id, [
            'create_time' => $createTime,
            'transaction' => (string) $payment->id,
            'state' => 1,
        ]);
    }

    private function perform(mixed $id, array $params, BillingService $billing): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        // Payme: yaratilgandan 12 soat o'tgan tranzaksiya bajarilmaydi — bekor qilinadi (sabab 4)
        if ((int) $payment->provider_state === 1 && $this->expired($payment)) {
            $billing->cancelPayme($payment, 4, (int) (microtime(true) * 1000));

            return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
        }

        // Idempotent: allaqachon bajarilgan bo'lsa o'sha natija qaytadi
        if ((int) $payment->provider_state === 2) {
            return $this->result($id, [
                'transaction' => (string) $payment->id,
                'perform_time' => (int) data_get($payment->meta, 'perform_time'),
                'state' => 2,
            ]);
        }

        if ((int) $payment->provider_state !== 1) {
            return $this->error($id, -31008, 'Tranzaksiya holati amalga ruxsat bermaydi.');
        }

        $performTime = (int) (microtime(true) * 1000);
        $billing->activate($payment, ['perform_time' => $performTime]);

        return $this->result($id, [
            'transaction' => (string) $payment->id,
            'perform_time' => $performTime,
            'state' => 2,
        ]);
    }

    private function cancel(mixed $id, array $params, BillingService $billing): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        $payment = $billing->cancelPayme($payment, (int) ($params['reason'] ?? 0), (int) (microtime(true) * 1000));

        return $this->result($id, [
            'transaction' => (string) $payment->id,
            'cancel_time' => (int) data_get($payment->meta, 'cancel_time'),
            'state' => (int) $payment->provider_state,
        ]);
    }

    private function check(mixed $id, array $params): JsonResponse
    {
        $payment = $this->findByTransaction($params);

        if ($payment === null) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi.');
        }

        return $this->result($id, [
            'create_time' => (int) data_get($payment->meta, 'create_time', 0),
            'perform_time' => (int) data_get($payment->meta, 'perform_time', 0),
            'cancel_time' => (int) data_get($payment->meta, 'cancel_time', 0),
            'transaction' => (string) $payment->id,
            'state' => (int) $payment->provider_state,
            'reason' => data_get($payment->meta, 'cancel_reason'),
        ]);
    }

    private function statement(mixed $id, array $params): JsonResponse
    {
        $from = (int) ($params['from'] ?? 0);
        $to = (int) ($params['to'] ?? 0);

        $transactions = Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->whereNotNull('transaction_id')
            ->get()
            ->filter(function (Payment $payment) use ($from, $to) {
                $time = (int) data_get($payment->meta, 'create_time', 0);

                return $time >= $from && $time <= $to;
            })
            ->map(fn (Payment $payment) => [
                'id' => $payment->transaction_id,
                'time' => (int) data_get($payment->meta, 'create_time', 0),
                'amount' => $payment->amountInTiyin(),
                'account' => [$this->accountField() => $payment->order_id],
                'create_time' => (int) data_get($payment->meta, 'create_time', 0),
                'perform_time' => (int) data_get($payment->meta, 'perform_time', 0),
                'cancel_time' => (int) data_get($payment->meta, 'cancel_time', 0),
                'transaction' => (string) $payment->id,
                'state' => (int) $payment->provider_state,
                'reason' => data_get($payment->meta, 'cancel_reason'),
            ])
            ->values();

        return $this->result($id, ['transactions' => $transactions]);
    }

    private const PASSWORD_FILE = 'payme/password';

    /** Joriy kassa kaliti: ChangePassword orqali o'zgartirilgan bo'lsa fayldagi, aks holda `.env` dagi */
    private function key(): string
    {
        $disk = Storage::disk('local');

        if ($disk->exists(self::PASSWORD_FILE)) {
            $stored = trim((string) $disk->get(self::PASSWORD_FILE));

            if ($stored !== '') {
                return $stored;
            }
        }

        return (string) config('savdodaftar.billing.payme.key');
    }

    /** Payme "Песочница"/kabinet parolni almashtirganda: yangi kalit saqlanadi va keyingi so'rovlar shu bilan tekshiriladi */
    private function changePassword(mixed $id, array $params): JsonResponse
    {
        $password = (string) ($params['password'] ?? '');

        if ($password === '') {
            return response()->json(['error' => [
                'code' => -32400,
                'message' => ['ru' => 'Недопустимый пароль.', 'uz' => 'Parol yaroqsiz.', 'en' => 'Invalid password.'],
                'data' => 'password',
            ], 'id' => $id]);
        }

        Storage::disk('local')->put(self::PASSWORD_FILE, $password);

        return $this->result($id, ['success' => true]);
    }

    private function authorized(Request $request): bool
    {
        $key = $this->key();

        if ($key === '') {
            return false;
        }

        $header = (string) $request->header('Authorization');

        if (! str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6), true) ?: '';
        [$login, $password] = array_pad(explode(':', $decoded, 2), 2, '');

        return $login === 'Paycom' && hash_equals($key, $password);
    }

    /** Payme kabinetidagi "account" maydoni kaliti (uz: byurtma_id, ru: zakaz_id nomi bilan ko'rsatiladi) */
    private function accountField(): string
    {
        return (string) config('savdodaftar.billing.payme.account_field', 'order_id');
    }

    private function findByOrder(array $params): ?Payment
    {
        $orderId = '';

        foreach (array_unique([$this->accountField(), 'order_id', 'byurtma_id', 'zakaz_id']) as $key) {
            $orderId = (string) data_get($params, "account.{$key}", '');

            if ($orderId !== '') {
                break;
            }
        }

        return $orderId === '' ? null : Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('order_id', $orderId)
            ->first();
    }

    private function findByTransaction(array $params): ?Payment
    {
        $transactionId = (string) ($params['id'] ?? '');

        return $transactionId === '' ? null : Payment::query()
            ->where('provider', Payment::PROVIDER_PAYME)
            ->where('transaction_id', $transactionId)
            ->first();
    }

    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['result' => $result, 'id' => $id]);
    }

    /** Payme xabarlarni ru/uz/en ko'rinishida kutadi */
    private const MESSAGES = [
        -32504 => ['ru' => 'Недостаточно привилегий для выполнения метода.', 'uz' => 'Avtorizatsiya xato.', 'en' => 'Insufficient privileges to perform this method.'],
        -32601 => ['ru' => 'Метод не найден.', 'uz' => 'Metod topilmadi.', 'en' => 'Method not found.'],
        -31050 => ['ru' => 'Заказ не найден.', 'uz' => 'Buyurtma topilmadi.', 'en' => 'Order not found.'],
        -31051 => ['ru' => 'Состояние заказа не позволяет оплату.', 'uz' => 'Buyurtma holati to\'lovga ruxsat bermaydi.', 'en' => 'Order state does not allow payment.'],
        -31001 => ['ru' => 'Неверная сумма платежа.', 'uz' => 'To\'lov summasi noto\'g\'ri.', 'en' => 'Incorrect amount.'],
        -31003 => ['ru' => 'Транзакция не найдена.', 'uz' => 'Tranzaksiya topilmadi.', 'en' => 'Transaction not found.'],
        -31099 => ['ru' => 'Заказ уже ожидает оплаты по другой транзакции.', 'uz' => 'Bu buyurtma bo\'yicha boshqa tranzaksiya to\'lov kutmoqda.', 'en' => 'The order is already waiting for payment in another transaction.'],
        -31008 => ['ru' => 'Невозможно выполнить операцию.', 'uz' => 'Tranzaksiya holati amalga ruxsat bermaydi.', 'en' => 'Unable to perform the operation.'],
    ];

    private function error(mixed $id, int $code, string $message, ?string $data = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => self::MESSAGES[$code] ?? ['ru' => $message, 'uz' => $message, 'en' => $message]];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json(['error' => $error, 'id' => $id]);
    }
}
