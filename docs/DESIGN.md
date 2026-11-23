# ZNU IT Helpdesk — وثيقة التصميم

> نظام تذاكر الدعم الفني (IT) للجامعة: الموظفون والدكاترة والإداريون يرسلون طلبات لأعطال الأجهزة
> والشاشات والصوت والشبكة، وفريق الـ IT يستقبلها ويوزعها ويتابعها حتى الحل.
>
> الإصدار: 0.1 (مسودة) — يُبنى منفصلًا الآن، ويُربط بـ ZNU Portal لاحقًا.

---

## 1. الأهداف والنطاق

### الأهداف
1. **إرسال طلب في ثوانٍ.** صاحب المكان الثابت لا يُدخل مكانه ولا جهازه كل مرة.
2. **يصل الطلب إلى الـ IT كاملًا:** من؟ أين بالضبط (مبنى / دور / قاعة)؟ أي جهاز؟ ما المشكلة؟ ما مدى العجلة؟
3. **متابعة شفافة:** صاحب الطلب يرى الحالة لحظيًا، والـ IT يعرف ما المفتوح ومن يعمل عليه.
4. **بيانات للإدارة:** أكثر الأماكن والأجهزة أعطالًا، وأوقات الاستجابة والحل، وأداء الفنيين.

### داخل النطاق (الإصدار الأول)
- تسجيل الدخول بحسابات النظام نفسه.
- إدارة الأماكن (مبانٍ / أدوار / قاعات) والأجهزة والمستخدمين ومكانهم الثابت.
- إنشاء الطلبات: "جهازي / مكتبي"، أو "مكان آخر"، أو عن طريق QR القاعة.
- دورة حياة التذكرة، والتعيين، والتعليقات، والصور، والتقييم.
- إشعارات داخل النظام وبالبريد، وتحديث لحظي.
- Dashboard وتقارير، واستيراد من Excel.

### خارج النطاق الآن
- الربط بـ ZNU Portal (التصميم يجهّز له، انظر §13).
- قطع الغيار والمخزون والمشتريات.
- استقبال الطلبات بالبريد أو واتساب.

---

## 2. التقنيات (مطابقة لـ ZNU Portal)

| الطبقة | التقنية |
|---|---|
| Backend | PHP 8.4، Laravel 13 |
| لوحة الـ IT والإدارة | Filament 5 على `/admin` |
| واجهة مقدمي الطلبات | Vue 3، Vite، Pinia، vue-router، vue-i18n (عربي/إنجليزي، RTL)، Tailwind 4، PWA |
| المصادقة | Laravel Sanctum (SPA بكوكي HttpOnly + CSRF) |
| التحديث اللحظي | Laravel Reverb + laravel-echo |
| الأدوار والصلاحيات | spatie/laravel-permission |
| سجل التدقيق | spatie/laravel-activitylog |
| الصور | spatie/laravel-medialibrary (أو تخزين Laravel مباشرة) |
| الاستيراد والتصدير | Laravel Excel (maatwebsite/excel)، ومعه mPDF للتقارير |
| QR | simplesoftwareio/simple-qrcode |
| الداتا / الكاش / الطوابير | MySQL 8، Redis 7 |
| التشغيل | Nginx + PHP-FPM + Supervisor (queue workers + Reverb) + cron |
| الاختبارات | PHPUnit، Vitest، Playwright |

---

## 3. المستخدمون والأدوار

| الدور (Spatie) | من هو | ماذا يفعل |
|---|---|---|
| `requester` | موظف / إداري / دكتور | ينشئ طلبات، يرى طلباته فقط، يعلّق، يقيّم بعد الحل |
| `technician` | فني IT | يرى الطلبات المعيّنة له وطلبات تخصصاته، يغيّر الحالة، يعلّق (عام / داخلي) |
| `it_supervisor` | مسؤول الـ IT | يرى كل الطلبات، يعيّن ويحوّل، يدير الأماكن والأجهزة والمستخدمين، يرى التقارير |
| `super_admin` | مدير النظام | كل ما سبق + الإعدادات والأدوار |

- المستخدم يمكن أن يحمل أكثر من دور (مثلًا فني ينشئ طلبًا لنفسه).
- `requester` يدخل الـ PWA فقط. باقي الأدوار تدخل Filament (ويمكنها أيضًا دخول الـ PWA).

