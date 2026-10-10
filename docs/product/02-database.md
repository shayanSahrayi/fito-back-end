# طراحی دیتابیس

موتور هدف MySQL است. تست پیش‌فرض پروژه SQLite در حافظه است، پس کلید یکتای مشروط با ستون قابل‌تهیِ نگهداری‌شده توسط سرویس دامنه ساخته می‌شود، نه generated column مخصوص MySQL. چند مقدار NULL در ایندکس یکتا مجاز است.

## ۱. قراردادهای مشترک

| موضوع | قرارداد |
| --- | --- |
| شناسه | `BIGINT UNSIGNED` خودکار |
| زمان لحظه‌ای | `DATETIME` به UTC |
| تاریخ مدنی | `DATE` به تقویم `Asia/Tehran` |
| پول | `BIGINT UNSIGNED` ریال، بدون اعشار |
| وزن | گرم، صحیح |
| طول | میلی‌متر، صحیح |
| انرژی | کیلوکالری صحیح |
| درشت‌مغذی | میلی‌گرم صحیح |
| درصد | basis point؛ ۱۸٫۲۵٪ برابر ۱۸۲۵ |
| BMI | مقدار ضربدر ۱۰۰؛ ۲۳٫۴۵ برابر ۲۳۴۵ |
| تلفن | `CHAR(11)` به شکل `09xxxxxxxxx` |
| کد ملی | `CHAR(10)`؛ رقم کنترلی در برنامه بررسی می‌شود |
| وضعیت | `VARCHAR(32)` با مجموعه بسته هر جدول |
| حذف | برای داده محصول `DELETE` مسیر عادی نیست |

مقدار ناموجود ذخیره نمی‌شود. برای معیار مشاهده، نبودن ردیف یعنی ناموجود. صفر مقدار واقعی است و جای خالی را پر نمی‌کند.

JSON فقط در دو جا استفاده می‌شود: `ai_generations.proposal` چون پیش‌نویس متغیر است و تا قبل از تبدیل به جدول‌های برنامه منبع حقیقت نیست؛ و `payment_attempts.gateway_payload` چون بدنه خام درگاه ساختار محصولی ندارد. ست، وعده، قیمت، قابلیت پلن و دسترسی JSON نیستند.

زیرساخت Laravel که دامنه محصول نیست و حفظ می‌شود: `sessions`، `cache`، `cache_locks`، `jobs`، `job_batches`، `failed_jobs`. جدول `password_reset_tokens` محصول نیست و در F01 حذف می‌شود. `personal_access_tokens` را migration خود Sanctum می‌سازد.

## ۲. فهرست موجودیت‌ها

| دامنه | جدول | هدف |
| --- | --- | --- |
| هویت | `users` | حساب شخص |
| هویت | `signup_drafts` | ثبت‌نام ناتمام، قبل از وجود حساب |
| هویت | `otp_challenges` | چالش یک‌بارمصرف شماره |
| هویت | `platform_roles` | نقش مدیر پلتفرم |
| هویت | `coach_profiles` | محدوده کاری مربی |
| هویت | `athlete_profiles` | پنل ورزشکار |
| تجاری | `plans` | تعریف قابل‌ویرایش پلن برای فروش آینده |
| تجاری | `plan_features` | قابلیت‌های فعلی تعریف پلن |
| تجاری | `orders` | سفارش و تصویر ثابت شرایط خرید |
| تجاری | `order_features` | قابلیت‌های همان سفارش |
| تجاری | `payment_attempts` | هر تلاش درگاه |
| تجاری | `payments` | پول تأییدشده |
| تجاری | `access_periods` | پنجره دسترسی و ظرفیت |
| تجاری | `ai_usage_entries` | دفتر مصرف سهمیه AI |
| رابطه | `athlete_invitations` | دعوت پیش از رابطه |
| رابطه | `coaching_relationships` | رابطه یک مربی و یک ورزشکار |
| رابطه | `coaching_relationship_events` | شروع، پایان و فعال‌سازی مجدد |
| حرکت | `muscles` | عضله هدف حرکت |
| حرکت | `body_model_areas` | ناحیه نمایش مدل بدن |
| حرکت | `muscle_body_area` | نگاشت عضله به ناحیه نمایش |
| حرکت | `exercises` | حرکت پلتفرم یا خصوصی مربی |
| حرکت | `exercise_media` | رسانه حرکت |
| حرکت | `exercise_muscles` | عضله‌های درگیر |
| تمرین | `workout_programs` | هویت پایدار برنامه |
| تمرین | `workout_program_versions` | نسخه تغییرناپذیر پس از انتشار |
| تمرین | `workout_days` | روز یا بخش نسخه |
| تمرین | `workout_day_exercises` | حرکت تجویزی |
| تمرین | `workout_assignments` | تخصیص یک نسخه به یک رابطه |
| عملکرد | `workout_sessions` | جلسه واقعاً انجام‌شده |
| عملکرد | `workout_session_sets` | ست یا تلاش واقعی |
| عملکرد | `personal_record_events` | رویداد شکسته‌شدن رکورد |
| تغذیه | `foods` | هویت ماده غذایی |
| تغذیه | `food_nutrient_revisions` | مبنای تغذیه‌ای تغییرناپذیر |
| تغذیه | `meal_plans` | هویت برنامه غذا |
| تغذیه | `meal_plan_versions` | نسخه برنامه غذا |
| تغذیه | `meal_days` | روز نسخه |
| تغذیه | `meals` | وعده |
| تغذیه | `meal_items` | قلم وعده، وصل به revision |
| تغذیه | `meal_assignments` | تخصیص نسخه غذا به رابطه |
| بدن | `body_metric_definitions` | واژگان معیار و واحد |
| بدن | `body_observations` | یک نوبت ثبت آنالیز |
| بدن | `body_observation_values` | معیارهایی که واقعاً مقدار دارند |
| بازخورد | `feedback_threads` | موضوع بازخورد داخل رابطه |
| بازخورد | `feedback_messages` | پیام مشترک یا یادداشت داخلی |
| اعلان | `notifications` | اعلان درون‌برنامه‌ای |
| AI | `coach_ai_setting_versions` | روش و قواعد نسخه‌دار مربی |
| AI | `coach_knowledge_documents` | سند دانش مربی |
| AI | `coach_knowledge_chunks` | قطعه قابل بازیابی همان مربی |
| AI | `ai_conversations` | گفتگو |
| AI | `ai_messages` | نوبت گفتگو یا نتیجه ابزار |
| AI | `ai_generations` | پیشنهاد ساختاریافته قبل از انتشار |

