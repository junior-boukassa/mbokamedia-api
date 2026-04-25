<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminRoles
{
    public static function allowed(): array
    {
        return config('admin.allowed_roles', []);
    }

    public static function normalize(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $trimmedRole = trim($role);

        if ($trimmedRole === '') {
            return null;
        }

        if (in_array($trimmedRole, self::allowed(), true)) {
            return $trimmedRole;
        }

        $aliases = collect(config('admin.role_aliases', []))
            ->mapWithKeys(static fn (string $normalizedRole, string $alias): array => [
                $alias => $normalizedRole,
                Str::lower($alias) => $normalizedRole,
            ]);

        return $aliases->get($trimmedRole)
            ?? $aliases->get(Str::lower($trimmedRole))
            ?? $trimmedRole;
    }

    /**
     * @param iterable<int, string> $roles
     * @return Collection<int, string>
     */
    public static function normalizeMany(iterable $roles): Collection
    {
        return collect($roles)
            ->filter(static fn ($role): bool => is_string($role))
            ->map(static fn (string $role): ?string => self::normalize($role))
            ->filter()
            ->unique()
            ->values();
    }

    public static function matchingNames(string $normalizedRole): array
    {
        return collect(config('admin.role_aliases', []))
            ->filter(static fn (string $mappedRole): bool => $mappedRole === $normalizedRole)
            ->keys()
            ->push($normalizedRole)
            ->unique()
            ->values()
            ->all();
    }
}