### الصلاحيات (Permissions)
```
tickets.create          tickets.view_own        tickets.view_assigned
tickets.view_all        tickets.assign          tickets.change_status
tickets.comment_internal tickets.delete
locations.manage        assets.manage           users.manage
categories.manage       reports.view            settings.manage
```

---

## 4. نموذج البيانات

### 4.1 مخطط العلاقات (مختصر)

```
Building 1──* Floor 1──* Room 1──* Asset
                           │          │
                           │          └── assigned_user_id ──> User (جهاز شخصي، اختياري)
                           │
User ── default_room_id ───┘   (المكان الثابت، اختياري)

Ticket ──> requester (User)      Ticket ──> room
Ticket ──> asset (اختياري)       Ticket ──> category
Ticket ──> assignee (User)       Ticket 1──* TicketComment
Ticket 1──* TicketStatusChange   Ticket 1──* Media (صور)
Category ──* User (فنيو التخصص، عبر category_technician)
```

### 4.2 الجداول

#### `buildings`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| code | varchar(20) unique | `MAIN`، `ENG`، `COM` |
| name_ar / name_en | varchar | المبنى الرئيسي / Main Building |
| is_active | bool | |
| timestamps, deleted_at | | Soft delete |

#### `floors`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| building_id | FK | |
| level | smallint | 0 = أرضي، -1 = بدروم |
| name_ar / name_en | varchar | "الدور الثاني" |
| unique(building_id, level) | | |

#### `rooms`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| floor_id | FK | |
| code | varchar(30) | `204`، `LAB-3` |
| name_ar / name_en | varchar | "مكتب شؤون الطلاب"، "قاعة 105" |
| type | enum | `office`، `lecture_hall`، `lab`، `meeting_room`، `server_room`، `other` |
| qr_token | char(26) unique | ULID يُطبع في QR على باب القاعة |
| is_active | bool | |
| unique(floor_id, code) | | |

#### `departments`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| name_ar / name_en | varchar | شؤون الطلاب، كلية الهندسة… |
| parent_id | FK nullable | كلية ← قسم |

#### `users` (تعديلات على جدول Laravel)
| العمود | النوع | ملاحظات |
|---|---|---|
| name | varchar | |
| email | varchar unique | |
| phone / extension | varchar nullable | الرقم الداخلي مهم للفني |
| job_title | varchar nullable | |
| department_id | FK nullable | |
| **default_room_id** | FK nullable → rooms | **المكان الثابت**. فارغ = مكانه متغير |
| external_id | varchar nullable unique | للربط مع ZNU Portal لاحقًا |
| locale | char(2) | `ar` / `en` |
| is_active | bool | |

#### `asset_types`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| code | varchar unique | `PC`، `LAPTOP`، `PRINTER`، `SCREEN`، `PROJECTOR`، `SPEAKER`، `MIC`، `AMPLIFIER`، `SWITCH`، `ACCESS_POINT`، `RACK`، `PHONE` |
| name_ar / name_en | varchar | |
| code_prefix | varchar(5) | يُستخدم في توليد كود الجهاز: `PC`، `PR`، `PJ`… |

#### `assets` (الأجهزة)
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| asset_tag | varchar(30) unique | الكود الملصق على الجهاز: `PC-0123` |
| asset_type_id | FK | |
| room_id | FK | مكان الجهاز الحالي |
| **assigned_user_id** | FK nullable → users | جهاز شخصي. فارغ = جهاز مشترك / جهاز قاعة |
| brand / model | varchar nullable | Dell OptiPlex 7090 |
| serial_number | varchar nullable | |
| specs | json nullable | CPU، RAM، OS، IP… |
| status | enum | `in_service`، `under_repair`، `retired` |
| purchased_at / warranty_until | date nullable | |
| notes | text nullable | |
| timestamps, deleted_at | | |

> الحركة (نقل جهاز من مكان لمكان أو من شخص لآخر) تُسجَّل تلقائيًا في activity log.

#### `categories` (أنواع المشاكل)
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| parent_id | FK nullable | تصنيف رئيسي ← فرعي |
| name_ar / name_en | varchar | |
| icon | varchar nullable | لعرضها كبطاقات في الفورم |
| default_priority | enum nullable | |
| sort_order / is_active | | |

**البيانات الأولية المقترحة:**

