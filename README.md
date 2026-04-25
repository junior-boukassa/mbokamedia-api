# Mboka Media API

Backend API Laravel pour Mboka Media, concu pour alimenter:

- le frontend public Next.js
- le dashboard admin
- une future application mobile

## Stack

- Laravel 11
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
- `GET /api/public/breaking-news`
- `GET /api/public/settings`
- `GET /api/public/featured`
- `POST /api/public/contact`
- `POST /api/public/newsletter/subscribe`
