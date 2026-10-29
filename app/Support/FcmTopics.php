<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Noms des sujets FCM auxquels l'application abonne ses lecteurs.
 *
 * Même règle que côté Flutter (`PushTopicsService.categoryTopic`) : un nom de
 * sujet n'admet que `[a-zA-Z0-9-_.~%]`, tout le reste devient `-`. Les deux
 * côtés doivent produire exactement la même chaîne, sans quoi un lecteur serait
 * abonné à un sujet que le serveur n'alimente jamais.
 */
class FcmTopics
{
    public const CATEGORY_PREFIX = 'category-';

    /** Sujet des lecteurs qui veulent être prévenus d'un nouveau reel. */
    public const REELS = 'reels';

    /** FCM limite une condition à cinq sujets. */
    public const MAX_IN_CONDITION = 5;

    public static function sanitize(string $name): string
    {
        // Drapeau `u` : on remplace par caractère, comme Dart, et non par octet
        // (un « é » occupe deux octets en UTF-8 et donnerait deux tirets).
        return preg_replace('/[^a-zA-Z0-9\-_.~%]/u', '-', $name) ?? '';
    }

    public static function category(string $slug): string
    {
        return self::CATEGORY_PREFIX.self::sanitize($slug);
    }

    public static function isValid(string $topic): bool
    {
        return preg_match('/^[a-zA-Z0-9\-_.~%]+$/', $topic) === 1;
    }

    /**
     * Condition FCM qui vise les appareils abonnés à au moins un des sujets :
     * `'all-users' in topics || 'category-politique' in topics`.
     *
     * Un appareil abonné à plusieurs de ces sujets ne reçoit le message qu'une
     * fois, ce qu'un envoi par sujet ne permettrait pas d'éviter.
     *
     * @param  array<int, string>  $topics
     */
    public static function condition(array $topics): string
    {
        $topics = array_values(array_unique($topics));

        if ($topics === [] || count($topics) > self::MAX_IN_CONDITION) {
            throw new InvalidArgumentException('Une condition FCM porte sur 1 à 5 sujets.');
        }

        foreach ($topics as $topic) {
            // Garde-fou : le nom est inséré entre apostrophes dans l'expression.
            if (! self::isValid($topic)) {
                throw new InvalidArgumentException("Nom de sujet FCM invalide : {$topic}");
            }
        }

        return implode(' || ', array_map(
            static fn (string $topic): string => "'{$topic}' in topics",
            $topics,
        ));
    }
}
