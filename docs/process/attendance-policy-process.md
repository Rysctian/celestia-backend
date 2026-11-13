# Attendance Policy — Module & Backend Process Plan

Industry-standard DTR timekeeping configuration replacing the academic *"Subject Time Configuration"* minute-bracket concept. Designed around Celestia's existing guidelines in [BACKEND_CODING_GUIDELINES.md](../../codex/BACKEND_CODING_GUIDELINES.md), [HRIS_ATTENDANCE_SCHEMA_AND_PROCESS.md](HRIS_ATTENDANCE_SCHEMA_AND_PROCESS.md), and [filter-search.md](filter-search.md).

---

## 1. Why Attendance Policies (Option A)

Instead of year-scoped `sequence` + `minute range` lookup rows:

1. **Shift-agnostic & reusable**: Policies define timekeeping behavior cleanly once (e.g., *Standard Office*, *Strict Operations*, *Flexible Grace*) and attach to `schedules` via `attendance_policy_id` with a company-wide `is_default` fallback.
2. **All durations in integer seconds**: Matches Core Rule #5 of [HRIS_ATTENDANCE_SCHEMA_AND_PROCESS.md](HRIS_ATTENDANCE_SCHEMA_AND_PROCESS.md) (`tardy_seconds`, `undertime_seconds`, `break_seconds`). UI forms can display and edit inputs cleanly as minutes or `HH:MM` while persisting exact integer seconds.
3. **Compatible with slot overrides**: Per-slot columns already on `schedule_details` (`tardy_start`, `undertime_start`, `early_dismissal_start`) continue to work as explicit slot overrides when present.

---

## 2. Database Design

### Table 1: `attendance_policies`

Migration: `database/migrations/2026_11_12_000001_create_attendance_policies_table.php`

```text
attendance_policies
  id                            bigIncrements (PK)
  name                          varchar(100) unique
  description                   varchar(255) nullable
  tardy_grace_seconds           unsignedInteger default(0)
  tardy_count_from              varchar(30) default('schedule_start')   // 'schedule_start' | 'after_grace'
  absent_after_late_seconds     unsignedInteger nullable                // null = never auto-absent from lateness
  undertime_grace_seconds       unsignedInteger default(0)
  early_in_window_seconds       unsignedInteger default(14400)            // 4 hours
  late_out_window_seconds       unsignedInteger default(14400)            // 4 hours
  duplicate_tap_window_seconds  unsignedInteger default(120)              // 2 minutes
  is_default                    boolean default(false)
  is_active                     boolean default(true)
  created_at                    timestamp
  updated_at                    timestamp
```

### Table Update: `schedules`

Migration: `database/migrations/2026_11_12_000002_add_attendance_policy_id_to_schedules_table.php`

- Adds `attendance_policy_id` (`foreignId('attendance_policy_id')->nullable()->after('timezone')->constrained('attendance_policies')->nullOnDelete()`).
- Leaves `schedule_details` intact; explicit `tardy_start` / `undertime_start` columns act as slot overrides.

---

## 3. Rule Definitions

| Field | Example | Behavior |
| --- | --- | --- |
| `tardy_grace_seconds` | `900` (15m) | Tap `<= sched_start + 900s` is on time (`tardy_seconds = 0`). |
| `tardy_count_from` | `'schedule_start'` / `'after_grace'` | `'schedule_start'` counts lateness from `sched_start` once grace is exceeded; `'after_grace'` counts only seconds beyond `sched_start + tardy_grace_seconds`. |
| `absent_after_late_seconds` | `7200` (2h) or `null` | When set and `time_in > sched_start + absent_after_late_seconds`, the slot is marked `absent = true` (`rendered_seconds = 0`). |
| `undertime_grace_seconds` | `300` (5m) | Tap out `>= sched_end - 300s` has `0` undertime. Earlier taps record `sched_end - time_out` undertime seconds. |
| `early_in_window_seconds` | `14400` (4h) | Earliest window before `sched_start` where raw biometric taps match as a candidate `IN` for the block. |
| `late_out_window_seconds` | `14400` (4h) | Latest window after `sched_end` where raw biometric taps match as a candidate `OUT` for the block. |
| `duplicate_tap_window_seconds` | `120` (2m) | Debounce window for classifying rapid repeat biometric taps as `duplicate`. |

