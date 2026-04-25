# Deploiement Laravel API sur Hostinger Shared Hosting

## 1. Stack livree

- Laravel 11
- MySQL
- JWT via `tymon/jwt-auth`
- CORS actif
- API versionnee sous `/api/v1`
- Fichiers `public_html` prets pour Hostinger

## 2. Endpoints disponibles

- `GET /api/v1/health`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me`
- `GET /api/v1/articles`
- `GET /api/v1/articles/{id}`
- `POST /api/v1/articles`
- `PUT /api/v1/articles/{id}`
- `PATCH /api/v1/articles/{id}`
- `DELETE /api/v1/articles/{id}`

## 3. Commandes completes

### Installation locale / build production

```bash
composer install --no-dev --optimize-autoloader
cp deploy/hostinger/.env.production.example .env
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

### Verification rapide

```bash
php artisan route:list | grep "api/v1"
php artisan about
```

## 4. Structure finale recommandee sur Hostinger

```text
domains/example.com/
├── laravel-api/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── tests/
│   ├── vendor/
│   ├── .env
│   ├── artisan
│   └── composer.json
└── public_html/
    ├── .htaccess
    ├── index.php
    └── storage -> ../laravel-api/storage/app/public
```

## 5. Etapes Hostinger tres claires

1. Dans le File Manager Hostinger, gardez le projet Laravel complet dans un dossier prive, par exemple `laravel-api`, au meme niveau que `public_html`.
2. Copiez le contenu de `deploy/hostinger/public_html/` dans le vrai dossier `public_html/`.
3. Copiez `deploy/hostinger/.env.production.example` vers `laravel-api/.env` puis remplacez:
- `APP_URL`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CORS_ALLOWED_ORIGINS`
- `API_RATE_LIMIT` si vous voulez ajuster le throttle global de l’API
- `DEFAULT_ADMIN_*` si vous utilisez l’admin legacy
4. Si vous avez acces SSH:
   - placez-vous dans `laravel-api`
   - lancez `php artisan key:generate --force`
   - lancez `php artisan jwt:secret --force`
   - lancez `php artisan migrate --force`
   - lancez `php artisan storage:link`
   - lancez `php artisan optimize`
5. Si vous n’avez pas SSH, generez `.env` localement, lancez les commandes localement sur une base identique si possible, puis uploadez le projet complet avec `vendor/`.
6. Dans `public_html/index.php`, le bootstrap cherche automatiquement `../laravel-api`, `../api`, puis `..`. Gardez de preference le nom `laravel-api` pour un demarrage direct.
7. Verifiez que les permissions d’ecriture existent sur:
   - `storage/`
   - `bootstrap/cache/`
8. Testez ensuite:
   - `https://votre-domaine.tld/api/v1/health`
   - `https://votre-domaine.tld/api/v1/auth/register`

## 6. Points securite / performance

- `APP_DEBUG=false` en production
- `QUEUE_CONNECTION=sync` pour shared hosting
- `CACHE_STORE=file` pour eviter Redis/Memcached
- `php artisan optimize` active le cache config/routes/views
- `.htaccess` bloque les fichiers caches et gere `Authorization`
- Les tokens JWT invalides/expirés renvoient du JSON `401`, pas une erreur 500

## 7. Notes JWT

- Avec `tymon/jwt-auth`, le jeton courant sert aussi au refresh.
- L’endpoint `/api/v1/auth/refresh` invalide l’ancien token et retourne un nouveau JWT.
- Le TTL par defaut est de `60` minutes et la fenetre de refresh de `20160` minutes.