`coach_profiles` ستون‌های `current_ai_setting_version_id` و `ai_onboarding_completed_at` را هم نگه می‌دارد تا جدول یک‌به‌یک جدا ساخته نشود.

## ۳. هویت

### `users`

حساب شخص. ایمیل و رمز محصول نیستند.

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `phone` | CHAR(11) | نه | | UNIQUE |
| `national_code` | CHAR(10) | نه | | UNIQUE |
| `status` | VARCHAR(32) | نه | `active` | `active` یا `suspended` |
| `suspended_at` | DATETIME | بله | | |
| `last_login_at` | DATETIME | بله | | |
| `created_at` / `updated_at` | DATETIME | نه | | |

حذف فیزیکی کاربر در نسخه اولیه نیست. تعلیق فقط `status` را عوض می‌کند.

### `signup_drafts`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `phone` | CHAR(11) | نه | | ایندکس |
| `phone_verified_at` | DATETIME | بله | | |
| `national_code` | CHAR(10) | بله | | |
| `display_name` | VARCHAR(120) | بله | | |
| `panel` | VARCHAR(32) | بله | | `coach`، `athlete`، `both` |
| `open_phone` | CHAR(11) | بله | | UNIQUE؛ تا قبل از مصرف برابر `phone` |
| `expires_at` | DATETIME | نه | | |
| `consumed_at` | DATETIME | بله | | |
| `created_user_id` | BIGINT UNSIGNED | بله | | FK به `users`، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | | |

با مصرف پیش‌نویس، `open_phone` در همان UPDATE برابر NULL می‌شود.

### `otp_challenges`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `phone` | CHAR(11) | نه | | ایندکس `(phone, purpose, consumed_at)` |
| `purpose` | VARCHAR(32) | نه | | `signup`، `login`، `invitation` |
| `code_hash` | CHAR(64) | نه | | متن کد ذخیره نمی‌شود |
| `expires_at` | DATETIME | نه | | |
| `attempt_count` | TINYINT UNSIGNED | نه | ۰ | |
| `consumed_at` | DATETIME | بله | | |
| `signup_draft_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `athlete_invitation_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `created_at` | DATETIME | نه | | |

پیشنهاد اجرایی: اعتبار ۱۲۰ ثانیه و حداکثر ۵ تلاش غلط. کد `12345` فقط وقتی `APP_ENV=local` است پذیرفته می‌شود و در production وجودش خطای راه‌اندازی است.

### `platform_roles`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `user_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `role` | VARCHAR(32) | نه | | مقدار نسخه اولیه: `platform_admin` |
| `granted_by_user_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `created_at` | DATETIME | نه | | |

UNIQUE `(user_id, role)`.

### `coach_profiles`

مرز مالکیت داده مربی.

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `user_id` | BIGINT UNSIGNED | نه | | UNIQUE، FK، RESTRICT |
| `display_name` | VARCHAR(120) | نه | | |
| `status` | VARCHAR(32) | نه | `active` | `active` یا `archived` |
| `current_ai_setting_version_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `ai_onboarding_completed_at` | DATETIME | بله | | |
| `created_at` / `updated_at` | DATETIME | نه | | |

### `athlete_profiles`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `user_id` | BIGINT UNSIGNED | نه | | UNIQUE، FK، RESTRICT |
| `display_name` | VARCHAR(120) | نه | | |
| `status` | VARCHAR(32) | نه | `active` | `active` یا `archived` |
| `created_at` / `updated_at` | DATETIME | نه | | |

جنسیت و تاریخ تولد خواسته نشده‌اند و ستون ندارند.

## ۴. تجارت

