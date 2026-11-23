# ZNU IT Helpdesk

نظام تذاكر الدعم الفني للجامعة. التصميم الكامل في [docs/DESIGN.md](docs/DESIGN.md).

| الجزء | الفولدر | التقنية |
|---|---|---|
| السيرفر + لوحة الـ IT (`/admin`) | `backend/` | Laravel 13، Filament 5، Sanctum، Reverb |
| تطبيق الموظفين والدكاترة (PWA) | `pwa/` | Vue 3، Vite، Pinia، Tailwind 4 |

## التشغيل لأول مرة

```powershell
cd backend
copy .env.example .env
php artisan key:generate
php artisan reverb:install
php artisan migrate --seed
```

```powershell
cd pwa
npm install
copy .env.example .env.local
```

في `pwa/.env.local` ضع قيمة `VITE_REVERB_APP_KEY` = نفس `REVERB_APP_KEY` من `backend/.env`.

حسابات التجربة (من `DemoSeeder`) كلمة مرورها في `DEMO_USER_PASSWORD` داخل `backend/.env`:
`admin@znu.test`، `supervisor@znu.test`، `tech.pc@znu.test`، `tech.av@znu.test`، `staff@znu.test` (مكانه ثابت)، `doctor@znu.test` (مكانه متغير).

## التشغيل اليومي (5 عمليات)

| العملية | الأمر | الوظيفة |
|---|---|---|
| السيرفر | `php artisan serve` (في `backend`) | الـ API ولوحة الـ IT على `localhost:8000` |
| الطابور | `php artisan queue:work` | الإشعارات والإيميلات والبث |
| Reverb | `php artisan reverb:start` | التحديث اللحظي على المنفذ 8080 |
| المجدول | `php artisan schedule:work` | القفل التلقائي وتنبيهات التأخير |
| التطبيق | `npm run dev` (في `pwa`) | تطبيق الموظفين على `localhost:5173` |

لو Reverb أو الطابور مش شغالين، النظام نفسه بيكمل شغل عادي، بس الإشعارات اللحظية والإيميلات بتتأخر لحد ما يشتغلوا.

## الاختبارات

```powershell
cd backend
php vendor/bin/phpunit
```

```powershell
cd pwa
npx vitest run
```
