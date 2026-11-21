# HRIS Master Setup — Module & Process Documentation

Complete reference for the **4 HRIS Master Setup modules** (34 configurable setup entities) implemented across `celestia-backend` and `celestia-frontend`, following [BACKEND_CODING_GUIDELINES.md](../../codex/BACKEND_CODING_GUIDELINES.md) and [filter-search.md](filter-search.md).

---

## 1. Module Coverage & Hybrid Architecture

Rather than creating 34 separate flat tables or forcing deep hierarchies into one generic table, the setup system uses a **hybrid schema**:

1. **Dedicated relational tables (9 tables)** for hierarchical and workflow-driven structures:
   - **01. Organizational Setup (6 tables):** `offices`, `departments`, `divisions`, `sections`, `positions`, `reports_items`
   - **04. Administrative — Clearance Setup (3 tables):** `clearance_types`, `clearance_requirements`, `clearance_signatories`
2. **Unified reference lookup table (`setup_reference_items`)** for all **25** flat or parent-linked option catalogs across Employee Information (`12`), Employment (`8`), and Administrative (`5`) setups, with optional `parent_id`, extensible `metadata` JSON, `is_default`, `sort_order`, and `is_active`.

---

## 2. Database Design

Migration: `database/migrations/2026_11_20_000001_create_hris_setup_tables.php`

### 2.1 Organizational Setup Tables (`01`)

```text
offices (Office / College)
  id                bigIncrements (PK)
  code              varchar(30) unique
  name              varchar(150)
  short_name        varchar(50) nullable
  type              varchar(30) default('office')   // 'office' | 'college' | 'campus' | 'branch'
  parent_id         foreignId nullable -> offices.id (nullOnDelete)
  head_employee_id  varchar(30) nullable -> employees.employee_id (nullOnDelete)
  description       varchar(255) nullable
  sort_order        unsignedInteger default(0)
  is_active         boolean default(true)
  timestamps

departments
  id                bigIncrements (PK)
  code              varchar(30) unique
  name              varchar(150)
  short_name        varchar(50) nullable
  office_id         foreignId nullable -> offices.id (nullOnDelete)
  head_employee_id  varchar(30) nullable -> employees.employee_id (nullOnDelete)
  description       varchar(255) nullable
  sort_order        unsignedInteger default(0)
  is_active         boolean default(true)
  timestamps

divisions
  id                bigIncrements (PK)
  code              varchar(30) unique
  name              varchar(150)
  short_name        varchar(50) nullable
  department_id     foreignId nullable -> departments.id (nullOnDelete)
  head_employee_id  varchar(30) nullable -> employees.employee_id (nullOnDelete)
  description       varchar(255) nullable
  sort_order        unsignedInteger default(0)
  is_active         boolean default(true)
  timestamps

sections (Section / Unit)
  id                bigIncrements (PK)
  code              varchar(30) unique
  name              varchar(150)
  short_name        varchar(50) nullable
  division_id       foreignId nullable -> divisions.id (nullOnDelete)
  department_id     foreignId nullable -> departments.id (nullOnDelete)
  head_employee_id  varchar(30) nullable -> employees.employee_id (nullOnDelete)
  description       varchar(255) nullable
  sort_order        unsignedInteger default(0)
  is_active         boolean default(true)
  timestamps

positions
  id                      bigIncrements (PK)
  code                    varchar(30) unique
  title                   varchar(150)
  department_id           foreignId nullable -> departments.id (nullOnDelete)
  division_id             foreignId nullable -> divisions.id (nullOnDelete)
  section_id              foreignId nullable -> sections.id (nullOnDelete)
  reports_to_position_id  foreignId nullable -> positions.id (nullOnDelete)
  salary_grade            varchar(30) nullable
  job_level               varchar(50) nullable
  description             varchar(255) nullable
  sort_order              unsignedInteger default(0)
  is_active               boolean default(true)
  timestamps

reports_items
  id                      bigIncrements (PK)
  code                    varchar(50) unique
  name                    varchar(150)
  office_id               foreignId nullable -> offices.id (nullOnDelete)
  department_id           foreignId nullable -> departments.id (nullOnDelete)
  division_id             foreignId nullable -> divisions.id (nullOnDelete)
  section_id              foreignId nullable -> sections.id (nullOnDelete)
  position_id             foreignId nullable -> positions.id (nullOnDelete)
  reports_to_position_id  foreignId nullable -> positions.id (nullOnDelete)
  reports_to_employee_id  varchar(30) nullable -> employees.employee_id (nullOnDelete)
  description             varchar(255) nullable
  sort_order              unsignedInteger default(0)
  is_active               boolean default(true)
  timestamps
```

