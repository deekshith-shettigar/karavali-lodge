# Karavali Lodge — Full Stack Hotel Management System

A complete hotel/lodge management system built with PHP, MySQL, and vanilla JavaScript.

## Live Demo

- **Guest Website:** https://karavalilodge.freedev.app/user/
- **Admin Dashboard:** https://karavalilodge.freedev.app/admin/

## Project Structure

```
karavali_lodge/
├── admin/           # Admin dashboard (SPA)
├── user/            # Guest-facing website
├── shared/          # SQL schema files
├── uploads/         # ID proof uploads
├── logs/            # Server logs
└── .env             # Environment config (not committed)
```

## Setup (XAMPP / Windows)

1. Copy the entire `karavali_lodge/` folder to:

```
C:\xampp\htdocs\karavali_lodge\
```

2. Install PHPMailer via Composer:

```bash
composer require phpmailer/phpmailer
```

3. Create `.env` in the project root:

```
karavali_lodge/.env
```

With these values (edit as needed):

```env
BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_USER=your-login@smtp-brevo.com
BREVO_SMTP_PASS=your-smtp-key
BREVO_FROM_EMAIL=your@email.com
BREVO_FROM_NAME=Karavali Lodge
ADMIN_NOTIFY_EMAIL=admin@gmail.com

DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=karavali_lodge

BASE_URL=http://localhost/karavali_lodge

RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_CURRENCY=INR
RAZORPAY_ADVANCE_PERCENT=1.00

APP_ENV=development
```

## Database Setup

1. Open phpMyAdmin
2. Create a database named `karavali_lodge`
3. Import `shared/karavali_lodge_complete.sql`

## Local Access URLs

| Page | URL |
|------|-----|
| Guest website | http://localhost/karavali_lodge/ |
| Guest website (direct) | http://localhost/karavali_lodge/user/ |
| Admin dashboard | http://localhost/karavali_lodge/admin/ |
| Admin setup | http://localhost/karavali_lodge/admin/setup_admin.php |

## Tech Stack

- PHP 8+ (backend API)
- MySQL (database)
- Vanilla JS (admin SPA)
- Bootstrap 5 (UI)
- PHPMailer + Brevo SMTP (transactional email)
- Razorpay (payments)
- jsPDF (PDF billing)

## Email Setup (Brevo SMTP)

All transactional emails (OTP, booking confirmations, etc.) are sent via **Brevo SMTP** using PHPMailer.

**Steps:**
1. Sign up at [app.brevo.com](https://app.brevo.com)
2. Go to **Settings → SMTP & API → SMTP Keys** → generate a key
3. Go to **Settings → Senders, Domains, IPs** → verify your sender email
4. Add credentials to `.env` as `BREVO_SMTP_USER` and `BREVO_SMTP_PASS`

## Security Features

- **Admin API auth & CORS** — every action on `admin/api.php` requires a valid admin session; cross-origin requests are restricted to an explicit origin allowlist.
- **Protected directories** — `uploads/` and `logs/` are blocked from direct browser access via `.htaccess`.
- **ID-proof photo viewer** — guest ID photos are served only through `admin/photo.php`, which requires an admin session and validates file paths strictly.
- **Payment integrity** — expected charge amount is calculated server-side and stored in session against the Razorpay order ID.
- **Room status guard** — housekeeping actions check for an active checked-in guest before changing a room's bookable status.
- **Password reset (OTP) flow** — OTPs are 6-digit, hashed at rest, expire in 10 minutes, lock after 5 wrong attempts, and are rate-limited to 3 sends per mobile per 10 minutes.
- **Shared password policy** — registration and password reset both run through the same validation rules.
- **Rate-limited lookup endpoints** — mobile number lookup is capped per IP address to prevent enumeration.

## Deployment (InfinityFree)

1. Create account at [infinityfree.com](https://infinityfree.com)
2. Create MySQL database and import `shared/karavali_lodge_complete.sql`
3. Upload files via FTP (FileZilla) to `htdocs/`
4. Upload `vendor/` folder (PHPMailer) via FTP
5. Create `.env` on server with production values:

```env
BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_USER=your-brevo-login
BREVO_SMTP_PASS=your-brevo-smtp-key
BREVO_FROM_EMAIL=your@email.com
BREVO_FROM_NAME=Karavali Lodge
ADMIN_NOTIFY_EMAIL=your@email.com

DB_HOST=sql200.infinityfree.com
DB_USER=if0_xxxxxxxx
DB_PASS=your_db_password
DB_NAME=if0_xxxxxxxx_karavali

BASE_URL=https://yourdomain.freedev.app

RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_CURRENCY=INR
RAZORPAY_ADVANCE_PERCENT=1.00

APP_ENV=production
```

6. Visit `https://yourdomain.freedev.app/admin/setup_admin.php` to create admin account
7. Delete `setup_admin.php` from server after setup

## GitHub

Repository: https://github.com/deekshith-shettigar/karavali-lodge