| رئيسي | فرعي |
|---|---|
| كمبيوتر / لابتوب | لا يعمل، بطء، شاشة زرقاء، برنامج، فيروس، تثبيت برنامج |
| طابعة / ماسح | لا تطبع، ورق عالق، حبر/تونر، تعريف |
| شاشات وعرض | الشاشة لا تعمل، البروجيكتور، كابل HDMI، لا صورة من اللابتوب |
| صوت | السماعات، الميكروفون، الأمبلفاير، تشويش |
| شبكة وإنترنت | لا يوجد إنترنت، Wi-Fi ضعيف، نقطة شبكة، راك / سويتش |
| حسابات وصلاحيات | بريد جامعي، كلمة مرور، صلاحية على نظام |
| تليفون داخلي | |
| أخرى | |

#### `category_technician` (pivot)
`category_id`، `user_id`. يحدد فنيي كل تخصص، ويُستخدم في التوزيع التلقائي (§7).

#### `tickets`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| number | varchar(20) unique | `IT-2026-000152` |
| requester_id | FK → users | |
| created_by_id | FK → users | عادةً = requester. يختلف لو الـ IT سجّل الطلب بالنيابة (تليفون) |
| category_id | FK | |
| room_id | FK | |
| asset_id | FK nullable | |
| **location_snapshot** | json | `{building, floor, room}` بالأسماء وقت الإنشاء، فالتذكرة تحتفظ بمكانها حتى لو انتقل الموظف |
| source | enum | `my_device`، `other_location`، `qr`، `admin` |
| title | varchar(150) | يُولَّد تلقائيًا إن لم يُكتب: "شاشات وعرض – البروجيكتور – قاعة 105" |
| description | text nullable | |
| priority | enum | `low`، `normal`، `high`، `urgent` |
| is_class_in_progress | bool | "توجد محاضرة الآن". ترفع الأولوية إلى `urgent` تلقائيًا |
| status | enum | انظر §5 |
| assignee_id | FK nullable → users | |
| first_response_at | datetime nullable | أول مرة تحولت فيها التذكرة إلى `acknowledged` |
| resolved_at / closed_at | datetime nullable | |
| due_at | datetime nullable | حسب الأولوية (§8) |
| rating | tinyint nullable | 1–5 |
| rating_comment | text nullable | |
| timestamps, deleted_at | | |

**Indexes:** `(status, priority)`، `(assignee_id, status)`، `(requester_id, created_at)`، `(room_id)`، `(asset_id)`، `(category_id)`.

#### `ticket_comments`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| ticket_id | FK | |
| user_id | FK | |
| body | text | |
| is_internal | bool | ملاحظة داخلية للـ IT لا يراها صاحب الطلب |
| timestamps | | |

#### `ticket_status_changes`
| العمود | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| ticket_id | FK | |
| from_status / to_status | enum | |
| user_id | FK | |
| note | text nullable | |
| created_at | | |

> جدول مخصص بدل الاعتماد على activity log وحده، لأن التقارير (أوقات الاستجابة والحل) تحتاج استعلامات سريعة عليه.

#### `media`
صور التذكرة والتعليقات (من spatie/medialibrary). الحد: 5 صور × 5MB، والأنواع jpg/png/webp/heic.

#### `notifications`
جدول Laravel القياسي للإشعارات داخل النظام.

---

## 5. دورة حياة التذكرة

```
              ┌─────────────── cancelled (من صاحب الطلب قبل الاستلام)
              │
  new ──> acknowledged ──> in_progress ──> resolved ──> closed
   │           │               │   ▲          │
   │           └──> on_hold ───┘   │          └──> reopened ──> in_progress
   │                (انتظار قطعة / │                  (صاحب الطلب: "المشكلة لم تُحل"
   │                 موعد)         │                   خلال 7 أيام من resolved)
   └── assign ─────────────────────┘
```

| الحالة | المعنى | من يحوّل إليها |
|---|---|---|
| `new` | طلب جديد لم يُستلم | النظام |
| `acknowledged` | استلمه فني ("في الطريق") | فني / مشرف |
| `in_progress` | جاري العمل عليه | فني |
| `on_hold` | معلّق (قطعة غيار، موعد، طرف خارجي). **يتطلب ملاحظة** | فني |
| `resolved` | تم الحل، بانتظار تأكيد صاحب الطلب | فني |
| `reopened` | صاحب الطلب رفض الحل | صاحب الطلب |
| `closed` | مغلق نهائيًا (بتأكيد صاحب الطلب أو تلقائيًا بعد 3 أيام من `resolved`) | صاحب الطلب / النظام |
| `cancelled` | ألغاه صاحب الطلب قبل الاستلام | صاحب الطلب |

