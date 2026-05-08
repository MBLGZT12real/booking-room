# 📅 Sistem Booking Ruangan

Aplikasi web pemesanan ruangan rapat berbasis PHP 7.4 + MySQL + Bootstrap 5. Dirancang untuk penggunaan internal organisasi, mendukung booking mandiri oleh karyawan, booking oleh admin/operator, jadwal berulang, notifikasi email, check-in/out, dan auto check-out terjadwal.

---

## ✨ Features

### Frontend (Publik)
- Lihat dan pesan ruangan yang tersedia
- Slot waktu visual: merah = sudah dipesan, abu = lewat waktu, biru = dipilih
- Modal detail ruangan (fasilitas, foto, kapasitas)
- Email konfirmasi otomatis dengan link check-in
- Check-in / Check-out via link token unik
- Jadwal harian ruangan publik dengan QR code

### Panel Admin
- Dashboard statistik booking dan grafik
- Kelola booking: approve, reject, batal, selesai
- **Buat Booking oleh Admin/Operator** — form lengkap dengan slot visual
- **Booking Berulang (Recurring)** — mingguan / dua mingguan, batas jumlah atau tanggal, preview tanggal live
- **Batalkan Seri Booking** — batal satu atau seluruh seri yang akan datang
- Check-in / Check-out manual oleh admin
- **Auto Check-out** — sistem otomatis check-out pukul 23:55
- **Email Reminder Check-out** — email otomatis ke customer saat waktu booking habis
- Kelola ruangan, departemen, tipe meeting, jam kerja, hari libur
- Manajemen user & role dengan permission granular
- Editor template email HTML
- Pengaturan sistem (nama app, warna, domain email, aturan booking)
- Laporan per ruangan dan per tanggal
- Notifikasi internal (bell real-time)
- Mail log & Audit log

---

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 7.4 (PDO, Argon2ID, session) |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Bootstrap 5.3, Bootstrap Icons |
| Email | PHPMailer (SMTP) |
| Library | Select2, DataTables, Chart.js, SweetAlert2 |
| Auth | Session-based RBAC |

---

## 📋 Persyaratan

- PHP **7.4** atau lebih baru
- MySQL **5.7+** atau MariaDB **10.3+**
- Web server: Apache (`mod_rewrite`) atau Nginx
- Extension PHP: `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`
- Akun SMTP untuk email (Gmail App Password, Mailtrap, dll.)

---

## 🚀 Instalasi

### Windows — XAMPP

