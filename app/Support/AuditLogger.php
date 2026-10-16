<?php

namespace App\Support;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\League\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(
        Request $request,
        string $action,
        Model $subject,
        ?League $league = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'league_id' => $league?->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $this->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitize($newValues) ?: null,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $values): array
    {
        $blocked = ['password', 'password_confirmation', 'remember_token', 'token', 'secret', 'api_key', 'private_key'];
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) $values[$key] = '[PROTEGIDO]';
            elseif (is_array($value)) $values[$key] = $this->sanitize($value);
        }
        return $values;
    }
}