کلید قابلیت‌های نسخه اولیه: `workout_programs`، `nutrition_programs`، `body_analysis`، `ai_coach_assistant`، `ai_athlete_access`، `progress_reports`. بودن کلید در پلن داده است؛ فهرست کلیدهای شناخته‌شده در کد بسته است تا غلط املایی قابلیت جدید نسازد.

### `plans`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `code` | VARCHAR(64) | نه | | UNIQUE |
| `name` | VARCHAR(160) | نه | | |
| `description` | TEXT | بله | | |
| `price_irr` | BIGINT UNSIGNED | نه | | |
| `billing_interval_unit` | VARCHAR(16) | نه | | `day` یا `month` |
| `billing_interval_count` | SMALLINT UNSIGNED | نه | | بزرگ‌تر از صفر |
| `athlete_capacity` | INT UNSIGNED | نه | | |
| `ai_request_quota` | INT UNSIGNED | بله | | اگر قابلیت AI هست، در برنامه اجباری و بزرگ‌تر از صفر |
| `is_sellable` | BOOLEAN | نه | false | |
| `created_at` / `updated_at` | DATETIME | نه | | |

ویرایش این جدول فقط فروش بعدی را عوض می‌کند. سفارش پرداخت‌شده تصویر خودش را دارد. پلن فروخته‌شده حذف نمی‌شود؛ فروشش با `is_sellable` بسته می‌شود.

### `plan_features`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `plan_id` | BIGINT UNSIGNED | نه | FK، CASCADE فقط برای پلن هرگز سفارش‌نشده |
| `feature_key` | VARCHAR(64) | نه | UNIQUE همراه `plan_id` |

### `orders`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `plan_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `status` | VARCHAR(32) | نه | `pending` | `pending`، `paid`، `expired`، `cancelled` |
| `price_irr` | BIGINT UNSIGNED | نه | | تصویر قیمت |
| `billing_interval_unit` | VARCHAR(16) | نه | | تصویر |
| `billing_interval_count` | SMALLINT UNSIGNED | نه | | تصویر |
| `athlete_capacity` | INT UNSIGNED | نه | | تصویر |
| `ai_request_quota` | INT UNSIGNED | بله | | تصویر |
| `open_checkout_coach_id` | BIGINT UNSIGNED | بله | | UNIQUE؛ در وضعیت `pending` برابر مربی |
| `expires_at` | DATETIME | نه | | مهلت پرداخت سفارش باز |
| `paid_at` | DATETIME | بله | | |
| `cancelled_at` | DATETIME | بله | | |
| `created_by_user_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | | |

ایندکس `(coach_profile_id, status)`. پس از `paid` ستون‌های تصویر تغییر نمی‌کنند.

### `order_features`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `order_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `feature_key` | VARCHAR(64) | نه | UNIQUE همراه `order_id` |

### `payment_attempts`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `order_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT، ایندکس |
| `gateway` | VARCHAR(32) | نه | | |
| `amount_irr` | BIGINT UNSIGNED | نه | | باید با سفارش برابر باشد |
| `status` | VARCHAR(32) | نه | `initiated` | `initiated`، `succeeded`، `failed` |
| `idempotency_key` | CHAR(64) | نه | | UNIQUE |
| `gateway_request_id` | VARCHAR(191) | بله | | UNIQUE |
| `gateway_reference` | VARCHAR(191) | بله | | UNIQUE |
| `failure_code` | VARCHAR(64) | بله | | |
| `gateway_payload` | JSON | بله | | بدنه خام؛ منبع حقیقت تجاری نیست |
| `finished_at` | DATETIME | بله | | |
| `created_at` / `updated_at` | DATETIME | نه | | |

`gateway = manual` فقط برای پرداخت ثبت‌شده توسط مدیر پلتفرم است و همان مسیر فعال‌سازی را طی می‌کند.

### `payments`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `order_id` | BIGINT UNSIGNED | نه | UNIQUE، FK، RESTRICT |
| `payment_attempt_id` | BIGINT UNSIGNED | نه | UNIQUE، FK، RESTRICT |
| `amount_irr` | BIGINT UNSIGNED | نه | |
| `gateway` | VARCHAR(32) | نه | |
| `gateway_reference` | VARCHAR(191) | نه | UNIQUE |
| `confirmed_at` | DATETIME | نه | |
| `created_at` | DATETIME | نه | |

هر سفارش حداکثر یک پرداخت تأییدشده دارد. تکرار callback به این UNIQUE برخورد می‌کند و دوره دوم نمی‌سازد.

### `access_periods`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `order_id` | BIGINT UNSIGNED | نه | | UNIQUE، FK، RESTRICT |
| `status` | VARCHAR(32) | نه | | `scheduled`، `active`، `expired`، `revoked` |
| `starts_at` | DATETIME | نه | | |
| `ends_at` | DATETIME | نه | | بزرگ‌تر از شروع |
| `athlete_capacity` | INT UNSIGNED | نه | | |
| `ai_request_quota` | INT UNSIGNED | بله | | |
| `ai_requests_used` | INT UNSIGNED | نه | ۰ | |
| `active_coach_id` | BIGINT UNSIGNED | بله | | UNIQUE؛ فقط در وضعیت `active` پر است |
| `created_at` / `updated_at` | DATETIME | نه | | |

