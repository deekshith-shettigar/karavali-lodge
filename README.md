# Karavali Lodge — Full Stack System

A complete hotel/lodge management system built with PHP, MySQL, and vanilla JavaScript.

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

2. Create `.env` in the project root:

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
BREVO_API_KEY=your-brevo-api-key

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

> **Note:** Email is sent via the **Brevo API** (HTTPS), not SMTP.
> This avoids port 587/465 being blocked on localhost or shared hosting.
> `BREVO_API_KEY` is required. Get it from:
> `app.brevo.com/settings/keys/api`

## Database Setup

1. Open phpMyAdmin
2. Create a database named `karavali_lodge`
3. Import `shared/karavali_lodge_complete.sql`

## Access URLs

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
- Razorpay (payments)
- Brevo API (transactional email — OTP & booking notifications)
- jsPDF (PDF billing)

## Email Setup (Brevo)

All transactional emails (OTP, booking confirmations, etc.) are sent via the **Brevo API** using cURL over HTTPS — not SMTP. This means:

- No firewall issues with ports 587 or 465
- Works on localhost, XAMPP, and shared hosting out of the box
- Requires only `BREVO_API_KEY` in your `.env`

**Steps to get your API key:**
1. Sign up / log in at [app.brevo.com](https://app.brevo.com)
2. Go to **Settings → SMTP & API → API Keys**
3. Generate a new key and paste it into `.env` as `BREVO_API_KEY`
4. Go to **Settings → Senders, Domains, IPs** and verify your sender email

## Security Features

- **Admin API auth & CORS** — every action on `admin/api.php` requires a valid admin session; cross-origin requests are restricted to an explicit origin allowlist instead of `*`.
- **Protected directories** — `uploads/` and `logs/` are both blocked from direct browser access via `.htaccess` (deny all).
- **ID-proof photo viewer** — guest ID photos stored as file paths are served only through `admin/photo.php`, which requires an admin session, validates the filename against a strict pattern, resolves and checks the real path stays inside `uploads/id_proofs/`, and whitelists the MIME type before serving.
- **Payment integrity** — the expected charge amount is calculated server-side and stored in session against the Razorpay order ID, so a guest can't pay a manipulated amount by editing values client-side.
- **Room status guard** — housekeeping actions (mark clean / in-progress / maintenance) check for an active checked-in guest before changing a room's bookable status, preventing a mid-stay cleaning task from accidentally freeing up an occupied room for a new booking.
- **Password reset (OTP) flow** — OTPs are 6-digit, hashed at rest, expire in 10 minutes, lock after 5 wrong attempts, and are rate-limited to 3 sends per mobile number per 10 minutes. A verified OTP issues a short-lived, session-bound reset token (compared with `hash_equals`) rather than ever passing the OTP itself through to the password-reset step.
- **Shared password policy** — registration and password reset both run through the same validation rule (minimum length, rejects all-numeric and repeated-character passwords, blocks a common-password list), so a reset can never produce a weaker password than signup would allow.
- **Rate-limited lookup endpoints** — the "is this mobile number registered" check (used for the live hint on the registration page, and internally by OTP sending) is capped per IP address, preventing it from being used to mass-enumerate registered guests.

## Deployment

1. Upload files to your server
2. Set `.env` values for production (especially `BREVO_API_KEY` and `APP_ENV=production`)
3. Import the SQL schema
4. Point your domain's document root to `karavali_lodge/`
5. Delete `test_email.php` from the project root if it exists