1. **Install XAMPP** (PHP 7.4) dari [apachefriends.org](https://www.apachefriends.org)

2. **Aktifkan mod_rewrite**: Buka `C:\xampp\apache\conf\httpd.conf`, pastikan baris `LoadModule rewrite_module` tidak dikomentari, dan ubah `AllowOverride None` menjadi `AllowOverride All` pada blok `<Directory "...htdocs">`. Restart Apache.

3. **Clone / extract** proyek ke:
   ```
   C:\xampp\htdocs\booking-room\
   ```

4. **Buat database** via phpMyAdmin (`http://localhost/phpmyadmin`):
   ```sql
   CREATE DATABASE booking_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Import schema dan seed** via phpMyAdmin: pilih database → tab Import → upload `database/schema.sql`, lalu ulangi untuk `database/seed.sql`.

6. **Buat file konfigurasi** (lihat bagian [Konfigurasi](#konfigurasi))

7. **Akses aplikasi**: `http://localhost/booking-room`

---

### Linux — LAMP (Ubuntu/Debian)

1. **Install stack**:
   ```bash
   sudo apt update
   sudo apt install apache2 php7.4 php7.4-mysql php7.4-mbstring php7.4-json php7.4-openssl \
       libapache2-mod-php7.4 mysql-server -y
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Clone proyek**:
   ```bash
   cd /var/www/html
   sudo git clone <repo-url> booking-room
   sudo chown -R www-data:www-data booking-room
   sudo chmod -R 755 booking-room
   sudo chmod -R 775 booking-room/uploads
   ```

3. **Konfigurasi Apache** `/etc/apache2/sites-available/booking-room.conf`:
   ```apache
   <VirtualHost *:80>
       ServerName booking-room.local
       DocumentRoot /var/www/html/booking-room
       <Directory /var/www/html/booking-room>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
   ```bash
   sudo a2ensite booking-room.conf && sudo systemctl reload apache2
   ```

4. **Buat dan isi database**:
   ```bash
   mysql -u root -p -e "CREATE DATABASE booking_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p booking_room < /var/www/html/booking-room/database/schema.sql
   mysql -u root -p booking_room < /var/www/html/booking-room/database/seed.sql
   ```

5. **Buat file konfigurasi** (lihat bagian [Konfigurasi](#konfigurasi))

---

### macOS — MAMP / Homebrew

**Opsi A — MAMP:**
1. Install [MAMP](https://www.mamp.info), pilih PHP 7.4 di preferensi
2. Set document root atau salin proyek ke folder htdocs MAMP
3. Buat database via phpMyAdmin MAMP (`http://localhost:8888/phpMyAdmin`)
4. Import `schema.sql` lalu `seed.sql`

**Opsi B — Homebrew:**
```bash
brew install php@7.4 mysql
brew services start php@7.4
brew services start mysql

mysql -u root -e "CREATE DATABASE booking_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root booking_room < database/schema.sql
mysql -u root booking_room < database/seed.sql

# Jalankan server built-in di folder proyek
php -S localhost:8000
```

---

## ⚙️ Konfigurasi

Semua file konfigurasi berada di `config/` dan **tidak di-commit** ke git. Buat secara manual:

### 1. Edit `config/secrets.php`

```bash
cp config/secrets.example.php config/secrets.php
```

```php
<?php
// Pepper untuk hash password akun admin (berbeda dari ENCRYPTION_KEY)
define('AUTH_PEPPER',    'isi-random-string-lain-32-karakter-atau-lebih');
// Kunci enkripsi password SMTP di database (gunakan string acak minimal 32 karakter)
define('ENCRYPTION_KEY', 'isi-random-string-32-karakter-atau-lebih');
```

> Generate string acak: `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`

### 2. Edit `config/database.php`

```bash
cp config/database.example.php config/database.php
```

```php
<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'booking_room');
define('DB_USER',    'your_db_user'); // user MySQL Anda
define('DB_PASS',    'your_db_password'); // password MySQL Anda
define('DB_CHARSET', 'utf8mb4');
```

### 3. Edit `config/config.php`

```bash
cp config/config.example.php config/config.php
```

```php
<?php
define('BASE_URL',    'https://yourdomain.com/booking-room'); // tanpa trailing slash
define('APP_NAME',    'Sistem Booking Ruangan');
define('APP_VERSION', '1.5');
define('TIMEZONE',    'Asia/Jakarta');
```

### 4. Edit `config/mailer.php`

```bash
cp config/mailer.example.php config/mailer.php
```

```php
<?php
// Nilai default — bisa di-override via Admin Panel > Pengaturan Sistem
define('MAIL_SMTP_HOST',       'smtp.yourdomain.com');
define('MAIL_SMTP_PORT',       465);
define('MAIL_SMTP_USERNAME',   'no-reply@yourdomain.com'); // email pengirim (kosongkan untuk nonaktifkan)
define('MAIL_SMTP_PASSWORD',   'your-app-password-here'); // app password Gmail atau password SMTP
define('MAIL_SMTP_ENCRYPTION', 'ssl');
define('MAIL_FROM_EMAIL',      'no-reply@yourdomain.com');
define('MAIL_FROM_NAME',       'Company - Booking Ruangan');
define('MAIL_DEBUG',           0);    // 0=off, 2=verbose SMTP debug
```

> **Gmail**: aktifkan 2-Step Verification → buat App Password di myaccount.google.com/apppasswords. Gunakan App Password, bukan password akun biasa.
> **Note**: Pengaturan SMTP juga dapat dikonfigurasi melalui Panel Admin → Pengaturan setelah penyiapan. Nilai dalam basis data lebih diutamakan daripada file.

### 5. Set folder permissions

```bash
chmod -R 755 uploads/
```

### 6. Web server setup

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

## Setup Cron Job

Dua skrip cron harus berjalan terjadwal:

| Skrip | Fungsi | Jadwal |
|-------|--------|--------|
| `cron/checkout-reminder.php` | Kirim email reminder ke customer yang belum check-out setelah waktu habis | Setiap 10 menit |
| `cron/auto-checkout.php` | Auto check-out semua booking yang belum check-out hari ini | Setiap hari 23:55 |

### Windows — Task Scheduler

Buat dua task via GUI Task Scheduler atau jalankan PowerShell berikut sebagai Administrator:

```powershell
# Checkout Reminder — setiap 10 menit
$action  = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" `
           -Argument "C:\xampp\htdocs\booking-room\cron\checkout-reminder.php"
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 10) `
           -Once -At (Get-Date)
Register-ScheduledTask -TaskName "BookingRoom-CheckoutReminder" `
    -Action $action -Trigger $trigger -RunLevel Highest -Force

# Auto Checkout — pukul 23:55
$action2  = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" `
            -Argument "C:\xampp\htdocs\booking-room\cron\auto-checkout.php"
$trigger2 = New-ScheduledTaskTrigger -Daily -At "23:55"
Register-ScheduledTask -TaskName "BookingRoom-AutoCheckout" `
    -Action $action2 -Trigger $trigger2 -RunLevel Highest -Force
```

### Linux / macOS — crontab

```bash
crontab -e
```

Tambahkan:
```cron
# Booking Room — reminder check-out setiap 10 menit
*/10 * * * * /usr/bin/php /var/www/html/booking-room/cron/checkout-reminder.php >> /var/log/br-reminder.log 2>&1

# Booking Room — auto check-out pukul 23:55
55 23 * * * /usr/bin/php /var/www/html/booking-room/cron/auto-checkout.php >> /var/log/br-checkout.log 2>&1
```

> Cek path PHP dengan `which php`. Sesuaikan path proyek jika berbeda.

### cPanel (Shared Hosting)

1. Login cPanel → **Cron Jobs**
2. **Checkout Reminder**:
   - Minute: `*/10` | Hour: `*` | Day: `*` | Month: `*` | Weekday: `*`
   - Command:
     ```
     /usr/local/bin/php /home/USERNAME/public_html/booking-room/cron/checkout-reminder.php >> /home/USERNAME/logs/br-reminder.log 2>&1
     ```
3. **Auto Checkout**:
   - Minute: `55` | Hour: `23` | Day: `*` | Month: `*` | Weekday: `*`
   - Command:
     ```
     /usr/local/bin/php /home/USERNAME/public_html/booking-room/cron/auto-checkout.php >> /home/USERNAME/logs/br-checkout.log 2>&1
     ```

> Ganti `USERNAME` dengan username cPanel Anda. Path PHP bisa berbeda antar hosting — tanyakan ke support atau cek via `phpinfo()`.

### VPS — crontab

Sama seperti Linux. Path PHP di VPS dengan PHP-FPM biasanya `/usr/bin/php8.1` atau `/usr/local/bin/php`. Gunakan `which php` untuk memastikan.

---

## Deploy ke Shared Hosting (cPanel)

1. **Upload file** via FTP/SFTP (FileZilla) atau File Manager cPanel
   - Upload semua isi folder proyek ke `public_html/booking-room/` (atau subfolder pilihan)
   - Pastikan file `.htaccess` ikut ter-upload

2. **Buat database** via cPanel → *MySQL Databases*:
   - Buat database baru (misal: `username_bookingroom`)
   - Buat user MySQL baru dan assign ke database dengan **All Privileges**

3. **Import database** via phpMyAdmin:
   - Pilih database → tab Import → upload `database/schema.sql`
   - Ulangi untuk `database/seed.sql`

4. **Buat file konfigurasi** via File Manager atau FTP (isi sesuai bagian [Konfigurasi](#konfigurasi)):
   - `config/secrets.php`
   - `config/database.php` — gunakan nama DB, user, pass yang dibuat di langkah 2
   - `config/config.php` — set `BASE_URL` ke URL domain Anda
   - `config/mailer.php`

5. **Permission folder**:
   ```
   uploads/rooms/   → 755 atau 775
   uploads/avatars/ → 755 atau 775
   ```

6. **Setup cron jobs** seperti di bagian [cPanel](#cpanel-shared-hosting) di atas

---

## Deploy ke VPS

### Ubuntu + Nginx + PHP-FPM

1. **Install dependensi**:
   ```bash
   sudo apt update
   sudo apt install nginx php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-curl \
       php8.1-json php8.1-openssl mysql-server -y
   ```

2. **Clone proyek**:
   ```bash
   cd /var/www
   sudo git clone <repo-url> booking-room
   sudo chown -R www-data:www-data booking-room
   sudo chmod -R 755 booking-room
   sudo chmod -R 775 booking-room/uploads
   ```

3. **Nginx config** `/etc/nginx/sites-available/booking-room`:
   ```nginx
   server {
       listen 80;
       server_name domain.com www.domain.com;
       root /var/www/booking-room;
       index index.php index.html;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
       }

       location ~ /\.(ht|git|env) {
           deny all;
       }
   }
   ```
   ```bash
   sudo ln -s /etc/nginx/sites-available/booking-room /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```

4. **SSL dengan Certbot**:
   ```bash
   sudo apt install certbot python3-certbot-nginx -y
   sudo certbot --nginx -d domain.com -d www.domain.com
   ```

5. **Database**:
   ```bash
   sudo mysql -e "CREATE DATABASE booking_room CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   sudo mysql -e "CREATE USER 'bookinguser'@'localhost' IDENTIFIED BY 'StrongPassword123';"
   sudo mysql -e "GRANT ALL PRIVILEGES ON booking_room.* TO 'bookinguser'@'localhost';"
   sudo mysql booking_room < /var/www/booking-room/database/schema.sql
   sudo mysql booking_room < /var/www/booking-room/database/seed.sql
   ```

6. **File konfigurasi** dan **cron jobs** sama seperti bagian Linux di atas.

---

## 🗄 Migrasi Database (Instalasi yang Sudah Ada)

Jalankan file migrasi secara berurutan sesuai versi:

```bash
# v1.1 — indeks conflict detection
mysql -u root -p booking_room -e \
  "ALTER TABLE bookings ADD KEY idx_bookings_conflict (room_id, booking_date, status);"

# v1.2 — login rate limiting & email templates
mysql -u root -p booking_room < database/migration_login_attempts.sql
mysql -u root -p booking_room < database/migration_email_templates.sql
mysql -u root -p booking_room < database/migration_menu_email_templates.sql

# v1.3 — ganti satuan booking minimum dari jam ke menit
mysql -u root -p booking_room < database/migration_min_minutes_before.sql

# v1.4 — booking berulang & booked_by_admin
mysql -u root -p booking_room < database/migration_recurring_bookings.sql

# v1.5 — auto check-out & email reminder check-out
mysql -u root -p booking_room < database/migration_auto_checkout.sql
```

> File `schema.sql` selalu berisi skema terlengkap (fresh install). File `migration_*.sql` untuk upgrade instalasi yang sudah ada.

---

## 🔑 Akun Default

| Username | Password | Role |
|---|---|---|
| `superadmin` | `superadmin@123` | Super Admin |
| `admin` | `admin123` | Administrator |
| `operator1` | `operator123` | Operator |
| `staff1` | `ap1208` | Staff |

> **Ganti semua password default segera setelah pertama kali login**, terutama di environment production.

---

## Penggunaan

### Booking oleh Karyawan
1. Buka URL aplikasi (frontend publik)
2. Pilih ruangan, tanggal, dan slot waktu
3. Isi form data diri dan tujuan booking
4. Cek email — berisi link check-in (langsung dikonfirmasi) atau info menunggu approval

### Booking oleh Admin/Operator
1. Login ke panel admin → menu **Booking** → tombol **Buat Booking**
2. Pilih ruangan, tanggal, slot waktu
3. Isi data pemesan
4. Aktifkan toggle **Booking Berulang** untuk jadwal mingguan/dua mingguan
5. Pratinjau tanggal otomatis muncul — konfirmasi via SweetAlert2 untuk seri ≥ 2 booking

### Check-in & Check-out
- **Customer**: klik link di email konfirmasi
- **Admin/Operator**: panel admin → **Check-in/out** → tombol manual

### Auto Check-out & Reminder
- Ketika waktu booking habis dan customer belum check-out, cron job `checkout-reminder.php` mengirim email pengingat
- Pukul 23:55, `auto-checkout.php` melakukan check-out otomatis untuk semua booking yang masih aktif
- Admin/Operator tetap bisa melakukan check-out manual kapan saja

---

## ⚙️ Pengaturan Sistem (Admin Panel → Pengaturan)

| Pengaturan | Keterangan |
|------------|------------|
| `booking_min_minutes_before` | Minimal menit sebelum jam mulai untuk bisa booking (default: 30) |
| `booking_max_days_advance` | Berapa hari ke depan booking bisa dibuat (default: 60) |
| `booking_allow_same_day` | Izinkan booking hari ini (default: ya) |
| `allowed_email_domain` | Batasi domain email pemesan (kosong = semua domain) |
| `email_notification_enabled` | Aktifkan/nonaktifkan pengiriman email |

---

## 🔒 Catatan Keamanan

- File `config/` tidak boleh pernah di-commit ke repository
- Sensitive folder (`config/`, `includes/`, `database/`) dilindungi dengan `.htaccess` (deny all direct HTTP access)
- Gunakan **HTTPS** di production — set `BASE_URL` ke `https://`
- Ganti semua password default setelah instalasi
- Password admin di-hash dengan **Argon2ID** + pepper
- Password SMTP di database dienkripsi dengan AES menggunakan `ENCRYPTION_KEY`
- Semua input divalidasi server-side dan di-escape via PDO prepared statement

---

## 📁 Struktur Proyek

```
booking-room/
├── admin/                  # Panel admin
│   ├── bookings/           # Daftar, detail, buat booking
│   ├── checkin/            # Manajemen check-in/out
│   ├── email-templates/    # Editor template email HTML
│   ├── mail-log/           # Log email terkirim
│   ├── master/             # Data master
│   ├── notifications/      # Notifikasi internal
│   ├── reports/            # Laporan
│   ├── roles/              # Role & permission
│   └── users/              # Manajemen user
├── api/                    # Endpoint AJAX
│   └── available-slots.php
├── assets/                 # CSS, JS, gambar
├── config/                 # Konfigurasi (gitignored)
│   ├── config.php
│   ├── database.php
│   ├── mailer.php
│   └── secrets.php
├── cron/                   # Skrip terjadwal
│   ├── auto-checkout.php   # Auto check-out 23:55
│   └── checkout-reminder.php
├── database/               # SQL files
│   ├── schema.sql          # Skema lengkap (fresh install)
│   ├── seed.sql            # Data awal + template email
│   └── migration_*.sql     # Migrasi inkremental
├── includes/               # Kelas & fungsi inti
│   ├── Auth.php
│   ├── BookingHelper.php   # Booking, recurring, slot
│   ├── cron-bootstrap.php  # Bootstrap khusus CLI
│   ├── Database.php
│   ├── Functions.php
│   ├── Mailer.php          # PHPMailer wrapper + template
│   └── Notification.php
├── uploads/                # Upload gambar (gitignored)
│   ├── avatars/
│   └── rooms/
├── vendor/                 # PHPMailer
├── .htaccess
├── index.php               # Frontend publik
├── booking.php             # Form booking publik
├── checkin.php             # Check-in/out customer
├── login.php
└── logout.php
```

---

## Lisensi

Untuk penggunaan internal. Seluruh hak cipta milik pemilik proyek.
