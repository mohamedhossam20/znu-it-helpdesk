# التشغيل على السيرفر

نفس نمط ZNU Portal: **Ubuntu + Nginx + PHP-FPM 8.4 + MySQL 8 + Redis 7 + Supervisor + cron**، والنظامين (لوحة الـ IT وتطبيق الموظفين) على **دومين واحد**.

## الملفات

| الملف | يتنسخ إلى |
|---|---|
| `nginx/it-helpdesk.conf` | `/etc/nginx/sites-available/` + رابط في `sites-enabled` |
| `supervisor/it-helpdesk.conf` | `/etc/supervisor/conf.d/` (طابورين + Reverb) |
| `cron/it-helpdesk` | `/etc/cron.d/` (المجدول) |
| `deploy.sh` | يتشغّل مع كل تحديث |

## مرة واحدة

1. امتدادات PHP: `mbstring intl gd zip exif curl mysql redis bcmath`.
2. داتابيز MySQL ومستخدم خاص بيها.
3. `/var/www/it-helpdesk/shared/.env` (من `backend/.env.example`) بالقيم دي:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://it.znu.edu.eg
APP_LOCALE=ar
FRONTEND_URL=https://it.znu.edu.eg

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=it_helpdesk
DB_USERNAME=it_helpdesk
DB_PASSWORD=********

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

SANCTUM_STATEFUL_DOMAINS=it.znu.edu.eg

BROADCAST_CONNECTION=reverb
# Laravel publishes to Reverb on the server itself. Browsers connect through
# Nginx (/app on 443) using the values in pwa.env below.
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=it-support@znu.edu.eg
MAIL_FROM_NAME="الدعم الفني"

HELPDESK_HOLIDAYS=2027-01-07,2027-04-25
```

بعدها: `php artisan key:generate` و`php artisan reverb:install`، وبعد أول deploy: `php artisan db:seed --class=RolesAndPermissionsSeeder` و`AssetTypeSeeder` و`CategorySeeder`. **لا تشغّل `DemoSeeder` على السيرفر**، ده للتجربة بس.

4. `/var/www/it-helpdesk/shared/pwa.env`:

```dotenv
VITE_REVERB_APP_KEY=<نفس REVERB_APP_KEY>
VITE_REVERB_HOST=it.znu.edu.eg
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

5. أول مستخدم (مدير النظام):

```bash
php artisan tinker --execute='App\Models\User::create(["name"=>"...","email"=>"...","password"=>"..."])->assignRole("super_admin");'
```

## كل تحديث

```bash
sudo -u www-data bash /var/www/it-helpdesk/current/deploy/deploy.sh main
```

## اختبار بعد التشغيل

- `https://it.znu.edu.eg/up` لازم يرجع 200.
- `https://it.znu.edu.eg/admin` يفتح لوحة الـ IT.
- `https://it.znu.edu.eg/` يفتح تطبيق الموظفين، وفي الـ DevTools يظهر اتصال WebSocket على `/app/...`.
- `supervisorctl status` يبيّن طابورين وReverb في حالة RUNNING.
