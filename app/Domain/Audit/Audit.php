<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Audit
{
    /** Never persisted in audit payloads. */
    private const SECRET_KEYS = ['password', 'remember_token', 'token', 'access_token', 'refresh_token', 'id_token', 'secret', 'client_secret'];

    public static function record(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => self::scrub($before),
            'after' => self::scrub($after),
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    private static function scrub(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $data = Arr::except($data, self::SECRET_KEYS);

        // Long HTML bodies bloat the log; revisions hold full snapshots anyway.
        array_walk_recursive($data, function (&$value) {
            if (is_string($value) && mb_strlen($value) > 2000) {
                $value = mb_substr($value, 0, 2000).'…';
            }
        });

        return $data;
    }
}
