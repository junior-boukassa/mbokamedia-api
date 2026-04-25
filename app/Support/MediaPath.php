<?php

namespace App\Support;

class MediaPath
{
    public static function normalize(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $trimmed) === 1) {
            $parsedPath = parse_url($trimmed, PHP_URL_PATH);

            if (! is_string($parsedPath) || $parsedPath === '') {
                return $trimmed;
            }

            $trimmed = $parsedPath;
        }

        $normalized = '/'.ltrim($trimmed, '/');

        if (str_starts_with($normalized, '/storage/')) {
            return ltrim(substr($normalized, strlen('/storage/')), '/');
        }

        return ltrim($normalized, '/');
    }
}