ایندکس `(coach_profile_id, status, ends_at)`.

کنترل ظرفیت و سهمیه روی همین ردیف `lockForUpdate` می‌شود. افزایش `ai_requests_used` و درج `ai_usage_entries` یک تراکنش است. اگر سهمیه کافی نباشد هیچ‌کدام نوشته نمی‌شود.

### `ai_usage_entries`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `access_period_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `coach_profile_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `actor_user_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `athlete_profile_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `ai_generation_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `kind` | VARCHAR(32) | نه | | مانند `coach_turn` یا `athlete_turn` |
| `units` | INT UNSIGNED | نه | ۱ | |
| `provider_prompt_tokens` | INT UNSIGNED | بله | | فقط مشاهده هزینه |
| `provider_completion_tokens` | INT UNSIGNED | بله | | |
| `created_at` | DATETIME | نه | | |

ایندکس `(access_period_id, created_at)`.

## ۵. رابطه

### `athlete_invitations`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `phone` | CHAR(11) | نه | | |
| `status` | VARCHAR(32) | نه | `pending` | `pending`، `accepted`، `expired`، `revoked`، `declined` |
| `pending_key` | VARCHAR(32) | بله | | UNIQUE؛ `{coachProfileId}:{phone}` فقط وقتی `pending` |
| `expires_at` | DATETIME | نه | | پیشنهاد: ۷ روز |
| `accepted_at` | DATETIME | بله | | |
| `revoked_at` | DATETIME | بله | | |
| `accepted_by_user_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `created_by_user_id` | BIGINT UNSIGNED | نه | | FK، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | | |

ایندکس `(phone, status)` و `(coach_profile_id, status)`.

### `coaching_relationships`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `status` | VARCHAR(32) | نه | `active` یا `ended` |
| `started_at` | DATETIME | نه | |
| `ended_at` | DATETIME | بله | |
| `ended_by_user_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `end_reason` | VARCHAR(160) | بله | |
| `created_at` / `updated_at` | DATETIME | نه | |

UNIQUE `(coach_profile_id, athlete_profile_id)`. ایندکس هر دو طرف با `status`.

برابری مالک دو پروفایل در دیتابیس به‌سادگی قابل بیان نیست و در تراکنش پذیرش رد می‌شود.

### `coaching_relationship_events`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coaching_relationship_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `event_type` | VARCHAR(32) | نه | `started`، `ended`، `reactivated` |
| `actor_user_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `happened_at` | DATETIME | نه | |
| `note` | VARCHAR(160) | بله | |

این جدول append-only است.

## ۶. حرکت و برنامه تمرین

### `muscles`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `code` | VARCHAR(64) | نه | UNIQUE |
| `name_fa` | VARCHAR(120) | نه | |
| `name_en` | VARCHAR(120) | بله | |
| `parent_id` | BIGINT UNSIGNED | بله | FK به همین جدول، RESTRICT |

### `body_model_areas`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `code` | VARCHAR(64) | نه | UNIQUE؛ قرارداد فرانت‌اند |
| `name_fa` | VARCHAR(120) | نه | |
| `name_en` | VARCHAR(120) | بله | |

### `muscle_body_area`

PK مرکب `(muscle_id, body_model_area_id)`، هر دو FK و RESTRICT. این نگاشت فقط نمایش است و اندازه‌گیری بدن نیست.

### `exercises`

| ستون | نوع | تهی | پیش‌فرض | قید |
| --- | --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | خودکار | PK |
| `coach_profile_id` | BIGINT UNSIGNED | بله | | NULL یعنی کاتالوگ پلتفرم؛ FK، RESTRICT |
| `name` | VARCHAR(160) | نه | | |
| `instructions` | TEXT | بله | | |
| `tracking_type` | VARCHAR(32) | نه | | `weight_reps`، `reps`، `duration`، `distance` |
| `status` | VARCHAR(32) | نه | `active` | `active` یا `archived` |
| `created_by_user_id` | BIGINT UNSIGNED | بله | | FK، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | | |

ایندکس `(coach_profile_id, status)`. حرکت استفاده‌شده در نسخه منتشرشده حذف نمی‌شود.

### `exercise_media`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `exercise_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `media_type` | VARCHAR(16) | نه | `image` یا `video` |
| `path` | VARCHAR(255) | نه | |
| `sort_order` | SMALLINT UNSIGNED | نه | |
| `created_at` | DATETIME | نه | |

### `exercise_muscles`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `exercise_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `muscle_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `involvement` | VARCHAR(16) | نه | `primary` یا `secondary` |

UNIQUE `(exercise_id, muscle_id)`.

### `workout_programs`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `title` | VARCHAR(160) | نه | |
| `status` | VARCHAR(32) | نه | `draft`، `published`، `archived` |
| `current_version_id` | BIGINT UNSIGNED | بله | FK به نسخه، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | |

### `workout_program_versions`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `workout_program_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `version_number` | INT UNSIGNED | نه | UNIQUE همراه برنامه |
| `state` | VARCHAR(32) | نه | `draft` یا `published` |
| `note` | TEXT | بله | |
| `published_at` | DATETIME | بله | |
| `created_by_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `created_at` | DATETIME | نه | |

نسخه `published` و فرزندانش UPDATE نمی‌شوند. ویرایش یعنی نسخه `draft` بعدی. انتشار، `published_at` را پر می‌کند و نوشتن بعدی را در سرویس ممنوع می‌کند.

### `workout_days`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `workout_program_version_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `position` | SMALLINT UNSIGNED | نه | UNIQUE همراه نسخه |
| `title` | VARCHAR(120) | نه | |

### `workout_day_exercises`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `workout_day_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `exercise_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `position` | SMALLINT UNSIGNED | نه | UNIQUE همراه روز |
| `tracking_type` | VARCHAR(32) | نه | می‌تواند با پیش‌فرض حرکت فرق کند |
| `prescribed_sets` | SMALLINT UNSIGNED | بله | |
| `reps_min` / `reps_max` | SMALLINT UNSIGNED | بله | |
| `target_weight_g` | INT UNSIGNED | بله | |
| `rest_seconds` | SMALLINT UNSIGNED | بله | |
| `duration_seconds` | INT UNSIGNED | بله | |
| `distance_m` | INT UNSIGNED | بله | |
| `coach_note` | VARCHAR(500) | بله | |

برای `weight_reps` وزن هدف اختیاری است. برای `duration` فقط مدت معنی دارد. سرویس ستون بی‌ربط را نمی‌نویسد.

### `workout_assignments`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coaching_relationship_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT؛ باید مالک رابطه باشد |
| `workout_program_version_id` | BIGINT UNSIGNED | نه | FK، RESTRICT؛ فقط نسخه published |
| `assigned_by_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `starts_on` | DATE | نه | |
| `ends_on` | DATE | بله | |
| `status` | VARCHAR(32) | نه | `active` یا `ended` |
| `created_at` / `updated_at` | DATETIME | نه | |

ایندکس `(athlete_profile_id, status)` و `(coaching_relationship_id, status)`.

تخصیص به نسخه وصل است. نسخه جدید به‌خودخود تخصیص قدیمی را جابه‌جا نمی‌کند.

## ۷. عملکرد

### `workout_sessions`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | FK، RESTRICT؛ فقط رابطه فعال در لحظه شروع |
| `workout_assignment_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `workout_day_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `performed_on` | DATE | نه | |
| `started_at` / `completed_at` | DATETIME | بله | |
| `status` | VARCHAR(32) | نه | `in_progress` یا `completed` |
| `perceived_effort` | TINYINT UNSIGNED | بله | ۱ تا ۱۰، اگر ورزشکار بدهد |
| `athlete_note` | VARCHAR(500) | بله | |
| `created_at` / `updated_at` | DATETIME | نه | |

ایندکس `(athlete_profile_id, performed_on)` و `(coaching_relationship_id, performed_on)`.

### `workout_session_sets`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `workout_session_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `sequence_no` | SMALLINT UNSIGNED | نه | UNIQUE همراه جلسه |
| `workout_day_exercise_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `exercise_id` | BIGINT UNSIGNED | نه | FK، RESTRICT؛ کپی شناسه در زمان ثبت |
| `set_index` | SMALLINT UNSIGNED | نه | ترتیب داخل همان حرکت جلسه |
| `reps` | SMALLINT UNSIGNED | بله | |
| `weight_g` | INT UNSIGNED | بله | |
| `duration_seconds` | INT UNSIGNED | بله | |
| `distance_m` | INT UNSIGNED | بله | |
| `is_completed` | BOOLEAN | نه | پیش‌فرض true |
| `created_at` | DATETIME | نه | |

`sequence_no` یکتا بودن را حتی وقتی قلم تجویزی تهی است حفظ می‌کند. ست تکمیل‌نشده رکورد نمی‌سازد.

### `personal_record_events`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `exercise_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `metric` | VARCHAR(32) | نه | `max_weight_g`، `max_duration_s`، `max_distance_m`، `estimated_1rm_g` |
| `value_int` | INT UNSIGNED | نه | |
| `workout_session_set_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `formula_code` | VARCHAR(32) | بله | برای یک‌تکرار بیشینه: `epley_v1` |
| `recorded_at` | DATETIME | نه | |

ایندکس `(athlete_profile_id, exercise_id, metric, recorded_at)`. درج فقط وقتی مقدار از بیشینه قبلی همان ورزشکار و حرکت و معیار بیشتر باشد. رویدادها UPDATE نمی‌شوند.

`epley_v1`: گرم ضربدر `(۱ + تکرار / ۳۰)` با گرد کردن half-up، فقط برای تکرار ۱ تا ۱۰.

## ۸. تغذیه

### `foods`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | بله | NULL یعنی کاتالوگ پلتفرم |
| `name` | VARCHAR(160) | نه | |
| `status` | VARCHAR(32) | نه | `active` یا `archived` |
| `current_revision_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | |

### `food_nutrient_revisions`

