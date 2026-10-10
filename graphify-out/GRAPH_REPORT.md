# Graph Report - Back-end  (2026-10-10)

## Corpus Check
- Corpus is ~39,504 words - fits in a single context window. You may not need a graph.

## Summary
- 427 nodes · 901 edges · 37 communities (31 shown, 6 thin omitted)
- Extraction: 87% EXTRACTED · 13% INFERRED · 0% AMBIGUOUS · INFERRED: 117 edges (avg confidence: 0.89)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Coaching Relationship Rules
- Composer Package Metadata
- Plans Orders and Access
- Composer Lifecycle Scripts
- Product Docs and Tables
- Laravel User and Config
- Coach AI Workspace
- NPM Dev Dependencies
- Exercise Body and Admin
- Nutrition Programs
- Identity and Login
- Design Review Concepts
- Users Cache Jobs Migrations
- Invitation Becomes Relationship
- Session Records and Catalog
- Published Program Versions
- Plan Payment and Capacity
- App Service Provider
- HTTP Bootstrap
- Feature Example Test
- Logging Config
- Coach Profile Boundary
- Design Review Scope
- Unit Example Test
- Artisan Console Routes
- Base Controller
- Web Routes
- Robots Crawl Policy

## God Nodes (most connected - your core abstractions)
1. `users` - 32 edges
2. `coaching_relationships` - 27 edges
3. `F10 Coach Assistant` - 27 edges
4. `F11 End-to-End Acceptance` - 26 edges
5. `coach_profiles` - 23 edges
6. `F03 Invitation and Relationship` - 22 edges
7. `F01 Identity Profile and Login` - 21 edges
8. `F02 Plans Orders Payments and Periods` - 18 edges
9. `F07 Nutrition` - 18 edges
10. `Coaching Relationship` - 17 edges

## Surprising Connections (you probably didn't know these)
- `Preserved Stack` --references--> `Eloquent ORM`  [INFERRED]
  docs/product/05-current-state-and-decisions.md → README.md
- `Preserved Stack` --references--> `Schema Migrations`  [INFERRED]
  docs/product/05-current-state-and-decisions.md → README.md
- `Invite Athlete Without an Account` --conceptually_related_to--> `Capacity Lock at Acceptance`  [AMBIGUOUS]
  docs/product/04-scenarios.md → docs/product/01-product-and-domains.md
- `Derived Dashboard` --conceptually_related_to--> `body_observations`  [INFERRED]
  docs/product/01-product-and-domains.md → docs/product/02-database.md
- `D-02 Rial or Toman` --conceptually_related_to--> `Storage and API Unit Conventions`  [INFERRED]
  docs/product/05-current-state-and-decisions.md → docs/product/02-database.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Athlete Invitation Acceptance Transaction** — docs_product_02_database_athlete_invitations, docs_product_02_database_users, docs_product_02_database_athlete_profiles, docs_product_02_database_coaching_relationships, docs_product_02_database_coaching_relationship_events, docs_product_02_database_otp_challenges, docs_product_02_database_access_periods [EXTRACTED 1.00]
- **Paid Order Opens One Access Period** — docs_product_02_database_plans, docs_product_02_database_orders, docs_product_02_database_order_features, docs_product_02_database_payment_attempts, docs_product_02_database_payments, docs_product_02_database_access_periods [EXTRACTED 1.00]
- **Post-Contract Visibility Rule** — docs_product_01_product_and_domains_post_contract_access, docs_product_02_database_coaching_relationships, docs_product_02_database_body_photos, docs_product_02_database_feedback_messages, docs_product_02_database_workout_assignments, docs_product_02_database_body_observations [EXTRACTED 1.00]
- **One coach-athlete pair is one relationship row** — docs_product_fito_design_review_coach_profile, docs_product_fito_design_review_athlete_profile, docs_product_fito_design_review_coaching_relationship [EXTRACTED 1.00]
- **Equal gateway confirmation creates one access period** — docs_product_fito_design_review_order, docs_product_fito_design_review_payment, docs_product_fito_design_review_access_period [EXTRACTED 1.00]
- **OTP acceptance of an invitation creates the active relationship** — docs_product_fito_design_review_athlete_invitation, docs_product_fito_design_review_otp, docs_product_fito_design_review_coaching_relationship [EXTRACTED 1.00]

## Communities (37 total, 6 thin omitted)

### Community 0 - "Coaching Relationship Rules"
Cohesion: 0.11
Nodes (56): Athlete Invitation, Body Photo Access, Coach-Only Notes, Coaching Relationship, Epley v1 Estimated One-Rep Max, Feedback, Free Workout Session, No Self Coaching (+48 more)

### Community 1 - "Composer Package Metadata"
Cohesion: 0.05
Nodes (40): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+32 more)

### Community 2 - "Plans Orders and Access"
Cohesion: 0.17
Nodes (28): Capacity Lock at Acceptance, Immutable Financial and Version History, Pay Later Tour, Plans Orders and Access, Rial Integer Money, access_periods, Feature ai_coach_assistant, Feature body_analysis (+20 more)

### Community 3 - "Composer Lifecycle Scripts"
Cohesion: 0.08
Nodes (26): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+18 more)

### Community 4 - "Product Docs and Tables"
Cohesion: 0.10
Nodes (26): Product and Domains, Flutter Mobile Clients, cache, Database Design, jobs, MySQL Target Engine, Nullable Conditional Unique Key, In-Memory SQLite Tests (+18 more)

