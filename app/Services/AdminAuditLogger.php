<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Support\AdminRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AdminAuditLogger
{
    public function log(
        string $action,
        ?User $actor = null,
        ?Request $request = null,
        string $status = 'success',
        Model|string|null $target = null,
        ?string $targetType = null,
        string|int|null $targetId = null,
        ?string $targetLabel = null,
        array $metadata = [],
        ?string $actorName = null,
        ?string $actorEmail = null,
        ?array $actorRoles = null,
    ): void {
        if (! config('admin.audit.enabled', true)) {
            return;
        }

        $request ??= request();
        $actor ??= $request?->user();
        $resolvedTarget = $this->resolveTarget($target);

        AdminAuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actorName ?? $actor?->name,
            'actor_email' => $actorEmail ?? $actor?->email,
            'actor_roles' => $actorRoles ?? ($actor ? AdminRoles::normalizeMany($actor->getRoleNames())->all() : []),
            'action' => $action,
            'target_type' => $targetType ?? $resolvedTarget['type'],
            'target_id' => ($targetId ?? $resolvedTarget['id']) !== null ? (string) ($targetId ?? $resolvedTarget['id']) : null,
            'target_label' => $targetLabel ?? $resolvedTarget['label'],
            'status' => $status,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{type: ?string, id: string|int|null, label: ?string}
     */
    protected function resolveTarget(Model|string|null $target): array
    {
        if ($target instanceof Model) {
            return [
                'type' => Str::snake(class_basename($target)),
                'id' => $target->getKey(),
                'label' => $this->resolveModelLabel($target),
            ];
        }

        if (is_string($target) && $target !== '') {
            return [
                'type' => $target,
                'id' => null,
                'label' => null,
            ];
        }

        return [
            'type' => null,
            'id' => null,
            'label' => null,
        ];
    }

    protected function resolveModelLabel(Model $target): ?string
    {
        return Arr::first([
            $target->getAttribute('name'),
            $target->getAttribute('title'),
            $target->getAttribute('email'),
            $target->getAttribute('slug'),
            $target->getAttribute('id'),
        ], static fn ($value): bool => filled($value));
    }
}