---

## 4. Backend Files & Flow

`routes/api.php` -> `AttendancePolicyRequest` / `AttendancePolicyController` -> `AttendancePolicyService` -> `AttendancePolicy` & `AttendancePolicyQuery`.

| Layer | File | Responsibility |
| --- | --- | --- |
| Migrations | `2026_11_12_000001_create_attendance_policies_table.php`, `2026_11_12_000002_add_attendance_policy_id_to_schedules_table.php` | Schema + schedule FK link. |
| Model | `app/Models/AttendancePolicy.php` | Fillables, casts, `schedules()` `hasMany`, `AttendancePolicyQuery` trait. |
| Query Trait | `app/Queries/AttendancePolicyQuery.php` | `scopeFilterSearch` (`search`, `tardy_count_from`, `is_default`, `is_active`, `date_from`/`date_to`, `sort_by`, `sort_order`, `limit`, `offset`). |
| FormRequest | `app/Http/Requests/AttendancePolicyRequest.php` | Method-dispatched `storeRules()` / `updateRules()`, bounds, `Rule::in(['schedule_start', 'after_grace'])`, and `after()` cross-field checks. |
| Controller | `app/Http/Controllers/Attendance/AttendancePolicyController.php` | Thin `index`, `find`, `store`, `update`, `destroy` actions returning `message`/`data`. |
| Service | `app/Services/Attendance/AttendancePolicyService.php` | Transactional writes with `lockForUpdate()`, single-default enforcement, delete guards, and `resolveForSchedule(?Schedule $schedule)`. |
| Schedule Updates | `Schedule.php`, `ScheduleRequest.php`, `ScheduleService.php` | Support optional `attendance_policy_id` and eager-load `attendancePolicy`. |
| Menu & Access | `database/seeders/MenuSeeder.php`, `database/seeders/AccessSeeder.php` | Add `attendance_policy` (`Attendance Policy`, `/attendance-policies`) under `administration` with admin grants. |
| Factory & Seeder | `database/factories/AttendancePolicyFactory.php`, `database/seeders/AttendancePolicySeeder.php` | Seed a default *Standard Office Policy* cleanly (idempotent on rerun). |
| Feature Tests | `tests/Feature/AttendancePolicyTest.php` | Permissions, CRUD, single-default invariants, schedule link, delete guards, list filtering/sorting, and DTR calculation behavior. |

---

## 5. Routes & Permissions

Protected by `auth:sanctum` and `can:attendance_policy,<action>`:

| Method | Endpoint | Permission | Status |
| --- | --- | --- | --- |
| `GET` | `/api/attendance-policies` | `attendance_policy,view` | `200` |
| `GET` | `/api/attendance-policies/{policy_id}` | `attendance_policy,view` | `200` / `404` |
| `POST` | `/api/attendance-policies` | `attendance_policy,create` | `201` / `422` |
| `PUT` | `/api/attendance-policies/{policy_id}` | `attendance_policy,update` | `200` / `404` / `422` |
| `DELETE` | `/api/attendance-policies/{policy_id}` | `attendance_policy,delete` | `200` / `404` / `422` |

Note: Add `->whereNumber('policy_id')` on `{policy_id}` routes.

---

## 6. Validation & Invariants

### Request Validation

- `name`: `required` (store) / `sometimes`, `string`, `max:100`, unique (`Rule::unique('attendance_policies', 'name')->ignore($policyId)` on update).
- `description`: `nullable`, `string`, `max:255`.
- `tardy_grace_seconds`: `required` (store) / `sometimes`, `integer`, `between:0,43200`.
- `tardy_count_from`: `required` (store) / `sometimes`, `Rule::in(['schedule_start', 'after_grace'])`.
- `absent_after_late_seconds`: `nullable`, `integer`, `between:60,86400`.
- `undertime_grace_seconds`: `required` (store) / `sometimes`, `integer`, `between:0,43200`.
- `early_in_window_seconds`: `sometimes`, `integer`, `between:0,43200`.
- `late_out_window_seconds`: `sometimes`, `integer`, `between:0,43200`.
- `duplicate_tap_window_seconds`: `sometimes`, `integer`, `between:0,3600`.
- `is_default`: `sometimes`, `boolean`.
- `is_active`: `sometimes`, `boolean`.

