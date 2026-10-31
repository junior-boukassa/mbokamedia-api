# Mboka Media API

Backend API Laravel pour Mboka Media, concu pour alimenter:

- le frontend public Next.js
- le dashboard admin
- une future application mobile

## Stack

- Laravel 12
- PHP 8.3+
- PostgreSQL en local / MySQL pour le deploiement Hostinger
- Laravel Sanctum
- JWT via `tymon/jwt-auth` pour l'API versionnee `/api/v1`
- spatie/laravel-permission
- API Resources
- Form Requests
- Policies

## Modules couverts

- Auth API
- Utilisateurs / roles / permissions
- Categories
- Tags
- Articles
- Videos
- Briefings audio
- Breaking news
- Media
- Contact
- Newsletter
- Settings
- Featured sections
- Dashboard stats

## Lancement local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve
```

## Compte admin local

Le seed admin local lit toujours ces variables depuis votre fichier `.env` local:

- `DEFAULT_ADMIN_NAME`
- `DEFAULT_ADMIN_EMAIL`
- `DEFAULT_ADMIN_PASSWORD`

Les valeurs présentes dans `.env.example` sont uniquement des placeholders de développement.

Exemple de workflow local:

```bash
cp .env.example .env
# modifiez ensuite DEFAULT_ADMIN_EMAIL et DEFAULT_ADMIN_PASSWORD dans .env
php artisan migrate --seed
```

Si la base existe déjà et que vous voulez recréer ou mettre à jour uniquement le compte admin local:

```bash
php artisan db:seed --class=AdminUserSeeder
```

Important:

- ne commitez jamais votre `.env`
- ne documentez pas un vrai mot de passe admin dans le dépôt
- utilisez des secrets locaux différents selon l’environnement

## Outils d'integration

- Collection Postman: [postman/Mboka Media API.postman_collection.json](/Users/pro/MM/mboka-media-api/postman/Mboka%20Media%20API.postman_collection.json)
- Environment Postman: [postman/Mboka Media API.postman_environment.json](/Users/pro/MM/mboka-media-api/postman/Mboka%20Media%20API.postman_environment.json)
- Documentation frontend public: [docs/frontend-public-api.md](/Users/pro/MM/mboka-media-api/docs/frontend-public-api.md)

## Tests

```bash
php artisan test
```

## Endpoints publics principaux

- `GET /api/public/articles`
- `GET /api/public/articles/{slug}`
- `GET /api/public/categories`
- `GET /api/public/videos`
- `GET /api/public/videos/{slug}`
- `GET /api/public/briefings`
- `GET /api/public/briefings/latest`
- `GET /api/public/briefings/{id}`
- `GET /api/public/briefings/{id}/audio` (requêtes HTTP Range)
- `GET /api/public/breaking-news`
- `GET /api/public/settings`
- `GET /api/public/featured`
- `POST /api/public/contact`
- `POST /api/public/newsletter/subscribe`

Les routes d'administration `/api/admin/briefings` nécessitent une session
administrateur et les permissions éditoriales d'articles existantes. L'audio
est contrôlé puis converti en arrière-plan par `briefings:process-audio`.
Le traitement réutilise `REEL_FFMPEG_BINARY`, `REEL_FFPROBE_BINARY` et le
verrou `reel-transcoding.worker_lock_file` déjà utilisés par les reels.

## Briefings — configuration et déploiement

Les limites audio sont configurables dans l'environnement API :

```dotenv
BRIEFING_AUDIO_MAX_UPLOAD_BYTES=20971520
BRIEFING_AUDIO_MAX_OUTPUT_BYTES=26214400
BRIEFING_AUDIO_TIMEOUT_SECONDS=120
BRIEFING_AUDIO_PROBE_TIMEOUT_SECONDS=15
BRIEFING_AUDIO_MAX_ATTEMPTS=3
```

Vérifier que les deux chemins `REEL_FFMPEG_BINARY` et
`REEL_FFPROBE_BINARY` pointent vers les binaires déjà utilisés par les reels,
et que PHP peut exécuter ces fichiers et écrire dans `storage/app/public`.
Le planificateur Laravel exécute un traitement audio au plus par minute.
L'hébergement doit déjà lancer `php artisan schedule:run` chaque minute;
aucune tâche cron audio distincte n'est nécessaire.

Avant déploiement, sauvegarder la base ainsi que
`storage/app/public/briefings/`. Déployer le code API et admin puis appliquer
`php artisan migrate --force`, vider/reconstruire le cache de configuration et
vérifier le planificateur. En cas de retour arrière, restaurer le code précédent
et garder la migration ainsi que les données tant qu'une sauvegarde vérifiée
n'est pas disponible : `migrate:rollback` supprime les éditions et leurs liens.
Les sources sont supprimées après conversion réussie; elles sont gardées en cas
d'échec jusqu'à une reprise ou suppression éditoriale. Un ancien audio remplacé
est supprimé après le succès du nouvel encodage, et les suppressions ne visent
que les chemins gérés sous `briefings/sources/` et `briefings/audio/`.

## Vérifications d'accès et déploiement Hostinger

L'API V1 laisse la lecture des articles publiés accessible sans compte. Les
créations et modifications exigent les permissions d'article existantes; la
publication et la programmation exigent `edit articles`, et la suppression
exige `delete articles`. L'inscription publique ne confère aucun rôle éditorial.

Sur l'hébergement Hostinger, le document root `public_html/storage` suit le
lien symbolique vers `laravel-api/storage/app/public`. Le répertoire
`laravel-api` doit rester traversable par LiteSpeed. Le 31 octobre 2026, son
mode `700` empêchait la traversée et provoquait des `404` sur les médias ;
le passage ciblé de ce seul répertoire à `711` a rétabli l'accès. Lors des
déploiements, ne remplacez pas ce mode par `700` et n'appliquez jamais de
changement récursif de permissions. Vérifiez le mode du répertoire avant et
après extraction :

```sh
stat -c '%a %n' /home/ACCOUNT/domains/api.mbokamedia.com/laravel-api
# attendu : 711
```

Après chaque déploiement API, contrôlez plusieurs images existantes d'époques
différentes à partir des chemins renvoyés par `GET /api/public/articles`.
Chaque URL doit répondre `200` avec un `Content-Type: image/*`; vérifiez aussi
qu'elles s'affichent dans le site public et l'administration. Contrôlez un reel
publié (URL vidéo lisible, octets/range servis) et, s'il existe, un briefing
publié/prêt (audio `200` ou `206` avec `Content-Type: audio/mp4`). Ne validez
pas seulement le statut de la page : chargez le contenu média lui-même.