### 2.2 Unified Reference Table (`02`, `03`, `04`)

```text
setup_reference_items
  id          bigIncrements (PK)
  group       varchar(40)       // 'employee_info' | 'employment' | 'administrative'
  category    varchar(60)       // canonical category key from SetupReferenceItem::CATEGORY_CATALOG
  code        varchar(50)
  name        varchar(150)
  description varchar(255) nullable
  parent_id   foreignId nullable -> setup_reference_items.id (nullOnDelete)
  metadata    json nullable
  sort_order  unsignedInteger default(0)
  is_default  boolean default(false)
  is_active   boolean default(true)
  timestamps

  UNIQUE (category, code)
  INDEX  (group, category, is_active)
```

Canonical 25-category registry (`SetupReferenceItem::CATEGORY_CATALOG`):

| Group | Menu Code | Categories | Parent Linking |
| --- | --- | --- | --- |
| `employee_info` (`02`) | `employee_info_setup` | `gender`, `civil_status`, `blood_type`, `country`, `nationality`, `citizenship`, `religion`, `educational_attainment`, `degree_course`, `relationship_type`, `language`, `disability_type` (12) | `degree_course` -> `educational_attainment` |
| `employment` (`03`) | `employment_setup` | `employment_type`, `employment_status`, `employee_category`, `appointment_type`, `employment_classification`, `work_arrangement`, `separation_type`, `separation_reason` (8) | `separation_reason` -> `separation_type` |
| `administrative` (`04`) | `administrative_setup` | `document_type`, `disciplinary_action_type`, `training_category`, `award_recognition_type`, `employee_requirement_type` (5) | None |

### 2.3 Clearance Relational Tables (`04`)

```text
clearance_types
  id           bigIncrements (PK)
  code         varchar(30) unique
  name         varchar(150)
  description  varchar(255) nullable
  applies_to   varchar(50) default('separation')   // 'separation' | 'transfer' | 'leave' | 'onboarding' | 'annual' | 'other'
  sort_order   unsignedInteger default(0)
  is_active    boolean default(true)
  timestamps

clearance_requirements
  id                   bigIncrements (PK)
  clearance_type_id    foreignId -> clearance_types.id (cascadeOnDelete)
  code                 varchar(30)
  name                 varchar(150)
  description          varchar(255) nullable
  office_id            foreignId nullable -> offices.id (nullOnDelete)
  department_id        foreignId nullable -> departments.id (nullOnDelete)
  is_mandatory         boolean default(true)
  requires_attachment  boolean default(false)
  sort_order           unsignedInteger default(0)
  is_active            boolean default(true)
  timestamps

  UNIQUE (clearance_type_id, code)

clearance_signatories
  id                        bigIncrements (PK)
  clearance_type_id         foreignId -> clearance_types.id (cascadeOnDelete)
  clearance_requirement_id  foreignId nullable -> clearance_requirements.id (cascadeOnDelete)
  office_id                 foreignId nullable -> offices.id (nullOnDelete)
  department_id             foreignId nullable -> departments.id (nullOnDelete)
  position_id               foreignId nullable -> positions.id (nullOnDelete)
  employee_id               varchar(30) nullable -> employees.employee_id (nullOnDelete)
  signatory_label           varchar(150)
  sign_order                unsignedSmallInteger default(1)
  is_required               boolean default(true)
  is_active                 boolean default(true)
  timestamps
```