تغییرناپذیر. ویرایش غذا ردیف جدید است و `current_revision_id` را جلو می‌برد.

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `food_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `revision_number` | INT UNSIGNED | نه | UNIQUE همراه غذا |
| `basis` | VARCHAR(16) | نه | `per_100g` یا `per_unit` |
| `unit_code` | VARCHAR(16) | نه | `g`، `ml`، `piece` |
| `unit_label` | VARCHAR(40) | نه | برچسب نمایش |
| `grams_per_unit` | INT UNSIGNED | بله | برای تبدیل واحد به گرم، وقتی واحد گرم نیست |
| `energy_kcal` | INT UNSIGNED | نه | مقدار همان مبنا |
| `protein_mg` | INT UNSIGNED | نه | |
| `carbohydrate_mg` | INT UNSIGNED | نه | |
| `fat_mg` | INT UNSIGNED | نه | |
| `created_at` | DATETIME | نه | |

برای `per_100g` واحد `g` است. برای `per_unit` مقدارها متعلق به یک واحد است.

محاسبه قلم: ضریبِ هر ۱۰۰ گرم برابر `quantity_g / 100` و ضریب واحد برابر `quantity_unit_milli / 1000` است. حاصل هر درشت‌مغذی با half-up و حساب صحیح محاسبه می‌شود: `(مقدار × عدد مبنا + مقسوم‌علیه / ۲) / مقسوم‌علیه`.

### `meal_plans` و `meal_plan_versions`

ساختار و قفل انتشار مانند برنامه تمرین است. ستون‌ها: عنوان و وضعیت روی برنامه؛ شماره نسخه، `state`، `published_at` و سازنده روی نسخه. UNIQUE `(meal_plan_id, version_number)`.

### `meal_days`

`meal_plan_version_id`، `position` یکتا در نسخه، `title`.

### `meals`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `meal_day_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `position` | SMALLINT UNSIGNED | نه | UNIQUE همراه روز |
| `meal_type` | VARCHAR(16) | نه | `breakfast`، `lunch`، `dinner`، `snack`، `other` |
| `title` | VARCHAR(120) | بله | |

### `meal_items`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `meal_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `position` | SMALLINT UNSIGNED | نه | UNIQUE همراه وعده |
| `food_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `food_nutrient_revision_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `quantity_g` | INT UNSIGNED | بله | فقط مبنای `per_100g` |
| `quantity_unit_milli` | INT UNSIGNED | بله | ۱۵۰۰ یعنی ۱٫۵ واحد |

دقیقاً یکی از دو ستون مقدار، مطابق `basis` همان revision، پر می‌شود. جمع ذخیره‌شده جداگانه ندارد تا از revision منحرف نشود.

### `meal_assignments`

همان شکل `workout_assignments` با `meal_plan_version_id`.

## ۹. بدن

### `body_metric_definitions`

داده واژگانی، نه جدول گزارش.

| کد | واحد ذخیره‌سازی | مشتق |
| --- | --- | --- |
| `weight_g` | گرم | نه |
| `height_mm` | میلی‌متر | نه |
| `body_fat_bp` | basis point | نه |
| `muscle_mass_g` | گرم | نه |
| `body_water_bp` | basis point | نه |
| `visceral_fat_score` | امتیاز صحیح دستگاه | نه |
| `bmi_centi` | BMI ضربدر ۱۰۰ | می‌تواند measured یا calculated باشد |
| `neck_mm`، `chest_mm`، `waist_mm`، `hip_mm` | میلی‌متر | نه |
| `left_arm_mm`، `right_arm_mm`، `left_thigh_mm`، `right_thigh_mm` | میلی‌متر | نه |

ستون‌ها: `code` به‌عنوان PK، `name_fa`، `unit_code`، `is_derived`. محیط دور قابل گسترش است، ولی هر کد جدید ردیف واژگان است نه ستون جدید مشاهده.

### `body_observations`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `recorded_by_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `source` | VARCHAR(16) | نه | `manual`، `device`، `coach` |
| `visibility` | VARCHAR(32) | نه | `athlete_private` یا `relationship` |
| `measured_at` | DATETIME | نه | زمان اندازه‌گیری، نه زمان درج |
| `note` | VARCHAR(500) | بله | |
| `created_at` | DATETIME | نه | |

ایندکس `(athlete_profile_id, measured_at)`.

اگر `visibility = relationship` باشد شناسه رابطه اجباری است. مربی دیگر این ردیف را نمی‌بیند. مشاهده خصوصی شناسه رابطه ندارد.

### `body_observation_values`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `body_observation_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `metric_code` | VARCHAR(64) | نه | FK به تعریف معیار |
| `value_int` | INT UNSIGNED | نه | بزرگ‌تر از صفر برای وزن و طول |
| `origin` | VARCHAR(16) | نه | `measured` یا `calculated` |
| `formula_code` | VARCHAR(32) | بله | برای BMI محاسبه‌شده `bmi_v1` |

UNIQUE `(body_observation_id, metric_code, origin)` تا BMI دستگاه و BMI محاسبه‌شده هم‌زمان بمانند. معیاری که اندازه گرفته نشده ردیف ندارد.

Before/After دو مشاهده را مقایسه می‌کند و جدول عکس یا نتیجه مقایسه‌ای ندارد.

## ۱۰. بازخورد و اعلان