- التحويلات المسموحة تُفرض في `TicketStateMachine` (خدمة واحدة)، ولا يُغيَّر `status` مباشرة من أي مكان آخر.
- كل تحويل: يكتب في `ticket_status_changes`، ويطلق event `TicketStatusChanged` (إشعارات + Reverb).
- عند `closed`: يُطلب من صاحب الطلب التقييم (اختياري).

---

## 6. سيناريوهات الاستخدام

### 6.1 صاحب مكان ثابت: "مشكلة في جهازي / مكتبي"
1. يفتح الـ PWA ويضغط "طلب جديد". الاختيار الافتراضي هو **جهازي / مكتبي** لأن `default_room_id` موجود.
2. يظهر المكان جاهزًا: *المبنى الرئيسي – الدور 2 – مكتب 204 (شؤون الطلاب)*.
3. يظهر **أجهزته** (`assets.assigned_user_id = me`)، و**أجهزة مكتبه المشتركة** (`room_id = default_room_id`، ولا صاحب لها). يختار جهازًا، أو "مشكلة عامة في المكتب".
4. يختار نوع المشكلة (بطاقات بأيقونات)، ثم الفرعي.
5. وصف (اختياري) + صورة (اختياري) + الأولوية، ثم إرسال.

**3 إلى 4 ضغطات، دون كتابة.**

### 6.2 مكان متغير: "مشكلة في مكان آخر"
1. "طلب جديد"، ثم **مكان آخر** (الافتراضي لمن ليس له `default_room_id`).
2. مبنى ← دور ← قاعة (قوائم متسلسلة + بحث برقم القاعة).
3. تظهر أجهزة القاعة، فيختار الجهاز أو "مشكلة عامة في القاعة".
4. نوع المشكلة + "توجد محاضرة الآن؟" + وصف/صورة، ثم إرسال.
5. آخر 5 أماكن استخدمها تظهر كاختصارات.

### 6.3 عن طريق QR القاعة
1. على باب كل قاعة ملصق QR فيه: `https://it.znu.edu.eg/r/{qr_token}`.
2. الـ PWA يفتح الفورم والمكان محدد مسبقًا، وأجهزة القاعة معروضة.
3. لو المستخدم لم يسجّل دخوله: تسجيل دخول، ثم يعود للفورم نفسه.

### 6.4 الـ IT يسجّل طلبًا بالنيابة (اتصال هاتفي)
من Filament: "طلب جديد" ← يختار صاحب الطلب. المكان والأجهزة تُملأ من بروفايله. `source = admin`.

### 6.5 ما يراه الـ IT
```
#IT-2026-000152          [عاجل]  [جديد]
د. أحمد محمد — شؤون الطلاب — داخلي 2145
📍 المبنى الرئيسي › الدور 2 › مكتب 204
💻 PC-0123 — Dell OptiPlex 7090 — Windows 11  (3 أعطال سابقة ↗)
⚠️ كمبيوتر › لا يعمل
"الجهاز لا يفتح منذ الصباح"   📷 1
منذ 10 دقائق
[استلام]  [تعيين ▾]
```

---

## 7. التوزيع (Assignment)

إعداد في Settings، بثلاثة أوضاع:
1. **يدوي** (الافتراضي في البداية): كل الطلبات تصل للمشرف ويعيّنها.
2. **حسب التخصص:** يُعيَّن تلقائيًا لفني من `category_technician` على الأقل حِملًا (أقل عدد تذاكر مفتوحة).
3. **حسب التخصص + المبنى (لاحقًا):** إضافة `building_technician` لو لكل مبنى فني مقيم.

- لا يوجد فني للتخصص: يبقى الطلب `new` بدون assignee ويظهر في "غير معيّنة".
- الفني يستطيع **الاستلام الذاتي** (Take) لأي طلب غير معيّن في تخصصه.
- التنفيذ في `TicketAssignmentService`.

---

## 8. الأولوية وأهداف الوقت

