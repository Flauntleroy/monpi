# Deployment Guide (Ubuntu + Nginx + PHP-FPM)

## Prasyarat
- Ubuntu 22.04/24.04, Nginx, PHP 8.2+ (php8.2-fpm, php8.2-xml, php8.2-mbstring, php8.2-curl, php8.2-zip), MySQL/MariaDB
- Composer, Node.js 18+

## Langkah Deploy
1. Clone project ke `/var/www/monitoringbpjs` dan `cd` ke sana.
2. Salin `.env.production.example` menjadi `.env`, isi DB dan `APP_KEY`:
   - `php artisan key:generate`
3. Install dependencies:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci && npm run build`
4. Set permission:
   - `sudo chown -R www-data:www-data storage bootstrap/cache`
   - `sudo chmod -R 775 storage bootstrap/cache`
5. Migrasi DB:
   - `php artisan migrate --force`
6. Cache konfigurasi & route:
   - `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize`
7. Konfigurasi Nginx:
   - Letakkan `deploy/nginx.conf.example` ke `/etc/nginx/sites-available/monitoringbpjs`
   - `sudo ln -s /etc/nginx/sites-available/monitoringbpjs /etc/nginx/sites-enabled/`
   - `sudo nginx -t && sudo systemctl reload nginx`

## Scheduler (Monitoring Otomatis)
- Tambahkan cron agar berjalan tiap menit:
```
* * * * * cd /var/www/monitoringbpjs && php artisan schedule:run >> /dev/null 2>&1
```
- Command yang dijadwalkan: `bpjs:monitor` (setiap 5 menit, tanpa overlap).

## Queue (opsional)
- Jika menggunakan queue `database`, jalankan worker via Supervisor:
```
[program:monitoringbpjs-queue]
command=/usr/bin/php /var/www/monitoringbpjs/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/var/www/monitoringbpjs
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/monitoringbpjs-queue.log
stderr_logfile=/var/log/supervisor/monitoringbpjs-queue-error.log
user=www-data
```
- `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start monitoringbpjs-queue`

## Hardening & Kinerja
- Nyalakan OPcache, set `pm` PHP-FPM sesuai kapasitas.
- `APP_DEBUG=false`, `LOG_CHANNEL=daily`, gunakan `logrotate`.
- Batasi `client_max_body_size` di Nginx jika perlu.

## Troubleshooting
- Cek `storage/logs/laravel.log` untuk error runtime.
- Jalankan manual: `php artisan bpjs:monitor --once` untuk uji cek.
- Pastikan asset build ada di `public/build` dan `try_files` diarahkan ke `index.php`.