### `feedback_threads`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coaching_relationship_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `opened_by_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `workout_session_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `workout_day_exercise_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `exercise_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `status` | VARCHAR(16) | نه | `open` یا `resolved` |
| `created_at` / `updated_at` | DATETIME | نه | |

حداکثر یکی از سه شناسه موضوع پر است. اگر هیچ‌کدام پر نباشد موضوع عمومی همان رابطه است. موضوع باید به همان رابطه قابل انتساب باشد. این قاعده در برنامه بررسی می‌شود، چون CHECK چندجدولی در MySQL عملی نیست.

### `feedback_messages`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `feedback_thread_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `author_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `visibility` | VARCHAR(16) | نه | `shared` یا `coach_only` |
| `body` | TEXT | نه | |
| `pain_level` | TINYINT UNSIGNED | بله | ۰ تا ۱۰ |
| `created_at` | DATETIME | نه | |

ورزشکار فقط `shared` می‌نویسد و فقط `shared` می‌خواند. `coach_only` یادداشت داخلی است، نه پیام گفتگو.

### `notifications`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `type` | VARCHAR(64) | نه | `program_assigned`، `record_achieved`، `body_observation_recorded`، `feedback_replied` |
| `title` | VARCHAR(160) | نه | |
| `body` | VARCHAR(500) | نه | |
| `subject_type` | VARCHAR(64) | بله | |
| `subject_id` | BIGINT UNSIGNED | بله | بدون FK به دلیل موضوع‌های متفاوت |
| `read_at` | DATETIME | بله | |
| `created_at` | DATETIME | نه | |

ایندکس `(user_id, read_at, id)`. اعلان push و وب‌سوکت جدول دیگری نمی‌خواهد.

## ۱۱. هوش مصنوعی

### `coach_ai_setting_versions`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `version_number` | INT UNSIGNED | نه | UNIQUE همراه مربی |
| `methodology` | TEXT | نه | روش برنامه‌نویسی مربی |
| `preferences` | TEXT | نه | |
| `constraints` | TEXT | نه | محدودیت‌های کاری |
| `created_at` | DATETIME | نه | |

ردیف منتشرشده تغییر نمی‌کند. گفتگو به شناسه نسخه وصل است. تکمیل اولین نسخه، `ai_onboarding_completed_at` مربی را پر می‌کند.

### `coach_knowledge_documents`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `title` | VARCHAR(160) | نه | |
| `storage_path` | VARCHAR(255) | نه | |
| `sha256` | CHAR(64) | نه | |
| `status` | VARCHAR(32) | نه | `uploaded`، `ready`، `rejected` |
| `created_by_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `created_at` / `updated_at` | DATETIME | نه | |

UNIQUE `(coach_profile_id, sha256)`.

### `coach_knowledge_chunks`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_knowledge_document_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT؛ تکرار عمدی برای فیلتر اجباری |
| `chunk_index` | INT UNSIGNED | نه | UNIQUE همراه سند |
| `body` | TEXT | نه | ایندکس FULLTEXT در MySQL |

ابزار بازیابی اگر `coach_profile_id` ندهد نتیجه خالی برمی‌گرداند. FULLTEXT در SQLite تست نمی‌شود؛ آزمایش آن یا MySQL است یا پشت درگاهی که در تست جایگزین می‌شود.

متن سند داده است و اجازه ابزار یا تغییر مربی نمی‌دهد.

### `ai_conversations`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `athlete_profile_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `actor_user_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `audience` | VARCHAR(16) | نه | `coach` یا `athlete` |
| `coach_ai_setting_version_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `created_at` | DATETIME | نه | |

گفتگوی ورزشکار فقط وقتی قابلیت `ai_athlete_access` روی دوره فعال مربی همان رابطه باشد ساخته می‌شود.

### `ai_messages`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `ai_conversation_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `sequence_no` | INT UNSIGNED | نه | UNIQUE همراه گفتگو |
| `role` | VARCHAR(16) | نه | `user`، `assistant`، `tool` |
| `body` | TEXT | نه | |
| `tool_name` | VARCHAR(64) | بله | |
| `created_at` | DATETIME | نه | |

ابزارهای مجاز نسخه اولیه: `relationship_summary`، `workout_history`، `personal_records`، `body_series`، `shared_feedback`، `coach_method`، `knowledge_search`. هر ابزار شناسه مربی و، در صورت وجود، شناسه رابطه را از زمینه سرور می‌گیرد، نه از متن کاربر.

### `ai_generations`

| ستون | نوع | تهی | قید |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED | نه | PK |
| `coach_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `coaching_relationship_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `athlete_profile_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `ai_conversation_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `coach_ai_setting_version_id` | BIGINT UNSIGNED | نه | FK، RESTRICT |
| `kind` | VARCHAR(32) | نه | `workout_program`، `meal_plan`، `progress_review` |
| `status` | VARCHAR(32) | نه | `draft`، `approved`، `rejected`، `materialized` |
| `proposal` | JSON | نه | پیش‌نویس؛ پس از materialize منبع حقیقت نیست |
| `model_name` | VARCHAR(120) | نه | |
| `reviewed_by_user_id` | BIGINT UNSIGNED | بله | FK، RESTRICT |
| `reviewed_at` | DATETIME | بله | |
| `workout_program_version_id` | BIGINT UNSIGNED | بله | FK نسخه ساخته‌شده |
| `meal_plan_version_id` | BIGINT UNSIGNED | بله | FK نسخه ساخته‌شده |
| `created_at` | DATETIME | نه | |