| الأولوية | الاستجابة (acknowledged) | الحل (resolved) | مثال |
|---|---|---|---|
| `urgent` | 15 دقيقة | 2 ساعة | محاضرة جارية بلا صوت أو عرض، انقطاع شبكة مبنى |
| `high` | 1 ساعة | 8 ساعات عمل | جهاز موظف خدمة طلاب متوقف |
| `normal` | 4 ساعات | يومان عمل | طابعة، بطء |
| `low` | يوم عمل | 5 أيام عمل | تثبيت برنامج غير عاجل |

- القيم قابلة للتعديل من Settings، وتُحسب بساعات العمل (مثلًا 8:00–16:00، الأحد–الخميس، مع الإجازات).
- `is_class_in_progress = true` يجعل الأولوية `urgent`.
- Scheduler كل 5 دقائق: الطلبات التي تجاوزت `due_at` تُعلَّم "متأخرة" ويُرسل إشعار للمشرف.

---

## 9. الإشعارات والتحديث اللحظي

### الأحداث والمستلمون
| الحدث | صاحب الطلب | الفني المعيّن | فنيو التخصص | المشرف |
|---|---|---|---|---|
| طلب جديد | ✓ (تأكيد) | | ✓ (لو غير معيّن) | ✓ |
| تعيين | ✓ ("فلان في الطريق") | ✓ | | |
| تغيير حالة | ✓ | | | |
| تعليق عام | ✓ (لو كتبه الـ IT) | ✓ (لو كتبه صاحب الطلب) | | |
| تعليق داخلي | | ✓ | | |
| تجاوز الوقت | | ✓ | | ✓ |
| إعادة فتح | | ✓ | | ✓ |

### القنوات
- **داخل النظام** (database notifications + جرس في الـ PWA وFilament).
- **Reverb** للتحديث اللحظي:
  - `private-user.{id}`: إشعارات المستخدم
  - `private-ticket.{id}`: تحديثات تذكرة مفتوحة (حالة، تعليقات)
  - `private-it.queue`: قائمة الطلبات في لوحة الـ IT
- **بريد** (عبر queue).
- **Web Push** للـ PWA (مرحلة 2).
- **Telegram** للفنيين (مرحلة 2، اختياري).

---

## 10. الشاشات

### 10.1 الـ PWA (مقدمو الطلبات) — عربي افتراضي، RTL
| المسار | الشاشة |
|---|---|
| `/login` | تسجيل الدخول |
| `/` | الرئيسية: زر "طلب جديد" كبير + طلباتي المفتوحة + آخر الإشعارات |
| `/tickets/new` | فورم الطلب (اختيار: جهازي / مكان آخر) |
| `/r/:qrToken` | فورم الطلب بقاعة محددة من QR |
| `/tickets` | طلباتي (مفتوحة / مغلقة) |
| `/tickets/:number` | تفاصيل الطلب: الحالة كخط زمني، التعليقات، تأكيد الحل / إعادة الفتح، التقييم |
| `/profile` | بياناتي، مكاني الثابت (عرض فقط)، أجهزتي، اللغة، كلمة المرور |
| `/notifications` | الإشعارات |

### 10.2 Filament (الـ IT والإدارة) — `/admin`
**Resources:**
- `TicketResource`: جدول بفلاتر (حالة، أولوية، مبنى، تصنيف، فني، متأخرة)، وتبويبات: *غير معيّنة / طلباتي / مفتوحة / متأخرة / الكل*. صفحة عرض فيها بيانات الطلب، والخط الزمني، والتعليقات (عام/داخلي)، وأزرار تغيير الحالة والتعيين، وبطاقة الجهاز وتاريخ أعطاله.
- `BuildingResource` مع RelationManagers للأدوار والقاعات.
- `RoomResource`: أجهزة القاعة + طلباتها + **طباعة QR** (فردي أو جماعي PDF لمبنى كامل).
- `AssetResource`: بيانات الجهاز + صاحبه + مكانه + تاريخ الأعطال + سجل الحركة.
- `UserResource`: الأدوار، القسم، **المكان الثابت**، الأجهزة المعيّنة.
- `CategoryResource`: شجرة التصنيفات + فنيو كل تصنيف.
- `DepartmentResource`، `AssetTypeResource`.

**Pages:** Dashboard، التقارير، الإعدادات (التوزيع، أهداف الوقت، ساعات العمل، الإجازات)، الاستيراد.

