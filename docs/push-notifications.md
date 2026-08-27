# Notifications push

Publier un article envoie désormais une notification à l'application mobile.
Rien à déclencher à la main.

## Comment ça marche

```
Publication d'un article  (ArticleController::store / update)
        │
        ▼
ArticlePublicationNotifier::notifyIfFreshlyPublished()
        │   crée un ArticleNotification, une seule fois par article
        ├──► e-mails aux abonnés newsletter      → email_sent_at
        └──► SendArticlePushNotification (file)  → push_sent_at
                     │
                     ▼
             FcmClient  →  topic « all-users »  →  téléphones
```

Le déclencheur existait déjà pour les e-mails : le push s'y greffe. Les deux
colonnes sont distinctes, pour qu'un envoi d'e-mails en échec n'empêche pas le
push, ni l'inverse.

Le job est mis en file : l'appel à Google ne doit pas faire attendre le
journaliste qui vient de cliquer sur « Publier ». **Un worker doit tourner** en
production (`php artisan queue:work`), sinon rien ne part.

## Configuration

```dotenv
FIREBASE_PROJECT_ID=mboka-media-7e778
FIREBASE_CREDENTIALS=/etc/mbokamedia/firebase-service-account.json
FIREBASE_BROADCAST_TOPIC=all-users
FIREBASE_ANDROID_CHANNEL=mboka_articles
```

La clé de compte de service se génère dans la console Firebase → Paramètres du
projet → **Comptes de service** → « Générer une nouvelle clé privée ».

Elle donne accès au projet Firebase entier, pas seulement à la messagerie :

- jamais dans le dépôt, jamais dans un dossier servi par le serveur web ;
- droits `600`, propriétaire = l'utilisateur qui fait tourner PHP ;
- si elle fuit, révoquez-la sur cette même page de la console.

Sans configuration, l'application ne casse pas : le job journalise un
avertissement et s'arrête. La publication aboutit normalement.

## Contrat avec l'application mobile

`data.slug` est **obligatoire** : c'est lui qui permet au tap d'ouvrir
l'article. Sans lui, la notification s'affiche mais n'ouvre que l'accueil.

```jsonc
{
  "topic": "all-users",
  "notification": { "title": "…", "body": "…" },
  "data": {
    "slug": "kinshasa-relance-transport-fluvial",
    "click_action": "FLUTTER_NOTIFICATION_CLICK",
    "image": "https://…"          // facultatif
  },
  "android": {
    "priority": "high",
    "notification": { "channel_id": "mboka_articles", "sound": "default" }
  }
}
```

Le `channel_id` doit rester `mboka_articles` : c'est le canal que
l'application crée au lancement. Un autre identifiant et Android range la
notification dans un canal par défaut, sans son ni bannière.

## Enregistrement des appareils

```
POST /api/public/devices
{ "token": "fY3x…", "platform": "android", "app_version": "1.1.1+5", "locale": "fr-CD" }
```

Idempotent : l'application appelle cette route à chaque lancement et à chaque
renouvellement de token. Le token FCM dépassant la taille d'un index MySQL,
l'unicité porte sur `token_hash` (SHA-256).

Cette table sert au **ciblage unitaire** (un lecteur, un segment). La diffusion
générale passe par le topic et n'en a pas besoin.

À prévoir : purger les tokens que FCM signale comme invalides
(`UNREGISTERED`), et ceux dont `last_seen_at` remonte à plusieurs mois.

## Vérifier

```bash
php artisan test --filter PushNotificationTest
```

Un essai réel, sans passer par l'interface d'administration :

```php
php artisan tinker
>>> $n = App\Models\ArticleNotification::latest()->first();
>>> $n->forceFill(['push_sent_at' => null])->save();
>>> (new App\Jobs\SendArticlePushNotification($n))->handle(app(App\Services\Fcm\FcmClient::class));
```

> Attention : cela diffuse vers **tous** les appareils abonnés. Pour tester sur
> un seul téléphone, utilisez `tools/push_test.py` du dépôt mobile.

## Limites connues

- **iOS ne reçoit rien** tant que le bundle id et la clé APNs ne sont pas
  réglés côté Apple et Firebase. Voir `NOTIFICATIONS.md` du dépôt mobile, §3.2.
- **Seuls les appareils à jour** sont abonnés à `all-users` : l'abonnement se
  fait au lancement de l'application, et les versions antérieures à 1.1.1 ne
  s'abonnaient à aucun topic.
- Les topics par rubrique (`category-<slug>`) sont prévus côté application mais
  ne sont pas encore utilisés à l'envoi.
