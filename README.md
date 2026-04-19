# Spice Quotation API — Laravel 11

Migration from Node.js/Express to Laravel 11, compatible with **cPanel shared hosting**.

---

## Stack

| Layer | Tech |
|---|---|
| Framework | Laravel 11 |
| Database | MongoDB (via `mongodb/laravel-mongodb`) |
| JWT | `firebase/php-jwt` |
| PDF | TCPDF |
| Email | Symfony Mailer (per-role SMTP) |
| Password encryption | AES-256-CBC (OpenSSL — same as original Node.js) |

---

## Directory structure

```
app/
  Http/
    Controllers/
      AuthController.php       ← /api/auth/*
      QuotationController.php  ← /api/quotation/*
    Middleware/
      Cors.php                 ← CORS (mirrors original app.js origins)
      RequireAuth.php          ← JWT cookie / Bearer header validation
      RequireRole.php          ← Role-based access (administrador / vendedor)
  Models/
    User.php                   ← MongoDB Eloquent model
  Services/
    AuthService.php            ← AES-256-CBC encrypt / decrypt / verify
    EmailService.php           ← Role-based SMTP sender
    JwtService.php             ← Sign / verify JWT
    PdfService.php             ← generateQuotationPDF / generateBillPDF (TCPDF)
bootstrap/
  app.php                      ← Laravel 11 app config (middleware aliases, CORS, JSON errors)
config/
  database.php                 ← MongoDB connection
routes/
  api.php                      ← All API routes (prefix /api)
composer.json
.env.example
```

---

## Installation

### 1. Install dependencies

```bash
composer install
```

### 2. Copy and fill environment file

```bash
cp .env.example .env
php artisan key:generate
```

Fill in all values in `.env` — especially:
- `MONGO_URI`
- `JWT_SECRET` (any long random string, 64+ chars)
- `AES_SECRET_KEY` (64 hex chars = 32 bytes, **must match the original Node.js value**)
- `AES_SECRET_IV`  (32 hex chars = 16 bytes, **must match the original Node.js value**)
- All four `SMTP_*` credential pairs

### 3. Logo asset

Place your company logo PNG at:

```
public/images/logo.png
```

This is used by PdfService to embed the logo in PDF headers.

### 4. Run locally

```bash
php artisan serve
```

---

## cPanel deployment

### Requirements

- PHP 8.2+
- `ext-openssl`, `ext-mbstring`, `ext-json` enabled
- MongoDB PHP driver (`pecl install mongodb` or ask your host)
- Composer available (or upload `vendor/` directly)

### Steps

1. Upload the entire project to a folder **outside** `public_html`
   (e.g. `/home/youruser/spice-api/`)

2. Point your subdomain (e.g. `api.lacasitadelsabor.com`) to
   `/home/youruser/spice-api/public`

3. Create a `.htaccess` inside `public/` (Laravel ships one):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

4. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`

5. Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
```

---

## API routes

All routes are prefixed with `/api`.

### Auth

| Method | Path | Auth | Body |
|--------|------|------|------|
| POST | `/api/auth/login` | — | `{ user, password }` |
| POST | `/api/auth/logout` | — | — |
| GET  | `/api/auth/me` | JWT | — |
| POST | `/api/auth/users` | JWT + admin | `{ id, name, email, user, password, roles }` |
| DELETE | `/api/auth/users/:id` | JWT + admin | — |
| GET  | `/api/auth/users` | JWT + admin | — |
| GET  | `/api/auth/users/:id` | — | — |
| POST | `/api/auth/sendRestoreEmail` | — | `{ email }` |
| POST | `/api/auth/restore` | — | `{ email, password }` |

### Quotation

| Method | Path | Body |
|--------|------|------|
| POST | `/api/quotation` | Quotation payload — sends PDF by email |
| POST | `/api/quotation/preview` | Same — returns PDF inline (no email) |
| POST | `/api/quotation/bill` | Bill payload — sends remision by email |
| POST | `/api/quotation/bill/preview` | Same — returns PDF inline |
| GET  | `/api/quotation/debug-image?imageName=uvas-pasas.png` | Checks Cloudinary URL |

Add `?download=true` to the non-preview quotation/bill endpoints to stream the PDF back instead of emailing it.

---

## Key migration notes

### Passwords
AES-256-CBC encryption is **bit-for-bit identical** to the Node.js implementation.
Existing passwords stored in MongoDB are fully compatible — no migration needed.

### JWT
Tokens signed by the Node.js API **will not** be valid here (different library internals).
Users will need to log in again after the cutover.

### PDF
TCPDF is used instead of `pdf-lib`. The layout mirrors the original design (red header, yellow accents, product image grid). Place your logo at `public/images/logo.png`.

### Email
Per-role SMTP credentials are preserved exactly. The `seller`, `remission`, and `security` roles map to the same env vars.

### MongoDB
Uses `mongodb/laravel-mongodb` (Jenssegers driver). The `User` collection schema is unchanged.