### Cross-Field & Business Rules

1. **Absent vs. Grace ordering**: When `absent_after_late_seconds` is non-null, it must be strictly greater than `tardy_grace_seconds` (cannot mark an employee absent while still inside their tardy grace period).
2. **Default must be active**: A policy with `is_default = true` cannot have `is_active = false` (`422`).
3. **Single default policy**: Inside `DB::transaction`, creating or updating a policy with `is_default = true` locks `attendance_policies` rows and sets `is_default = false` on any other default policy.
4. **Cannot delete assigned policy**: Deleting a policy referenced by any schedule (`schedules()->exists()`) returns `422` (`ValidationException::withMessages(['attendance_policy' => 'This attendance policy is assigned to one or more schedules.'])`).
5. **Cannot delete or deactivate the sole active default while active**: Deactivating or deleting `is_default = true` fails cleanly with `422` unless another policy is default first (or allow unsetting only when no longer default).

---

## 7. Attendance Calculation Integration (`AttendanceCalculator` & `EmployeeProcessLogsService`)

`EmployeeProcessLogsService` loads `'schedule.attendancePolicy'` alongside `'schedule.details'`, resolves the effective policy via `AttendancePolicyService::resolveForSchedule($employeeSchedule->schedule)`, and passes it to `AttendanceCalculator::calculate($date, collect([$detail]), $logs, $policy)`:

1. **Tardy**:
   - If `schedule_details.tardy_start` is explicitly set, preserve slot override (`tardyCutoff = $date $tardy_start`).
   - Otherwise `tardyCutoff = $scheduleStart->copy()->addSeconds($policy?->tardy_grace_seconds ?? 0)`.
   - When `$timeIn->greaterThan($tardyCutoff)`:
     - `'after_grace'` -> `$tardySeconds = $tardyCutoff->diffInSeconds($timeIn)`.
     - `'schedule_start'` (default) -> `$tardySeconds = $scheduleStart->diffInSeconds($timeIn)`.
2. **Late-as-Absent**:
   - If `$policy?->absent_after_late_seconds !== null` and `$timeIn->greaterThan($scheduleStart->copy()->addSeconds($policy->absent_after_late_seconds))`:
     - Mark `absent = true`, `rendered_seconds = 0`, `tardy_seconds = 0`, `undertime_seconds = $scheduledSeconds`, `remarks = 'absent'`.
3. **Undertime**:
   - If `schedule_details.undertime_start` is explicitly set, preserve slot override.
   - Otherwise `undertimeCutoff = $scheduleEnd->copy()->subSeconds($policy?->undertime_grace_seconds ?? 0)`.
   - When `$timeOut && $timeOut->lessThan($undertimeCutoff)`: `$undertimeSeconds = $timeOut->diffInSeconds($scheduleEnd)`.

---

## 8. Execution Order

1. **Backend Core (Phase 1)**: Migrations -> Model -> Query -> FormRequest -> Service -> Controller -> Routes -> Seeders (`MenuSeeder`, `AccessSeeder`, `AttendancePolicySeeder`, `ScheduleSeeder` / `DatabaseSeeder`).
2. **Schedule Link & Calculator Integration (Phase 2)**: Wire `attendance_policy_id` on `Schedule` and policy evaluation in `EmployeeProcessLogsService` + `AttendanceCalculator`.
3. **Backend Tests (Phase 3)**: Add `AttendancePolicyTest` and extend `AttendanceTest` / `ScheduleTest` / `MenuAccessTest`.
4. **Frontend Module (Phase 4)**: Build `/attendance-policies` management page + policy selector inside Schedule setup.
