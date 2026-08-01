<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsService
{
    public function track(
        Request $request,
        string $event,
        ?Model $entity = null,
        array $properties = [],
        ?string $searchQuery = null
    ): void {
        try {
            if (! Schema::hasTable('analytics_events') || $this->isBot($request)) {
                return;
            }

            AnalyticsEvent::query()->create([
                'user_id' => $request->user()?->id,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'event' => mb_substr($event, 0, 80),
                'entity_type' => $entity ? class_basename($entity) : null,
                'entity_id' => $entity?->getKey(),
                'search_query' => $searchQuery !== null
                    ? mb_substr(trim($searchQuery), 0, 500)
                    : null,
                'properties' => $this->sanitizeProperties($properties),
                'path' => mb_substr($request->path(), 0, 1000),
                'referrer' => $request->headers->get('referer')
                    ? mb_substr((string) $request->headers->get('referer'), 0, 1000)
                    : null,
                'ip_hash' => $request->ip()
                    ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))
                    : null,
                'user_agent' => $request->userAgent()
                    ? mb_substr((string) $request->userAgent(), 0, 1000)
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function sanitizeProperties(array $properties): array
    {
        $blockedKeys = [
            'email',
            'phone',
            'contact_name',
            'password',
            'token',
            'notes',
        ];

        $clean = [];
        foreach ($properties as $key => $value) {
            $key = mb_substr((string) $key, 0, 100);
            if (in_array(mb_strtolower($key), $blockedKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeProperties($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = mb_substr((string) $value, 0, 1000);
            }
        }

        return $clean;
    }

    private function isBot(Request $request): bool
    {
        $agent = mb_strtolower((string) $request->userAgent());

        return $agent !== '' && preg_match('/bot|crawler|spider|slurp|preview|monitor/u', $agent) === 1;
    }
}
