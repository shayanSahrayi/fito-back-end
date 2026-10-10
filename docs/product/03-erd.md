# نمودار موجودیت‌ها

نمودارها همان مدل [02-database.md](02-database.md) هستند و برای خوانایی دامنه به دامنه جدا شده‌اند. `users` در چند نمودار تکرار شده تا مرز هر دامنه پیدا باشد. برچسب خط‌ها شناسه انگلیسی رابطه است تا در چاپ راست‌به‌چپ به‌هم نریزد. معنی فارسی در بخش «رابطه‌های اصلی» سند بررسی آمده است.

## هویت و پروفایل

```mermaid
erDiagram
    users ||--o| coach_profiles : coach
    users ||--o| athlete_profiles : athlete
    users ||--o{ platform_roles : admin
    users ||--o{ signup_drafts : consumes
    signup_drafts ||--o{ otp_challenges : signup
    athlete_invitations ||--o{ otp_challenges : invite
    coach_profiles ||--o{ coach_ai_setting_versions : versions
    coach_profiles |o--o| coach_ai_setting_versions : current
```

## پلن و دسترسی

```mermaid
erDiagram
    coach_profiles ||--o{ orders : buys
    plans ||--o{ plan_features : features
    plans ||--o{ orders : origin
    orders ||--o{ order_features : snapshot
    orders ||--o{ payment_attempts : attempts
    orders ||--o| payments : payment
    payment_attempts ||--o| payments : winning
    orders ||--o| access_periods : period
    coach_profiles ||--o{ access_periods : access
    access_periods ||--o{ ai_usage_entries : usage
```

## دعوت و رابطه

```mermaid
erDiagram
    coach_profiles ||--o{ athlete_invitations : invites
    coach_profiles ||--o{ coaching_relationships : coaches
    athlete_profiles ||--o{ coaching_relationships : trains
    athlete_invitations |o--o| coaching_relationships : accepts
    coaching_relationships ||--o{ coaching_relationship_events : events
```

یک زوج مربی و ورزشکار فقط یک ردیف رابطه دارد. چند مربی برای یک ورزشکار چند ردیف با `coach_profile_id` متفاوت است.

## تمرین تجویزی و عملکرد

```mermaid
erDiagram
    muscles ||--o{ exercise_muscles : targets
    exercises ||--o{ exercise_muscles : muscles
    exercises ||--o{ exercise_media : media
    muscles }o--o{ body_model_areas : display
    coach_profiles ||--o{ exercises : private
    coach_profiles ||--o{ workout_programs : owns
    workout_programs ||--o{ workout_program_versions : versions
    workout_program_versions ||--o{ workout_days : days
    workout_days ||--o{ workout_day_exercises : items
    exercises ||--o{ workout_day_exercises : exercise
    workout_program_versions ||--o{ workout_assignments : assigns
    coaching_relationships ||--o{ workout_assignments : relationship
    workout_assignments ||--o{ workout_sessions : context
    coaching_relationships ||--o{ workout_sessions : scope
    athlete_profiles ||--o{ workout_sessions : performs
    workout_sessions ||--o{ workout_session_sets : sets
    workout_day_exercises ||--o{ workout_session_sets : prescribed
    workout_session_sets ||--o{ personal_record_events : record
```

## تغذیه

```mermaid
erDiagram
    coach_profiles ||--o{ foods : private
    foods ||--o{ food_nutrient_revisions : basis
    coach_profiles ||--o{ meal_plans : owns
    meal_plans ||--o{ meal_plan_versions : versions
    meal_plan_versions ||--o{ meal_days : days
    meal_days ||--o{ meals : meals
    meals ||--o{ meal_items : items
    food_nutrient_revisions ||--o{ meal_items : snapshot
    meal_plan_versions ||--o{ meal_assignments : assigns
    coaching_relationships ||--o{ meal_assignments : relationship
```

## بدن، بازخورد و دستیار

```mermaid
erDiagram
    athlete_profiles ||--o{ body_observations : sessions
    coaching_relationships ||--o{ body_observations : shared
    body_observations ||--o{ body_observation_values : values
    body_metric_definitions ||--o{ body_observation_values : unit
    coaching_relationships ||--o{ feedback_threads : subject
    feedback_threads ||--o{ feedback_messages : messages
    coach_profiles ||--o{ coach_knowledge_documents : knowledge
    coach_knowledge_documents ||--o{ coach_knowledge_chunks : chunks
    coach_profiles ||--o{ ai_conversations : scope
    coaching_relationships ||--o{ ai_conversations : context
    ai_conversations ||--o{ ai_messages : turns
    ai_conversations ||--o{ ai_generations : drafts
    ai_generations |o--o| workout_program_versions : materialize
    ai_generations |o--o| meal_plan_versions : materialize
    access_periods ||--o{ ai_usage_entries : quota
```
