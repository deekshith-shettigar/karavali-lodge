# Karavali Lodge — Hotel Management System

A full-stack hotel management system built with PHP, MySQL, and vanilla JavaScript. Designed for small to mid-sized hotels to manage rooms, bookings, guests, housekeeping, billing, and online reservations.

## Live Demo

- **Guest Website:** https://karavalilodge.freedev.app/user/
- **Admin Dashboard:** https://karavalilodge.freedev.app/admin/

## Features

**Guest Side**
- Browse available rooms with photos, amenities, and pricing
- Online room booking with Razorpay payment integration
- Guest registration, login, and OTP-based password reset
- View and manage personal bookings
- Contact form

**Admin Side**
- Dashboard with real-time stats (rooms, guests, revenue, occupancy)
- Room management (add, edit, availability)
- Booking and reservation management
- Guest check-in / check-out
- Housekeeping task management
- Billing and invoice generation (PDF)
- Night audit and reports
- Room service order management
- Online booking request handling

## Tech Stack

- **Backend:** PHP 8+
- **Database:** MySQL
- **Frontend:** Vanilla JS, Bootstrap 5
- **Email:** PHPMailer + Brevo SMTP
- **Payments:** Razorpay
- **PDF:** jsPDF

## Project Structure

```
karavali_lodge/
├── admin/       # Admin dashboard (SPA)
├── user/        # Guest-facing website
├── shared/      # SQL schema
├── uploads/     # Guest ID proof uploads
└── logs/        # Server logs
```

## Security

- Admin API requires valid session on every request
- CORS restricted to explicit origin allowlist
- Guest ID photos served only through authenticated endpoint
- Payment amounts verified server-side
- OTPs hashed, expire in 10 minutes, rate-limited
- Protected directories blocked via `.htaccess`

## GitHub

https://github.com/deekshith-shettigar/karavali-lodge