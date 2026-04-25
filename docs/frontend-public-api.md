# Mboka Media Public API

Ce document sert de reference d'integration pour le frontend Next.js public.

## Base URL

- Local: `http://127.0.0.1:8000/api/public`
- Production: a definir selon le domaine deploye

## Format des reponses

Toutes les reponses API retournent une structure homogene:

```json
{
  "success": true,
  "message": "Request successful.",
  "data": {},
  "meta": {}
}
```

`meta` n'est present que sur les listings pagines.

## Homepage

### Settings globaux

- `GET /settings`
- Usage frontend:
  - nom du site
  - slogan
  - email principal
  - telephone
  - logo / favicon
  - SEO global
  - reseaux sociaux

### Featured sections

- `GET /featured`
- Usage frontend:
  - hero homepage
  - sections editoriales mises en avant
  - contenus ordonnes pour la homepage

### Breaking news

- `GET /breaking-news`
- Usage frontend:
  - bandeau urgent
  - ticker en haut de page

### Articles homepage

- `GET /articles`
- Query params utiles:
  - `featured=1`
  - `category={slug}`
  - `tag={slug}`
  - `search={texte}`
  - `per_page={nombre}`

### Videos homepage

- `GET /videos`
- Query params utiles:
  - `featured=1`
  - `per_page={nombre}`

## Articles

### Listing articles

- `GET /articles`
- Query params:
  - `category`
  - `tag`
  - `search`
  - `featured`
  - `per_page`

### Detail article

- `GET /articles/{slug}`
- Le `slug` est l'identifiant public a utiliser dans les pages Next.js.
- Cet endpoint incremente aussi le compteur de vues.

### Champs principaux d'un article

- `id`
- `title`
- `slug`
- `excerpt`
- `content`
- `featured_image`
- `status`
- `is_featured`
- `seo_title`
- `seo_description`
- `published_at`
- `views_count`
- `author`
- `category`
- `tags`

## Categories

### Listing categories

- `GET /categories`
- Retourne uniquement les categories actives
- Usage frontend:
  - menu
  - pages categories
  - filtres article

## Videos

### Listing videos

- `GET /videos`
- Query params:
  - `featured`
  - `per_page`

### Detail video

- `GET /videos/{slug}`

### Champs principaux d'une video

- `id`
- `title`
- `slug`
- `description`
- `thumbnail`
- `video_url`
- `source_type`
- `status`
- `is_featured`
- `published_at`
- `author`

## Contact

### Soumettre un message

- `POST /contact`

Payload:

```json
{
  "name": "Junior Bk",
  "email": "junior@example.com",
  "phone": "+243000000000",
  "subject": "Demande d'information",
  "message": "Bonjour, je souhaite contacter Mboka Media."
}
```

## Newsletter

### Inscription newsletter

- `POST /newsletter/subscribe`

Payload:

```json
{
  "email": "newsletter@example.com"
}
```

## Mapping frontend suggere

- Homepage:
  - `/settings`
  - `/featured`
  - `/breaking-news`
  - `/articles?featured=1`
  - `/videos?featured=1`
- Page articles:
  - `/articles`
  - `/categories`
- Detail article:
  - `/articles/{slug}`
- Page videos:
  - `/videos`
- Layout global:
  - `/settings`
  - `/categories`

## Conseils Next.js

- Mettre `revalidate` sur les pages de contenu public
- Mettre les appels homepage en parallele
- Utiliser le `slug` public pour les routes dynamiques
- Afficher `message` si `success` est `false`
