<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebasePushService
{
    public function sendToUser(User $user, string $title, string $body, ?string $url = null, array $data = []): void
    {
        if (! config('palomnik.firebase.enabled')) {
            return;
        }

        $devices = $user->pushDevices()->get();
        if ($devices->isEmpty()) {
            return;
        }

        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->accessToken($serviceAccount);
        $projectId = $serviceAccount['project_id'] ?? config('palomnik.firebase.project_id');

        if (! $projectId) {
            throw new RuntimeException('Firebase project_id is not configured.');
        }

        foreach ($devices as $device) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $device->token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', [
                            ...$data,
                            'url' => $url ?: '',
                        ]),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'pilgrim_notifications',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'content-available' => 1,
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                continue;
            }

            $errorCode = data_get($response->json(), 'error.details.0.errorCode');
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $device->delete();
                continue;
            }

            Log::warning('FCM delivery failed', [
                'device_id' => $device->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }
    }

    private function serviceAccount(): array
    {
        $path = config('palomnik.firebase.service_account_path');
        if (! $path) {
            throw new RuntimeException('FIREBASE_SERVICE_ACCOUNT_PATH is not configured.');
        }

        $fullPath = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        if (! is_file($fullPath)) {
            throw new RuntimeException('Firebase service account file was not found.');
        }

        $decoded = json_decode((string) file_get_contents($fullPath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Firebase service account JSON is invalid.');
        }

        return $decoded;
    }

    private function accessToken(array $serviceAccount): string
    {
        $cacheKey = 'firebase_access_token_'.sha1((string) ($serviceAccount['client_email'] ?? 'default'));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($serviceAccount) {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claims;

            $signature = '';
            $signed = openssl_sign($unsigned, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            if (! $signed) {
                throw new RuntimeException('Unable to sign Firebase OAuth assertion.');
            }

            $assertion = $unsigned.'.'.$this->base64Url($signature);
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Unable to obtain Firebase access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