**Widgets (Dashboard):**
- بطاقات: جديدة / مفتوحة / متأخرة / تم حلها اليوم
- طلبات حسب الحالة، طلبات حسب التصنيف
- أكثر 10 قاعات وأكثر 10 أجهزة أعطالًا (آخر 30 يومًا)
- متوسط وقت الاستجابة والحل (أسبوعيًا)
- حِمل الفنيين (المفتوح لكل فني)

---

## 11. الـ API (للـ PWA الآن، وللبورتال لاحقًا)

الكل تحت `/api/v1`، ومحمي بـ `auth:sanctum` ما عدا تسجيل الدخول.

```
POST   /auth/login                  GET    /auth/me
POST   /auth/logout

GET    /me/context                  → المكان الثابت + أجهزتي + أجهزة مكتبي + آخر الأماكن
GET    /buildings                   → مع الأدوار
GET    /floors/{floor}/rooms
GET    /rooms/{room}/assets
GET    /rooms/by-qr/{token}         → القاعة + أجهزتها
GET    /categories                  → شجرة

GET    /tickets                     → طلباتي (?status=open|closed)
POST   /tickets
GET    /tickets/{number}
POST   /tickets/{number}/comments
POST   /tickets/{number}/attachments
POST   /tickets/{number}/confirm    → resolved → closed
POST   /tickets/{number}/reopen
POST   /tickets/{number}/cancel
POST   /tickets/{number}/rating

GET    /notifications               POST /notifications/read
```

**مثال `POST /tickets`:**
```json
{
  "source": "my_device",
  "room_id": 412,
  "asset_id": 1203,
  "category_id": 17,
  "priority": "high",
  "is_class_in_progress": false,
  "description": "الجهاز لا يفتح منذ الصباح"
}
```
- لو `source = my_device` و`room_id` غير مُرسل، يُؤخذ من `default_room_id`.
- التحقق: `asset_id` (لو أُرسل) يجب أن يكون في `room_id`، أو مملوكًا لصاحب الطلب.
- Rate limit: 10 طلبات / ساعة / مستخدم.
- كل منطق الإنشاء في `CreateTicketAction`، ويستدعيه الـ API وFilament معًا (نفس نمط البورتال: لا منطق في الـ Controllers).

---

## 12. الاستيراد الأولي (Excel)

ملفات قوالب تُحمَّل من صفحة الاستيراد، وتُرفع بالترتيب:

1. **الأماكن:** `building_code, building_name_ar, floor_level, floor_name_ar, room_code, room_name_ar, room_type`
2. **الأقسام:** `name_ar, parent_name_ar`
3. **المستخدمون:** `name, email, phone, extension, job_title, department, building_code, floor_level, room_code, role`
4. **الأجهزة:** `asset_tag, type_code, brand, model, serial, building_code, floor_level, room_code, assigned_user_email`

- معاينة قبل الحفظ + تقرير بالأخطاء لكل صف.
- Upsert بالمفتاح الطبيعي (`code` / `email` / `asset_tag`)، فإعادة رفع الملف تحدّث ولا تكرر.
- يُنفَّذ في queue job للملفات الكبيرة.

---

## 13. الجاهزية للربط مع ZNU Portal

- **نفس التقنيات** (Laravel 13 / Sanctum / Spatie / Vue 3)، فيمكن لاحقًا إما:
  - **(أ)** الإبقاء عليه نظامًا منفصلًا ودخول موحّد (SSO). البورتال يصدر token، والـ helpdesk يربط المستخدم بـ `users.external_id`.
  - **(ب)** دمجه كـ module داخل البورتال (`app/Modules/Helpdesk` أو package داخلية)، لأن المنطق كله في Actions/Services مستقلة.
- **API موثّق ومُصدَّر بإصدار** (`/api/v1`) يمكن للبورتال استدعاؤه لعرض "طلباتي" أو إنشاء طلب.
- **Webhooks صادرة (لاحقًا):** `ticket.created` و`ticket.status_changed`.
- أسماء الجداول بالبادئة الافتراضية (بلا تعارض متوقع مع البورتال)، ويمكن إضافة بادئة `hd_` عند الدمج إن احتجنا.

---

## 14. الأمان