---

## 3. Business Rules & Integrity Guards

All writes execute inside `DB::transaction()` with `lockForUpdate()` where invariants require protection, and report violations via `ValidationException::withMessages(...)` (`422`):

### Organizational Setup (`OrganizationalSetupService`)
- **Code normalization:** `code` is trimmed and uppercased in every FormRequest (`prepareForValidation()`).
- **Circular hierarchy prevention:**
  - `Office::parent_id` cannot reference itself or any descendant office.
  - `Position::reports_to_position_id` cannot reference itself or any subordinate position.
- **Section parent sync:** When a `Section` specifies a `division_id`, its `department_id` is automatically synced from the division if omitted; if both are provided and disagree, validation fails (`422`).
- **Reports Item self-report guard:** `position_id` and `reports_to_position_id` cannot point to the same position.
- **Safe deletion:** Deleting an Office, Department, Division, Section, or Position is blocked (`422`) if active child records or reporting items still reference it.

### Reference Items (`SetupReferenceService`)
- **Strict group boundary:** Group routes inject `setup_group` via route defaults (`employee_info`, `employment`, `administrative`). Creating or modifying a category outside the route's group is rejected (`422`).
- **Parent category validation:** `parent_id` is only allowed on categories that declare a `parent_category` (`degree_course`, `separation_reason`) and must reference an item in that exact parent category (never itself).
- **Single default per category:** Setting `is_default = true` requires `is_active = true` and automatically demotes the previous default within the same category.
- **Safe deletion:** Items referenced by child reference items (`children()->exists()`) cannot be deleted.

### Clearance Setup (`ClearanceSetupService`)
- **Signatory-requirement consistency:** When `clearance_requirement_id` is provided on a signatory, it must belong to the same `clearance_type_id`.
- **Requirement move guard:** A requirement cannot be moved to a different `clearance_type_id` while signatories are linked to it.
- **Safe deletion:** A `ClearanceType` cannot be deleted while requirements or signatories exist; a `ClearanceRequirement` cannot be deleted while signatories are linked to it.

---

## 4. Sidebar Menus & Permissions

Added in `database/seeders/MenuSeeder.php` and `database/seeders/AccessSeeder.php` (also invoked idempotently in the migration so existing databases receive the new menus on `php artisan migrate`):

| Menu Code | Title | Path | Parent | Default Grants |
| --- | --- | --- | --- | --- |
| `setup` | Setup | `null` | `null` | Parent group (shown when any child is viewable) |
| `organizational_setup` | Organizational Setup | `/setup/organizational` | `setup` | `admin`: full CRUD |
| `employee_info_setup` | Employee Information Setup | `/setup/employee-information` | `setup` | `admin`: full CRUD |
| `employment_setup` | Employment Setup | `/setup/employment` | `setup` | `admin`: full CRUD |
| `administrative_setup` | Administrative Setup | `/setup/administrative` | `setup` | `admin`: full CRUD |

Regular employees do **not** have access to setup management endpoints, but any authenticated `auth:sanctum` user can read active dropdown options via `GET /api/setup/options`.

---

## 5. API Endpoints (`routes/api.php`)

All endpoints require `auth:sanctum` and return the standard `['message' => ..., 'data' => ...]` JSON envelope (`201` on create, `200` on read/update/delete, `404` on missing record, `422` on validation/integrity error).

### Shared Dropdown Helper (`auth:sanctum`)
- `GET /api/setup/options?categories=gender,civil_status&group=employee_info` — active reference options grouped by category.

### 01. Organizational Setup (`can:organizational_setup,<action>`)
- `GET /api/setup/organizational/tree?include_inactive=1` — nested Offices -> Departments -> Divisions -> Sections tree.
- Full CRUD (`GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`) on:
  - `/api/setup/offices`
  - `/api/setup/departments`
  - `/api/setup/divisions`
  - `/api/setup/sections`
  - `/api/setup/positions`
  - `/api/setup/reports-items`

