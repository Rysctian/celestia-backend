# Schedules and employee tagging

This version uses three tables: `schedules`, `schedule_details`, and
`employee_schedules`. Controllers call concrete services, requests validate input,
and responses use the same `message` / `data` structure as EmployeeController.
Each schedule controller uses one request class. Its methods select validation rules
by HTTP method so Scramble can document the corresponding body and query fields.
Employee schedule history checks own-record or role-grant access in the controller;
date-range reads check it in the request class.
Date-specific overrides, leave, holidays, and attendance are not part
of this version.

## Create development data

To rebuild your development database and seed employees, users, and schedules:

```shell
php artisan migrate:fresh --seed
```

This command deletes the existing database tables. To keep existing data, run
`php artisan migrate` instead. The new migration renames existing `schedule_days`
rows to `schedule_details` and allows multiple slots per weekday. Existing rows
keep their old unpaid-break setting; new slots represent breaks as gaps.
ScheduleSeeder and EmployeeScheduleSeeder are called
after the existing employee/user creation in DatabaseSeeder. They create two weekly
templates (office 08:00–12:00 and 13:00–17:00, and night 22:00–07:00, both
Monday–Friday), then alternate assignments across the freshly created
employees. Assignment dates start on the first day of the current month.

The existing seeded `admin@example.com` account (password `a`) is linked to the
first employee, now assigned the Admin role, so it can test these endpoints in Scramble.
These are development credentials. The other generated user for that same employee
also has the Admin role. Passwords were not changed by this feature.

To seed templates and assign employees added since the last seed, without replacing
existing assignments:

```shell
php artisan db:seed --class=ScheduleSeeder
php artisan db:seed --class=EmployeeScheduleSeeder
```

The assignment seeder requires an existing admin user. Employees with any assignment
history are skipped. Creating an employee through the API does not automatically
assign a schedule; use the tagging endpoint to choose one.

## Access

Use Sanctum authentication, including the local Scramble token flow described in
`authentication.md`. Schedule List grants control schedule reads and writes.
Employees with Employee 201 File `view` access can view their own schedule and history.
Employee create/update actions require matching Employee 201 File grants. See
`menu-access.md` for the role and menu API. The legacy employee `type` field no
longer grants access.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/schedules` | List templates and their details (admin) |
| GET | `/api/schedules/{schedule_id}` | Read a template (admin) |
| POST | `/api/schedules` | Create a complete weekly template (admin) |
| PUT | `/api/schedules/{schedule_id}` | Update or archive a template (admin) |
| POST | `/api/employee-schedules` | Assign to 1–100 employees atomically (admin) |
| PUT | `/api/employee-schedules/{assignment_id}` | Set the last effective date (admin) |
| GET | `/api/employees/{employee_id}/schedules` | Assignment history (self/admin) |
| GET | `/api/employees/{employee_id}/schedule?from=2026-10-01&to=2026-10-31` | Resolved daily schedule (self/admin) |

Employee route parameters and `employee_ids` are business IDs such as
`EMP-20260001`, not numeric row IDs. Schedule and assignment IDs are numeric.

## Create a template

POST `/api/schedules`:

```json
{
  "name": "Office - Mon to Fri",
  "timezone": "Asia/Manila",
  "is_active": true,
  "details": [
    {"day_of_week": 1, "is_rest_day": false, "start_time": "08:00", "end_time": "12:00", "ends_next_day": false},
    {"day_of_week": 1, "is_rest_day": false, "start_time": "13:00", "end_time": "17:00", "ends_next_day": false},
    {"day_of_week": 2, "is_rest_day": false, "start_time": "08:00", "end_time": "12:00", "ends_next_day": false},
    {"day_of_week": 2, "is_rest_day": false, "start_time": "13:00", "end_time": "17:00", "ends_next_day": false},
    {"day_of_week": 3, "is_rest_day": false, "start_time": "08:00", "end_time": "12:00", "ends_next_day": false},
    {"day_of_week": 3, "is_rest_day": false, "start_time": "13:00", "end_time": "17:00", "ends_next_day": false},
    {"day_of_week": 4, "is_rest_day": false, "start_time": "08:00", "end_time": "12:00", "ends_next_day": false},
    {"day_of_week": 4, "is_rest_day": false, "start_time": "13:00", "end_time": "17:00", "ends_next_day": false},
    {"day_of_week": 5, "is_rest_day": false, "start_time": "08:00", "end_time": "12:00", "ends_next_day": false},
    {"day_of_week": 5, "is_rest_day": false, "start_time": "13:00", "end_time": "17:00", "ends_next_day": false},
    {"day_of_week": 6, "is_rest_day": true, "start_time": null, "end_time": null, "ends_next_day": false},
    {"day_of_week": 7, "is_rest_day": true, "start_time": null, "end_time": null, "ends_next_day": false}
  ]
}
```

All seven weekdays are required, but a working weekday may have two or more detail
rows. Times use `HH:mm`. Each slot must last longer than zero and less than 24 hours.
The gap between slots is break time; Monday above has a 12:00–13:00 break and
480 scheduled minutes. Rest days have one row with null times. Overlapping slots,
including Sunday night into Monday, are rejected. An overnight slot belongs to the
date it starts.

Each `schedule_details` row has a generated `code_day` column, returned in
template responses: `1 = M`, `2 = T`, `3 = W`, `4 = TH`, `5 = F`, `6 = S`,
`7 = SUN`. The database derives it from `day_of_week`, including for existing rows
and seeded data. Send only `day_of_week` when creating or updating details;
`code_day` is read-only.

Once assigned, working hours and timezone cannot be edited. Create a new template
for new hours. Name and `is_active` remain editable. Archive with
`{"is_active": false}`; archived templates continue to resolve existing assignments.

## Assign and change schedules

POST `/api/employee-schedules`:

```json
{
  "employee_ids": ["EMP-20260001", "EMP-20260002"],
  "schedule_id": 1,
  "effective_from": "2026-11-01",
  "effective_to": null
}
```

Both effective dates are inclusive; null end date means ongoing. Any conflict rejects
the entire batch. The authenticated user supplies `assigned_by` on the server.
Transactions and employee row locks serialize assignment writes; locking assignment
reads avoid stale overlap checks on MySQL. Schedule locks coordinate template edits
and new assignments. SQLite tests cover functional behavior; concurrency should be
verified on your deployed database engine.

To switch an ongoing assignment on November 1, first PUT its assignment ID with
`{"effective_to": "2026-10-31"}`, then POST the new assignment starting November 1.
These are two requests: if the second fails, retry it or extend the old assignment.
The update endpoint only changes the end date. It cannot edit an assignment already
ended in the past or set an end date earlier than today/the assignment start.
There is no delete endpoint, preserving assignment history.

Date ranges must not overlap. The service also checks actual shift timestamps near
assignment boundaries, preventing an old night shift from overlapping a new morning
slot. Date queries accept up to 366 days and return `unassigned`, `rest_day`, or
`working`. Working dates contain a `shifts` array with each slot's timezone-qualified
timestamps and minutes. `scheduled_minutes` sums the slots, excluding gaps. Old rows
with an unpaid-break setting still subtract that amount until their template is replaced.

## Factories

```php
Schedule::factory()->withWeek()->create();         // Complete split-slot office week
Schedule::factory()->withWeek(true)->create();     // Complete night week
ScheduleDetail::factory()->create();              // One slot on a new empty template
EmployeeSchedule::factory()->create();            // New employee + complete office schedule
```

Factories are fixture helpers. Production writes should use validated requests and
services so assignment and shift rules are enforced.
