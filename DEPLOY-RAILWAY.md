# Déploiement sur Railway

Guide pour mettre en ligne la plateforme ebook (Laravel API + React + MySQL).

## Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  react-web      │────▶│  laravel-api    │────▶│  MySQL          │
│  (frontend SPA) │     │  (API REST)     │     │  (plugin)       │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

| Service Railway | Dossier racine | Port |
|-----------------|----------------|------|
| Frontend | `react-web` | `$PORT` (auto) |
| API | `laravel-api` | `$PORT` (auto) |
| MySQL | Plugin Railway | — |

---

## 1. Prérequis

- Compte [Railway](https://railway.app)
- Dépôt Git (GitHub recommandé)
- Clés Stripe (mode test ou live)

### Initialiser Git (si pas encore fait)

```bash
cd ebook-railway
git init
git add .
git commit -m "Prepare Railway deployment"
git remote add origin https://github.com/VOTRE_USER/ebook-railway.git
git push -u origin main
```

---

## 2. Créer le projet Railway

1. **New Project** → **Deploy from GitHub repo**
2. Sélectionner le dépôt `ebook-railway`

Vous obtiendrez un premier service (à reconfigurer). Il en faut **trois** au total.

---

## 3. Service MySQL

1. Dans le projet Railway : **+ New** → **Database** → **MySQL**
2. Noter le nom du service (ex. `MySQL`) pour les références `${{MySQL.*}}`

---

## 4. Service API (Laravel)

### Configuration du service

1. **+ New** → **GitHub Repo** → même dépôt
2. **Settings** → **Root Directory** : `laravel-api`
3. **Settings** → **Networking** → **Generate Domain** (ex. `ebook-api.up.railway.app`)

### Volume pour les fichiers (PDF + couvertures)

Sans volume, les uploads sont **perdus à chaque redéploiement**.

1. Service API → **Volumes** → **Add Volume**
2. **Mount path** : `/app/storage/app`
3. Taille : selon vos besoins (ex. 5 Go)

> Alternative long terme : stockage S3 (variables `AWS_*` dans `.env`).

### Variables d'environnement

Dans **Variables** du service API :

| Variable | Valeur |
|----------|--------|
| `APP_KEY` | Générer : `php artisan key:generate --show` en local |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://ebook-api.up.railway.app` (votre domaine API) |
| `FRONTEND_URL` | `https://ebook-web.up.railway.app` (votre domaine frontend) |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `info` |
| `STRIPE_SECRET` | `sk_live_...` ou `sk_test_...` |
| `STRIPE_WEBHOOK_SECRET` | `whsec_...` (après config webhook) |

Les migrations et `storage:link` s'exécutent automatiquement au démarrage (`scripts/start.sh`).

### Créer le compte admin (une fois déployé)

Dans Railway → service API → **Shell** :

```bash
php artisan db:seed --class=AdminUserSeeder
```

Identifiants : `admin@example.com` / `password` — **changez le mot de passe immédiatement**.

---

## 5. Service Frontend (React)

### Configuration du service

1. **+ New** → **GitHub Repo** → même dépôt
2. **Settings** → **Root Directory** : `react-web`
3. **Settings** → **Networking** → **Generate Domain** (ex. `ebook-web.up.railway.app`)

### Variables d'environnement

| Variable | Valeur |
|----------|--------|
| `VITE_API_URL` | `https://ebook-api.up.railway.app` (sans `/api`) |

> `VITE_API_URL` est injectée **au build**. Après modification, redéployez le frontend.

---

## 6. Stripe

### Webhook

Dans le [Dashboard Stripe](https://dashboard.stripe.com/webhooks) :

- **URL** : `https://ebook-api.up.railway.app/api/webhooks/stripe`
- **Événements** :
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `invoice.payment_succeeded`
  - `invoice.payment_failed`

Copier le **Signing secret** dans `STRIPE_WEBHOOK_SECRET` sur Railway.

### URLs de redirection

Déjà configurées via `FRONTEND_URL` :

- Succès : `{FRONTEND_URL}/subscription/success`
- Annulation : `{FRONTEND_URL}/subscription/cancel`

---

## 7. Vérifications post-déploiement

| Test | URL / action |
|------|----------------|
| Health API | `GET https://ebook-api.up.railway.app/up` → 200 |
| Frontend | Ouvrir le domaine frontend |
| Inscription | Créer un compte |
| Admin | Login `admin@example.com` → `/admin` |
| Upload ebook | Admin → nouvel ebook (PDF + couverture) |
| Stripe | Tester un checkout abonnement |

---

## 8. Domaines personnalisés (optionnel)

Pour chaque service :

1. **Settings** → **Networking** → **Custom Domain**
2. Ajouter un CNAME chez votre registrar
3. Mettre à jour `APP_URL`, `FRONTEND_URL` et `VITE_API_URL` puis redéployer

---

## Fichiers de déploiement

| Fichier | Rôle |
|---------|------|
| `laravel-api/railway.toml` | Config Railway API |
| `laravel-api/nixpacks.toml` | Build PHP + Composer |
| `laravel-api/scripts/start.sh` | Migrations, cache, démarrage |
| `laravel-api/php-prod.ini` | Limites upload 100 Mo |
| `react-web/railway.toml` | Config Railway frontend |
| `react-web/nixpacks.toml` | Build Node + Vite |

---

## Dépannage

### 419 / CSRF
L'API utilise des tokens Bearer, pas de cookies Sanctum. Ne pas réactiver `EnsureFrontendRequestsAreStateful`.

### Upload PDF échoue (422)
Vérifier que `php-prod.ini` est bien utilisé (`scripts/start.sh`). Limite app : 100 Mo PDF, 5 Mo couverture.

### Fichiers disparus après redéploiement
Monter un volume sur `/app/storage/app` ou passer à S3.

### Frontend ne joint pas l'API
Vérifier `VITE_API_URL` et redéployer le frontend. Vérifier CORS : `FRONTEND_URL` doit correspondre à l'URL du frontend.

### Erreur base de données
Vérifier les références `${{MySQL.*}}` et que le service MySQL est dans le même projet Railway.
