# FichoChat API

API REST de **FichoChat**, messagerie instantanée chiffrée de bout en bout.

Le serveur ne stocke **jamais** le clair des messages : chiffrement côté client, transport d’enveloppes/ciphertext, auth JWT, sync multi-appareils et temps réel.

**Branche principale de développement :** `dev`  
**Base URL :** `/api/v1`

---

## Stack

| Composant | Techno |
|-----------|--------|
| Framework | Laravel 13 (PHP 8.4+) |
| Auth | JWT (`php-open-source-saver/jwt-auth`) + refresh cookie (Web) |
| Permissions | Spatie Permission |
| Docs | L5-Swagger (`/api/documentation`) |
| Mail | Brevo API |
| Realtime | Pusher Channels |
| Queue | Database (`broadcasts`, `emails`, `audits`, `media`, `default`) |
| DB cible | PostgreSQL (SQLite OK en local) |

---

## Prérequis

- PHP 8.3+
- Composer
- PostgreSQL 16+ (recommandé) ou SQLite
- Extensions PHP habituelles Laravel (`pdo`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)

---

## Installation

```bash
git clone https://github.com/Garrykab/FichoChat_api.git
cd FichoChat_api
git checkout dev

composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### Base de données (PostgreSQL Docker)

```bash
docker start fichochat-pg || docker run --name fichochat-pg \
  -e POSTGRES_PASSWORD=secret \
  -e POSTGRES_USER=fichochat \
  -e POSTGRES_DB=fichochat \
  -p 55432:5432 -d postgres:16
```

Vérifiez dans `.env` :

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=fichochat
DB_USERNAME=fichochat
DB_PASSWORD=secret
```

Puis :

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan l5-swagger:generate
php artisan serve
```

- API : http://localhost:8000  
- Swagger : http://localhost:8000/api/documentation

---

## Configuration essentielle

| Variable | Rôle |
|----------|------|
| `APP_URL` | URL publique de l’API (logo e-mails, liens) |
| `FRONTEND_URL` | URL du client Web (CORS + liens auth) |
| `JWT_SECRET` / `JWT_TTL` | Secret JWT + durée access token (recommandé `15`) |
| `MAIL_MAILER=brevo` + `BREVO_API_KEY` | E-mails transactionnels |
| `MAIL_FROM_ADDRESS` | Expéditeur **vérifié** dans Brevo |
| `BROADCAST_CONNECTION=pusher` | Temps réel (sinon events → log) |
| `PUSHER_APP_*` | Identifiants Pusher Channels |
| `ADMIN_EMAILS` | E-mails auto-promus admin au login /me |
| `CORS_ALLOWED_ORIGINS` | Origines CORS additionnelles (CSV) |

> Ne committez jamais `.env`. Seul `.env.example` est versionné.

### E-mails (Brevo)

1. Créer une clé API : https://app.brevo.com/settings/keys/api  
2. Renseigner `BREVO_API_KEY` et un `MAIL_FROM_ADDRESS` vérifié  
3. Tester :

```bash
php artisan mail:test-brevo votre@email.com
```

### Workers & scheduler

```bash
# Queues
php artisan queue:work --queue=broadcasts,emails,audits,media,default

# Scheduler (ex. cleanup médias)
php artisan schedule:work
```

### Admin

```bash
php artisan admin:create
# ou
php artisan admin:promote email@example.com
```

---

## Modules API (`/api/v1`)

| Préfixe | Contenu |
|---------|---------|
| `/auth` | Register, login, refresh, logout, forgot/reset password, verify email |
| `/users` | Profil, avatar chiffré, recherche, changement mot de passe |
| `/devices` | Enregistrement, approbation, révocation, récupération OTP / phrase |
| `/keys` | Clés publiques appareils, enveloppes CEK, rotation |
| `/conversations` | Conversations privées 1-1, participants |
| `/messages` | Envoi / édition / suppression (ciphertext), accusés |
| `/media` | Upload chunké chiffré, limites configurables |
| `/sync` | Bootstrap / delta / ack multi-appareils |
| `/presence` | Heartbeat, dernière activité |
| `/settings` | Préférences utilisateur (notifs, confidentialité, médias) |
| `/security` | Journal d’activité / événements sécurité |
| `/admin` | Stats, utilisateurs, appareils, réglages app (Spatie) |
| `/realtime` | Config client + auth broadcasting |

Auth métier : Bearer JWT (`Authorization: Bearer …`).  
Refresh / logout Web : cookie HttpOnly + contrôle d’origine.

---

## Tests

```bash
php artisan test
```

Exemples ciblés :

```bash
php artisan test --filter='AuthTest|DeviceModuleTest|MessageModuleTest'
```

---

## Structure du dépôt

```
app/
  Actions/          # Cas d’usage métier
  Http/Controllers/ # API v1
  Models/
  Notifications/    # E-mails brandés
  Jobs/             # Audits, médias…
config/
database/migrations/
routes/api.php
storage/api-docs/   # OpenAPI généré
tests/
```

---

## Sécurité (dépôt public)

Ce dépôt **n’inclut pas** :

- fichiers `.env` / secrets
- PRD / notes internes (`prd.md`, `AGENT.md`, …)
- bases SQLite locales
- médias / préviews e-mails locaux

Signalez toute fuite de secret via les issues GitHub (sans republier la clé).

---

## Licence

Projet privé / usage FichoChat — voir le propriétaire du dépôt pour les conditions d’utilisation.