### 02. Employee Information Setup (`can:employee_info_setup,<action>`)
- `GET /api/setup/employee-information/categories` — all 12 categories with `total_count` and `active_count`.
- Full CRUD on `/api/setup/employee-information/items` (supports `?category=...&search=...&parent_id=...&is_active=1`).

### 03. Employment Setup (`can:employment_setup,<action>`)
- `GET /api/setup/employment/categories` — all 8 categories with counts.
- Full CRUD on `/api/setup/employment/items`.

### 04. Administrative Setup (`can:administrative_setup,<action>`)
- `GET /api/setup/administrative/categories` — all 5 reference categories with counts.
- Full CRUD on `/api/setup/administrative/items`.
- Full CRUD on `/api/setup/clearance-types`, `/api/setup/clearance-requirements`, `/api/setup/clearance-signatories`.

---

## 6. Backend & Frontend File Map

### Backend (`celestia-backend/`)
| Layer | Location |
| --- | --- |
| Migration | `database/migrations/2026_11_20_000001_create_hris_setup_tables.php` |
| Models | `app/Models/Setup/{Office,Department,Division,Section,Position,ReportsItem,SetupReferenceItem,ClearanceType,ClearanceRequirement,ClearanceSignatory}.php` |
| Query Traits | `app/Queries/Setup/*Query.php` (10 traits implementing `scopeFilterSearch`) |
| FormRequests | `app/Http/Requests/Setup/*Request.php` (10 method-dispatched request classes) |
| Services | `app/Services/Setup/{OrganizationalSetupService,SetupReferenceService,ClearanceSetupService}.php` |
| Controllers | `app/Http/Controllers/Setup/{OrganizationalSetupController,SetupReferenceController,ClearanceSetupController}.php` |
| Seeders | `database/seeders/Setup/{OrganizationalSetupSeeder,SetupReferenceItemSeeder,ClearanceSetupSeeder}.php` (wired in `DatabaseSeeder.php`) |
| Feature Tests | `tests/Feature/Setup/{OrganizationalSetupTest,SetupReferenceTest,ClearanceSetupTest}.php` |

### Frontend (`celestia-frontend/`)
| Layer | Location |
| --- | --- |
| Types & API | `src/types/setup.ts`, `src/api/setup.ts` |
| Shared Setup UI | `src/features/setup/components/{setup-ui.tsx,reference-item-modal.tsx}`, `src/features/setup/setup-utils.ts`, `src/features/setup/reference-setup-workspace.tsx` |
| Organizational UI | `src/features/setup/organizational/{organizational-setup-page.tsx,org-tables.tsx,org-hierarchy-tree.tsx,org-unit-modals.tsx,org-role-modals.tsx}` |
| Employee Info & Employment UI | `src/features/setup/{employee-info-setup-page.tsx,employment-setup-page.tsx}` |
| Administrative UI | `src/features/setup/administrative/{administrative-setup-page.tsx,clearance-tables.tsx,clearance-setup-modals.tsx}` |
| Routes | `src/routes/setup.{organizational,employee-information,employment,administrative}.tsx` |

> **HTTP Note:** `src/lib/http.ts` normalizes relative API paths to root-relative `/api/...` via `resolveUrl()` so nested `/setup/*` routes never send relative requests to `/setup/api/...`.

---

## 7. Migration, Seeding & Verification

Run migrations and seed default HRIS setup records (all seeders are idempotent via `firstOrCreate`):

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan route:clear
docker compose exec app php artisan db:seed --class="Database\\Seeders\\Setup\\OrganizationalSetupSeeder" --force
docker compose exec app php artisan db:seed --class="Database\\Seeders\\Setup\\SetupReferenceItemSeeder" --force
docker compose exec app php artisan db:seed --class="Database\\Seeders\\Setup\\ClearanceSetupSeeder" --force
```

Run backend feature tests:

```bash
php vendor/bin/phpunit tests/Feature/Setup tests/Feature/MenuAccessTest.php
```
