<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging (HTTP v1). Service account JSON orqali OAuth2 token olinadi
 * (RS256 JWT, qo'shimcha paketsiz). Sozlanmagan bo'lsa barcha metodlar jim o'tadi.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return filled(config('savdodaftar.push.fcm_project_id')) && $this->credentials() !== null;
    }

    /**
     * Bitta qurilmaga yuboradi. Token yaroqsiz bo'lsa (UNREGISTERED/NOT_FOUND) bazadan o'chiriladi.
     *
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $accessToken = $this->accessToken();
            $project = config('savdodaftar.push.fcm_project_id');

            $response = Http::withToken($accessToken)->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'high'],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            $status = $response->json('error.status');

            if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                DeviceToken::where('token', $token)->delete();
            } else {
                Log::warning('FCM xatoligi: '.$response->body());
            }
        } catch (Throwable $e) {
            Log::warning('FCM yuborishda xatolik: '.$e->getMessage());
        }

        return false;
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $path = config('savdodaftar.push.fcm_credentials');

        if (blank($path) || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', 3000, function () {
            $credentials = $this->credentials();
            $now = time();

            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}.".$this->base64Url($signature),
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
