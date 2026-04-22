# 📅 Booking Room System

A web-based office meeting room booking system built with PHP 7.4+, MySQL, and Bootstrap 5. Designed for internal use by organizations that need a simple, self-hosted room scheduling solution.

---

## ✨ Features

### Public
- Browse and book available meeting rooms
- Real-time time slot availability with visual indicators (booked = red, past = grey, available = normal)
- Room detail modal with full facilities info and photo
- Email confirmation with check-in link (auto-sent after booking)
- Check-in / Check-out via unique token link (time-window restricted)
- Public daily schedule per room with auto-refresh and QR code sharing

### Admin Panel
- Dashboard with booking statistics and charts
- Booking management (approve, reject, cancel, complete)
- Admin-assisted check-in / check-out
- Room management (photo, facilities, capacity, approval toggle)
- User & role management with granular permissions
- Working hours & holiday calendar configuration
- Email template editor (HTML)
- System settings (app name, colors, email domain restriction, booking rules)
- Reports: by date and by room
- Notification system (real-time bell)
- Mail log

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ (PDO, Argon2ID, session) |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Bootstrap 5.3, Bootstrap Icons |
| Email | PHPMailer (SMTP) |
| Libraries | Select2, DataTables, Chart.js, SweetAlert2 |
| Auth | Session-based with role & permission system |

---

## 📋 Requirements

- PHP **7.4** or higher (recommended: PHP 8.1+)
- MySQL **5.7** or higher (or MariaDB 10.3+)
- Web server: Apache (with `mod_rewrite`) or Nginx
- PHP extensions: `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`
- SMTP account for email delivery (Gmail App Password, Mailtrap, etc.)

---

## 🚀 Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-username/booking-room.git
cd booking-room
```

### 2. Set up the database

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE booking_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p booking_room < database/schema.sql

# Import seed data (default settings, holidays, sample rooms)
mysql -u root -p booking_room < database/seed.sql
```

### 3. Configure the application

**a. Database connection**
```bash
cp config/database.example.php config/database.php
```
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_room');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

**b. Application secrets**
```bash
cp config/secrets.example.php config/secrets.php
```
Edit `config/secrets.php`:
```php
// Generate with: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
define('AUTH_PEPPER',    'your-random-64-char-hex-string');
define('ENCRYPTION_KEY', 'your-random-64-char-hex-string');
```

**c. Mail (SMTP)**
```bash
cp config/mailer.example.php config/mailer.php
```
Edit `config/mailer.php` with your SMTP credentials. For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833).

> **Note:** SMTP settings can also be configured via Admin Panel → Settings after setup. Values in the DB take precedence over the file.

**d. Base URL**

Edit `config/config.php`:
```php
define('BASE_URL', 'https://yourdomain.com/booking-room');
```

### 4. Set folder permissions

```bash
chmod -R 755 uploads/
```

### 5. Web server setup

**Apache** — ensure `.htaccess` is enabled (`AllowOverride All`) and `mod_rewrite` is active.

**Nginx** — example config:
```nginx
location /booking-room {
    try_files $uri $uri/ /booking-room/index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

---

## 🔑 Default Login

After importing `seed.sql`, use these credentials to log in at `/login.php`:

| Username | Password | Role |
|---|---|---|
| `superadmin` | `Admin1234!` | Super Admin |
| `admin` | `Admin1234!` | Admin |
| `operator` | `Operator1234!` | Operator |

> **⚠️ Change all passwords immediately after first login.**

---

## 📁 Project Structure

```
booking-room/
├── admin/                  # Admin panel pages
│   ├── bookings/           # Booking list & detail
│   ├── master/             # Rooms, holidays, settings, etc.
│   ├── reports/            # Reports
│   ├── roles/              # Role & permission management
│   └── users/              # User management
├── api/                    # JSON API endpoints (AJAX)
├── assets/                 # CSS, JS, images (bundled, no CDN)
├── config/                 # Configuration files
│   ├── config.php          # Main app config
│   ├── database.php        # DB credentials (gitignored)
│   ├── database.example.php
│   ├── mailer.php          # SMTP config (gitignored)
│   ├── mailer.example.php
│   ├── secrets.php         # Auth pepper & encryption key (gitignored)
│   └── secrets.example.php
├── database/
│   ├── schema.sql          # Full database schema
│   ├── seed.sql            # Default data & sample content
│   └── migration_*.sql     # Incremental migrations
├── includes/               # Core PHP classes & helpers
│   ├── Auth.php
│   ├── BookingHelper.php
│   ├── Database.php
│   ├── Functions.php
│   ├── Mailer.php
│   └── Notification.php
├── uploads/                # User-uploaded images (gitignored, structure kept)
│   ├── rooms/
│   └── avatars/
├── vendor/                 # PHPMailer (bundled)
├── index.php               # Public booking form
├── schedule.php            # Public room schedule viewer
├── checkin.php             # Check-in / check-out handler
├── login.php
└── logout.php
```

---

## ⚙️ Key Settings (Admin Panel → Settings)

| Key | Description |
|---|---|
| `booking_min_minutes_before` | Minimum minutes before start time to allow booking (default: 30) |
| `booking_max_days_advance` | How many days ahead users can book (default: 30) |
| `booking_allow_same_day` | Allow same-day bookings (default: yes) |
| `allowed_email_domain` | Restrict bookings to a specific email domain (e.g. `company.com`) |
| `checkin_open_minutes_before` | Minutes before start time check-in link activates (default: 30) |
| `checkin_expire_minutes_after` | Minutes after end time check-in link expires (default: 15) |

---

## 🔒 Security Notes

- `config/secrets.php`, `config/database.php`, and `config/mailer.php` are **gitignored** and must **never** be committed
- Sensitive directories (`config/`, `includes/`, `database/`) are protected by `.htaccess` (deny all direct HTTP access)
- Use **HTTPS** in production — set `BASE_URL` to `https://`
- Auth uses **Argon2ID** password hashing with a pepper
- SMTP password in the database is stored **encrypted** (AES via `ENCRYPTION_KEY`)
- All user inputs are validated server-side and sanitized via PDO prepared statements

---

## 🗄 Database Migrations

If upgrading from an existing installation, run the relevant migration scripts:

```bash
# Convert booking_min_hours_before → booking_min_minutes_before
mysql -u root -p booking_room < database/migration_min_minutes_before.sql

# Add email templates table
mysql -u root -p booking_room < database/migration_email_templates.sql
```

---

## 📄 License

This project is open-source. Feel free to use, modify, and distribute.