### Community 5 - "Laravel User and Config"
Cohesion: 0.11
Nodes (13): User, UserFactory, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Seeder, Illuminate\Foundation\Auth\User (+5 more)

### Community 6 - "Coach AI Workspace"
Cohesion: 0.23
Nodes (24): Coach Approves AI Programs, AI Tool and Data Limits, AI Workspace Is Memory Only, Coach Profile Data Boundary, Shared Model Per-Coach Memory, ai_conversations, ai_generations, ai_messages (+16 more)

### Community 7 - "NPM Dev Dependencies"
Cohesion: 0.10
Nodes (19): axios, concurrently, laravel-vite-plugin, devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss (+11 more)

### Community 8 - "Exercise Body and Admin"
Cohesion: 0.20
Nodes (19): BMI v1, Body and Progress, Muscle Area and Metric Split, Derived Dashboard, Exercise Catalog and Prescribed Program, Exercise Bank Request, Platform Admin, Private Coach Exercise (+11 more)

### Community 9 - "Nutrition Programs"
Cohesion: 0.22
Nodes (18): Actual Food Intake, Immutable Food Revision, Nutrition Programs, Platform Fee Only, food_nutrient_revisions, foods, meal_assignments, meal_days (+10 more)

### Community 10 - "Identity and Login"
Cohesion: 0.23
Nodes (18): Athlete, Atomic Account and Profile, Coach, Development OTP 12345, Fito Coaching SaaS, Identity and Login, National Code, Phone OTP (+10 more)

### Community 11 - "Design Review Concepts"
Cohesion: 0.14
Nodes (18): AI Assistant, Athlete, Body Observation, Body Photo, Coach Approval Before Publish, Coach-only Note, Coaching Relationship, Empty Value Is Not Zero (+10 more)

### Community 12 - "Users Cache Jobs Migrations"
Cohesion: 0.23
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 13 - "Invitation Becomes Relationship"
Cohesion: 0.30
Nodes (12): Acceptance Activates Relationship, Athlete Invitation, Athlete Profile, Current Laravel Skeleton, F01 Identity and Login, Invitation Without an Account, National ID, OTP Challenge (+4 more)

### Community 14 - "Session Records and Catalog"
Cohesion: 0.20
Nodes (11): Body Measurement, Body Model Area, Exercise Catalog, F06 Session and Records, Target Muscle, Personal Record, Platform Admin, Private Exercise (+3 more)

### Community 15 - "Published Program Versions"
Cohesion: 0.24
Nodes (10): F03 Coaching Relationship, F04 Exercise Catalog, F05 Workout Program, F07 Meal Plan, Food Nutrient Revision, Immutable Published History, Meal Plan, Published Program Version (+2 more)

### Community 16 - "Plan Payment and Capacity"
Cohesion: 0.31
Nodes (9): Access Period, Athlete Capacity, Expiry Does Not Auto-Drop Athletes, F02 Plan and Payment, Order, Pay Later Tour Lock, Payment, Subscription Plan (+1 more)

### Community 18 - "HTTP Bootstrap"
Cohesion: 0.33
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request

### Community 19 - "Feature Example Test"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

### Community 20 - "Logging Config"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 21 - "Coach Profile Boundary"
Cohesion: 0.50
Nodes (5): Coach, Coach Profile, Coach AI Workspace, Coach Profile Data Boundary, Shared Model, Private Memory

### Community 22 - "Design Review Scope"
Cohesion: 0.60
Nodes (5): Design Status Labels, Fito Backend Design Review, Fito, Version-One Exclusions, Fito Workflow Brief

## Ambiguous Edges - Review These
- `Capacity Lock at Acceptance` → `Invite Athlete Without an Account`  [AMBIGUOUS]
  docs/product/04-scenarios.md · relation: conceptually_related_to
- `Invitation Without an Account` → `Coach-Provisioned Athlete Account`  [AMBIGUOUS]
  docs/product/Fito-design-review.pdf · relation: conceptually_related_to

## Knowledge Gaps
- **68 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+63 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **6 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Capacity Lock at Acceptance` and `Invite Athlete Without an Account`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Invitation Without an Account` and `Coach-Provisioned Athlete Account`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `users` connect `Coach AI Workspace` to `Coaching Relationship Rules`, `Plans Orders and Access`, `Product Docs and Tables`, `Exercise Body and Admin`, `Nutrition Programs`, `Identity and Login`?**
  _High betweenness centrality (0.028) - this node is a cross-community bridge._
- **Why does `F01 Identity Profile and Login` connect `Identity and Login` to `Coaching Relationship Rules`, `Plans Orders and Access`, `Coach AI Workspace`, `Exercise Body and Admin`, `Nutrition Programs`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Why does `F10 Coach Assistant` connect `Coach AI Workspace` to `Coaching Relationship Rules`, `Exercise Body and Admin`, `Plans Orders and Access`, `Nutrition Programs`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Are the 3 inferred relationships involving `users` (e.g. with `meal_assignments` and `meal_plan_versions`) actually correct?**
  _`users` has 3 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `coaching_relationships` (e.g. with `D-03 Access After the Relationship Ends` and `D-04 Expiry and Excess Capacity`) actually correct?**
  _`coaching_relationships` has 2 INFERRED edges - model-reasoned connections that need verification._