- Sanctum SPA (كوكي HttpOnly + CSRF) مثل البورتال، وFilament بجلسة Laravel.
- Policies لكل Model (`TicketPolicy`: صاحب الطلب يرى طلبه، الفني يرى المعيّن له وتخصصاته، المشرف يرى الكل).
- التعليقات الداخلية لا تُرجع أبدًا في API الـ requester (تُفلتر في Resource، وتُختبر).
- رفع الصور: تحقق من النوع الفعلي (MIME)، وإعادة تسمية، وتخزين خارج `public` مع روابط موقّعة.
- `qr_token` عشوائي (ULID) وليس رقم القاعة، ولا يكشف بيانات بدون تسجيل دخول.
- Rate limiting على تسجيل الدخول وإنشاء الطلبات.
- Activity log على: التذاكر، الأجهزة، المستخدمين، الأدوار، الإعدادات.

---

## 15. هيكل المشروع

```
znu-it-helpdesk/
├── backend/                         Laravel 13
│   ├── app/
│   │   ├── Actions/Tickets/         CreateTicket, AssignTicket, ChangeTicketStatus, AddComment, RateTicket
│   │   ├── Services/                TicketStateMachine, TicketAssignmentService, DueDateCalculator, TicketNumberGenerator
│   │   ├── Enums/                   TicketStatus, TicketPriority, TicketSource, RoomType, AssetStatus
│   │   ├── Events/ Listeners/       TicketCreated, TicketAssigned, TicketStatusChanged, TicketCommented
│   │   ├── Notifications/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Http/Requests/  Http/Resources/
│   │   ├── Imports/                 LocationsImport, UsersImport, AssetsImport
│   │   ├── Filament/                Resources / Pages / Widgets
│   │   └── Console/                 MarkOverdueTickets, AutoCloseResolvedTickets
│   ├── database/migrations, seeders (أدوار، تصنيفات، أنواع أجهزة، بيانات تجريبية)
│   ├── routes/                      api.php, web.php, console.php, channels.php
│   └── tests/                       Feature + Unit
├── pwa/                             Vue 3 PWA
│   └── src/  pages/ components/ stores/ api/ i18n/ composables/
├── deploy/                          nginx, supervisor, cron (على نمط البورتال)
└── docs/                            DESIGN.md, API.md
```

---

## 16. خطة التنفيذ (Milestones)

| # | المرحلة | المخرجات | التقدير |
|---|---|---|---|
| M1 | الأساس | مشروع Laravel + Filament + Sanctum + Spatie، الأدوار، الـ migrations كلها، seeders | 3–4 أيام |
| M2 | الأماكن والأجهزة | Resources للمباني/الأدوار/القاعات/الأجهزة/المستخدمين/التصنيفات + الاستيراد من Excel + طباعة QR | 4–5 أيام |
| M3 | قلب التذاكر | Actions + State machine + التوزيع + Policies + API + اختبارات | 5–6 أيام |
| M4 | لوحة الـ IT | TicketResource كامل (تبويبات، فلاتر، صفحة العرض، التعليقات، التعيين) | 3–4 أيام |
| M5 | الـ PWA | تسجيل الدخول، الرئيسية، فورم الطلب (3 سيناريوهات)، طلباتي، التفاصيل، QR | 6–7 أيام |
| M6 | اللحظي والإشعارات | Reverb channels، الإشعارات (داخلي + بريد)، الجرس | 2–3 أيام |
| M7 | التقارير والتشغيل | Dashboard widgets، التقارير + تصدير، Scheduler (متأخرة / إغلاق تلقائي)، deploy | 3–4 أيام |
| M8 | تجربة فعلية | تشغيل تجريبي على مبنى واحد، جمع ملاحظات، تعديلات | أسبوع |

**الإجمالي التقريبي: 5 إلى 7 أسابيع لمطوّر واحد**، قبل التجربة الفعلية.

---

## 17. قرارات مفتوحة (تحتاج تأكيد)

1. **الدومين:** `it.znu.edu.eg` أم مسار داخل البورتال؟
2. **تسجيل الدخول المبدئي:** بالبريد الجامعي + كلمة مرور يضعها الـ IT؟ أم تفعيل برابط على البريد؟
3. **فريق الـ IT:** كم فنيًا؟ هل لكل مبنى أو كلية فني مقيم (يؤثر على §7)؟
4. **ساعات العمل والإجازات** الرسمية لحساب الأوقات.
5. **هل يُسمح لصاحب الطلب بتحديد الأولوية**، أم يقترحها والـ IT يعتمدها؟
6. **ترقيم الأجهزة:** هل توجد أكواد جرد حالية على الأجهزة نعتمدها كـ `asset_tag`؟
7. **البريد الصادر:** أي SMTP سنستخدم؟
