<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Keys that must never appear in audit log payloads.
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'private_key',
        'seed_phrase',
        'card_number',
        'cvv',
        'cvc',
    ];

    public function __construct(
        protected ?Request $request = null
    ) {}

    public function log(
        string $eventType,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        ?string $reason = null
    ): AuditLog {
        $actor = $actor ?? auth()->user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $this->request?->ip() ?? '127.0.0.1',
            'user_agent' => $this->request?->userAgent() ?? 'CLI/System',
            'old_values' => $this->redactSensitiveData($oldValues),
            'new_values' => $this->redactSensitiveData($newValues),
            'reason' => $reason,
        ]);
    }

    /**
     * Recursively redact sensitive keys from audit logs.
     */
    public function redactSensitiveData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            $isSensitive = is_string($key) && in_array(strtolower($key), $this->sensitiveKeys, true);

            if ($isSensitive) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->redactSensitiveData($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