تأیید مربی، ردیف‌های عادی نسخه برنامه را می‌سازد و شناسه آن‌ها را اینجا ثبت می‌کند. تخصیص به ورزشکار بعد از ساخته شدن نسخه و با همان قواعد دامنه تمرین است.

## ۱۲. مالکیت و دسترسی

| داده | مالک محدوده | چه کسی می‌خواند |
| --- | --- | --- |
| کاربر و پروفایل خودش | شخص | خودش؛ مدیر فقط برای وضعیت حساب |
| پلن و سفارش و پرداخت | پروفایل مربی | همان مربی و مدیر پلتفرم |
| دعوت و رابطه | پروفایل مربی | مربی همان رابطه و ورزشکار همان رابطه |
| حرکت پلتفرم | پلتفرم | هر مربیِ دارای قابلیت تمرین |
| حرکت خصوصی | یک مربی | فقط همان مربی |
| نسخه و تخصیص برنامه | رابطه | دو طرف همان رابطه؛ مربی بعد از پایان فقط خواندن |
| جلسه و ست | ورزشکار، در زمینه رابطه | ورزشکار همیشه؛ مربی فقط اگر شناسه رابطه برای او باشد |
| رکورد | ورزشکار | ورزشکار؛ مربی فقط رویدادهای رابطه خودش |
| مشاهده خصوصی بدن | ورزشکار | فقط ورزشکار |
| مشاهده رابطه‌ای بدن | همان رابطه | ورزشکار و مربی همان رابطه |
| پیام `shared` | رابطه | دو طرف |
| پیام `coach_only` | مربی | فقط مربی |
| دانش و گفتگوی AI | مربی | عامل مجاز همان محدوده؛ هرگز مربی دیگر |
| اعلان | کاربر گیرنده | فقط گیرنده |

مدیر پلتفرم با نقش خود از این مرزها عبور نمی‌کند.

## ۱۳. نگهداری و حذف

| داده | سیاست |
| --- | --- |
| پرداخت، سفارش پرداخت‌شده، دوره | بدون حذف و بدون تغییر مبلغ |
| نسخه published و revision غذا | بدون UPDATE |
| رویداد رابطه و رویداد رکورد | append-only |
| پیش‌نویس ثبت‌نام و OTP مصرف‌شده | قابل پاک‌سازی زمان‌بندی‌شده پس از انقضا |
| برنامه و غذا و حرکت | `archived`، نه حذف اگر ارجاع دارند |
| رابطه و دعوت | تغییر وضعیت |
| کاربر | تعلیق |
| سند دانش | مربی می‌تواند سند خودش را از بازیابی خارج کند؛ پیام‌های قبلی متن خودشان را دارند |
| جلسه، ست، مشاهده، پیام | نگهداری؛ صفحه‌بندی و ایندکس زمانی. پارتیشن برای نسخه اولیه نیست |

## ۱۴. محل اعمال قاعده

در دیتابیس:

- PK و FK.
- UNIQUE شماره، کد ملی، یک پروفایل از هر نوع، یک زوج مربی–ورزشکار، یک پرداخت برای هر سفارش، یک مرجع درگاه، یک سفارش باز برای هر مربی، یک دوره فعال برای هر مربی، یک دعوت باز برای هر مربی و شماره.
- NOT NULL روی مبلغ، وضعیت، زمان خرید و شناسه مالک.
- ایندکس‌های خط زمانی ورزشکار، رابطه، اعلان و مصرف AI.

در تراکنش برنامه:

- رقم کنترلی کد ملی و نرمال‌سازی شماره.
- ساخت اتمیک کاربر و پروفایل.
- برابری مبلغ تأییدشده با سفارش و فعال‌سازی یک‌باره دوره.
- قفل دوره برای ظرفیت و سهمیه.
- ممنوعیت رابطه شخص با خودش.
- تطبیق ورزشکار تخصیص با رابطه.
- قفل نسخه منتشرشده و revision.
- انتخاب ستون معیار متناسب با `tracking_type`.
- محاسبه half-up تغذیه و BMI و Epley.
- ننوشتن صفر به‌جای مقدار تهی.
- فیلتر `coach_only` و فیلتر اجباری `coach_profile_id` در ابزار AI.
- تبدیل پیشنهاد AI به ردیف‌های رابطه فقط پس از تأیید مربی، طبق پیشنهاد سیاست نسخه اولیه.

## ۱۵. جدول‌هایی که عمداً ساخته نمی‌شوند

ثبت مصرف واقعی غذا، عکس بدن، شهریه ورزشکار، تسویه و کمیسیون، workspace، باشگاه و شعبه، گفتگوی بلادرنگ، مدل AI جدا برای هر مربی، جدول جدا برای هر نمودار داشبورد، و ردیف متغیر رکورد جاری.
