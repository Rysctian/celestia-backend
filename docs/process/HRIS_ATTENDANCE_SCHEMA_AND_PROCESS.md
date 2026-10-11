# HRIS Attendance Schema and Processing Specification

## Goal

Use a layered attendance pipeline:

```text
employee_logs
    ↓
timesheets
    ↓
employee_attendance
    ↓
attendance_cutoff_summary
```

Responsibilities:

- `employee_logs` = raw attendance taps exactly as received.
- `timesheets` = organized/classified interpretation of raw logs.
- `employee_attendance` = polished result per employee + date + schedule block.
- `attendance_cutoff_summary` = confirmed accumulated attendance totals for one cutoff period.

This specification ends at confirmed attendance. **Do not implement payroll tables, salary calculation, deductions, or monetary fields yet.**

---

# Core Rules

1. Never classify `IN` / `OUT` directly in `employee_logs`.
2. Never delete raw logs because they are duplicate, invalid, or unused.
3. `employee_attendance` represents one expected schedule block, not whole-cutoff totals.
4. Accumulated duration totals belong in `attendance_cutoff_summary`.
5. Store calculated durations as integer **seconds**.
6. Store actual attendance timestamps as `DATETIME` when overnight schedules are possible.
7. Never invent a missing time-in or time-out.
8. One employee may have multiple schedule blocks in one day.
9. Reprocessing must always be possible from raw logs before cutoff confirmation/lock.
10. Attendance computation must be deterministic and idempotent.

---

# 1. `employee_logs`

## Purpose

Store raw biometric/API/mobile/manual taps exactly as received.

```text
employee_logs
---------------------------------------------
id                      BIGINT PK
employee_id             VARCHAR / FK
logged_at               DATETIME
source                   VARCHAR(30)
device_id                VARCHAR(100) NULL
external_log_id          VARCHAR(150) NULL
raw_payload              JSON NULL
created_at               TIMESTAMP
updated_at               TIMESTAMP
```

Recommended indexes:

```text
INDEX(employee_id, logged_at)
INDEX(logged_at)
INDEX(source, device_id)
```

If the source guarantees a stable external ID:

```text
UNIQUE(source, external_log_id)
```

Raw logs must remain unchanged except for safe metadata corrections. Do not add `in/out` classification here.

---

# 2. `timesheets`

## Purpose

Store the organized/classified interpretation of raw logs.

Each row must remain traceable to one `employee_logs` row.

```text
timesheets
------------------------------------------------
id                          BIGINT PK
employee_log_id             BIGINT FK UNIQUE
employee_id                 VARCHAR / FK
attendance_date             DATE
schedule_id                 BIGINT FK NULL
schedule_detail_id          BIGINT FK NULL
logged_at                   DATETIME
log_type                    VARCHAR(20) NULL
status                      VARCHAR(30)
reason                      VARCHAR(255) NULL
processed_at                DATETIME NULL
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
```

### `log_type`

```text
in
out
NULL
```

### `status`

```text
pending
selected
duplicate
ignored
unmatched
invalid
```

Example:

```text
07:58:04 -> in   -> selected
07:59:10 -> NULL -> duplicate
12:01:02 -> out  -> selected
13:05:15 -> in   -> selected
18:02:44 -> out  -> selected
```

Never delete duplicate or unmatched taps. Keep their status and reason for audit/debugging.

---

# 3. `employee_attendance`

## Purpose

Store the polished attendance result for **one employee + one date + one expected schedule block**.

A day with multiple schedule blocks may create multiple rows.

Do **not** store whole-cutoff accumulated totals here.

## Recommended Migration

```php
Schema::create('employee_attendance', function (Blueprint $table) {
    $table->id();

    $table->string('employee_id');
    $table->foreign('employee_id')
        ->references('employee_id')
        ->on('users')
        ->cascadeOnDelete();

    $table->date('date');

    $table->foreignId('schedule_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->foreignId('schedule_detail_id')
        ->nullable()
        ->constrained('schedule_details')
        ->nullOnDelete();

    $table->dateTime('sched_start')->nullable();
    $table->dateTime('sched_end')->nullable();

    $table->dateTime('time_in')->nullable();
    $table->dateTime('time_out')->nullable();

    $table->boolean('tardy')->default(false);
    $table->unsignedInteger('tardy_seconds')->default(0);

    $table->boolean('undertime')->default(false);
    $table->unsignedInteger('undertime_seconds')->default(0);

    $table->boolean('absent')->default(false);

    $table->boolean('early_dismiss')->default(false);
    $table->unsignedInteger('early_dismiss_seconds')->default(0);

    $table->boolean('missing_time_in')->default(false);
    $table->boolean('missing_time_out')->default(false);

    $table->string('status', 30)->default('present');
    $table->text('remarks')->nullable();
    $table->json('details')->nullable();

    $table->dateTime('computed_at')->nullable();
    $table->dateTime('confirmed_at')->nullable();

    $table->timestamps();

    $table->unique([
        'employee_id',
        'date',
        'schedule_detail_id',
    ]);

    $table->index(['employee_id', 'date']);
    $table->index(['date', 'status']);
});
```

## Why `DATETIME` instead of `TIME`?

Prefer `DATETIME` for:

```text
sched_start
sched_end
time_in
time_out
```

because overnight schedules can cross dates.

Example:

```text
2026-10-06 22:00:00
→
2026-10-07 06:00:00
```

Plain `TIME` cannot identify which day the timeout belongs to.

---

# `employee_attendance` Field Rules

## `tardy` / `tardy_seconds`

Example:

```text
sched_start   = 08:00:00
time_in       = 08:07:35

tardy         = true
tardy_seconds = 455
```

`tardy_seconds` is authoritative. The boolean is only a convenient flag.

## `undertime` / `undertime_seconds`

Use for work time lost according to attendance rules.

```text
undertime = true
undertime_seconds = 1200
```

## `early_dismiss`

Keep this only if the business distinguishes early dismissal from general undertime.

If both mean exactly the same thing in the organization, remove `early_dismiss` and use only `undertime`.

## `absent`

Do not mark an employee absent only because logs are missing. First consider applicable attendance exceptions such as:

```text
approved leave
official business
rest day
holiday
suspension
schedule cancellation
```

## Missing Logs

Never fabricate missing values.

Missing time-in:

```text
sched_start:       13:00
sched_end:         17:00
time_in:           NULL
time_out:          17:03
missing_time_in:   true
missing_time_out:  false
status:            incomplete
remarks:           "Missing time-in."
```

Missing time-out:

```text
time_in:           13:02
time_out:          NULL
missing_time_in:   false
missing_time_out:  true
status:            incomplete
remarks:           "Missing time-out."
```

Missing both:

```text
time_in:  NULL
time_out: NULL
status:   absent
remarks:  "No valid attendance logs."
```

---

# Do Not Put Cutoff Totals in `employee_attendance`

Do not store these accumulated values in each per-block attendance row:

```text
scheduled_seconds
rendered_seconds
payable_seconds
late_seconds
undertime_seconds
break_seconds
overtime_rendered_seconds
overtime_approved_seconds
night_diff_seconds
regular_seconds
```

Per-block `employee_attendance` should only contain the facts and exceptions for that block, such as:

```text
sched_start / sched_end
time_in / time_out
tardy_seconds
undertime_seconds
early_dismiss_seconds
missing flags
status
remarks
```

Whole-cutoff totals belong in the confirmed summary below.

---

# 4. `attendance_cutoff_summary`

## Purpose

Store the accumulated, reviewed, confirmed attendance totals for **one employee within one cutoff period**.

This table is produced from finalized `employee_attendance` rows.

## Recommended Migration

```php
Schema::create('attendance_cutoff_summary', function (Blueprint $table) {
    $table->id();

    $table->string('employee_id');
    $table->foreign('employee_id')
        ->references('employee_id')
        ->on('users')
        ->cascadeOnDelete();

    $table->date('date_from');
    $table->date('date_to');

    $table->unsignedInteger('scheduled_seconds')->default(0);
    $table->unsignedInteger('rendered_seconds')->default(0);
    $table->unsignedInteger('payable_seconds')->default(0);

    $table->unsignedInteger('late_seconds')->default(0);
    $table->unsignedInteger('undertime_seconds')->default(0);
    $table->unsignedInteger('break_seconds')->default(0);

    $table->unsignedInteger('overtime_rendered_seconds')->default(0);
    $table->unsignedInteger('overtime_approved_seconds')->default(0);

    $table->unsignedInteger('night_diff_seconds')->default(0);
    $table->unsignedInteger('regular_seconds')->default(0);

    $table->unsignedInteger('present_days')->default(0);
    $table->unsignedInteger('absent_days')->default(0);
    $table->unsignedInteger('incomplete_days')->default(0);

    $table->json('details')->nullable();

    $table->dateTime('computed_at')->nullable();
    $table->dateTime('confirmed_at')->nullable();
    $table->string('confirmed_by')->nullable();
    $table->dateTime('locked_at')->nullable();

    $table->timestamps();

    $table->unique([
        'employee_id',
        'date_from',
        'date_to',
    ]);

    $table->index(['date_from', 'date_to']);
    $table->index(['employee_id', 'date_from', 'date_to']);
});
```

This design intentionally does **not** depend on a payroll table or payroll cutoff foreign key yet.

---

# Meaning of Cutoff Totals

## `scheduled_seconds`

Total required attendance time across all applicable schedule blocks in the cutoff.

## `rendered_seconds`

Total valid time actually rendered based on finalized attendance.

## `payable_seconds`

Keep this only if the attendance module already determines the accepted/creditable attendance duration.

If the business wants all payability decisions handled later by another module, rename this to:

```text
credited_seconds
```

Recommended for now:

```text
credited_seconds
```

because it avoids coupling attendance directly to salary computation.

If using this recommendation, replace `payable_seconds` with:

```php
$table->unsignedInteger('credited_seconds')->default(0);
```

## `late_seconds`

Accumulated tardiness for the cutoff.

## `undertime_seconds`

Accumulated undertime for the cutoff.

## `break_seconds`

Accumulated excluded break duration when needed for attendance computation/audit.

## `overtime_rendered_seconds`

Total extra time actually rendered beyond regular attendance.

## `overtime_approved_seconds`

Approved overtime duration after the attendance approval workflow.

## `night_diff_seconds`

Total valid attendance duration inside the configured night differential window.

## `regular_seconds`

Total regular credited attendance duration before special attendance classifications.

---

# Processing Flow

## Step 1 — Capture Raw Logs

Insert every received attendance tap into:

```text
employee_logs
```

Do not classify or calculate attendance here.

---

## Step 2 — Organize / Classify Logs

For the employee and relevant attendance date:

1. Load raw `employee_logs`.
2. Load the employee's effective schedule.
3. Load all schedule blocks for that day.
4. Create/update one `timesheets` row per raw log.
5. Detect duplicate/spam taps.
6. Match candidate taps against schedule blocks.
7. Classify selected taps as `in` or `out`.
8. Keep unused logs as `duplicate`, `ignored`, `unmatched`, or `invalid`.

Do not classify by simple alternating order:

```text
1st = IN
2nd = OUT
3rd = IN
4th = OUT
```

Schedule context must decide the meaning of logs.

---

## Step 3 — Build Final Per-Block Attendance

For every expected schedule block, create/update one `employee_attendance` row.

Populate:

```text
sched_start
sched_end
time_in
time_out

tardy
tardy_seconds
undertime
undertime_seconds
absent
early_dismiss
early_dismiss_seconds
missing_time_in
missing_time_out
status
remarks
```

This remains a per-block record.

---

## Step 4 — Review / Correct Attendance

Before cutoff confirmation, authorized HR workflows may:

```text
approve corrections
resolve missing logs
approve leave
approve official business
approve overtime
reprocess attendance
```

Raw `employee_logs` must remain unchanged.

---

## Step 5 — Build Cutoff Summary

When the attendance cutoff is ready for confirmation:

1. Load all finalized `employee_attendance` rows within `date_from` and `date_to`.
2. Load approved attendance-related records required by the computation.
3. Accumulate all duration values in seconds.
4. Count present, absent, and incomplete days according to business rules.
5. Create/update one `attendance_cutoff_summary` row per employee and cutoff range.
6. Set `computed_at`.
7. After review, set `confirmed_at`, `confirmed_by`, and `locked_at`.

Example:

```text
Employee 25
Cutoff: Oct 1 - Oct 15

scheduled_seconds            = 345600
rendered_seconds             = 338400
credited_seconds             = 334800
late_seconds                 = 1800
undertime_seconds            = 900
overtime_rendered_seconds    = 7200
overtime_approved_seconds    = 5400
night_diff_seconds           = 10800
regular_seconds              = 334800
```

No salary or monetary computation belongs in this step.

---

# Multiple Schedule Blocks Example

Schedule:

```text
08:00 - 10:00
10:30 - 12:00
13:00 - 17:00
```

Logs:

```text
07:58
10:02
10:31
12:01
17:03
```

Final `employee_attendance`:

```text
Row 1
08:00 - 10:00
07:58 - 10:02
status = present

Row 2
10:30 - 12:00
10:31 - 12:01
status = present

Row 3
13:00 - 17:00
NULL - 17:03
status = incomplete
missing_time_in = true
remarks = "Missing time-in."
```

Do not force the fifth log into the wrong type merely because there is an odd number of logs.

---

# Reprocessing Rules

The system must support:

```text
reprocessAttendance(employee_id, date)
```

Before cutoff lock, reprocessing may:

1. reread `employee_logs`,
2. rebuild/reclassify `timesheets`,
3. rebuild `employee_attendance`,
4. recalculate an open `attendance_cutoff_summary` if affected.

If a cutoff summary is already locked, do not silently overwrite it.

Use an authorized reopen/correction workflow.

---

# Transaction Requirement

Processing one employee/date should run in a database transaction.

Cutoff summary generation/confirmation should also be transactional.

Never leave half-computed timesheets, attendance rows, or cutoff summaries.

---

# Idempotency Requirement

Running the same processor multiple times with unchanged inputs must produce the same result.

Use where appropriate:

```text
unique constraints
updateOrCreate()
upsert()
```

Do not create duplicate timesheet or attendance rows during reprocessing.

---

# Suggested Service Structure

```text
app/
  Services/
    Attendance/
      AttendanceProcessor.php
      TimesheetProcessor.php
      AttendanceCalculator.php
      AttendanceCutoffService.php
```

## `TimesheetProcessor`

Responsible for:

```text
raw log organization
duplicate detection
schedule matching
IN/OUT classification
unmatched/ignored statuses
```

## `AttendanceCalculator`

Responsible for:

```text
per-block attendance creation
tardy
undertime
missing logs
absence/incomplete status
remarks
```

## `AttendanceCutoffService`

Responsible for:

```text
loading finalized attendance within a cutoff
accumulating seconds
counting attendance states
confirming/locking cutoff summaries
```

## `AttendanceProcessor`

Orchestrates the complete attendance flow.

---

# Edge Cases Codex Must Handle

```text
1. No logs
2. One log only
3. Odd number of logs
4. Duplicate/spam taps
5. Missing time-in
6. Missing time-out
7. Multiple schedule blocks
8. Very early arrival
9. Very late timeout
10. Log between schedule blocks
11. Overnight schedules
12. Cross-midnight logs
13. Rest day
14. Holiday
15. Approved leave
16. Approved overtime
17. Rendered but unapproved overtime
18. Late + undertime on the same date
19. Multiple attendance devices
20. Manual attendance correction
21. Reprocessing
22. Locked/confirmed cutoff
```

---

# Final Architecture

```text
RAW TRUTH
employee_logs
        ↓
INTERPRETED LOGS
timesheets
        ↓
FINAL PER-BLOCK ATTENDANCE
employee_attendance
        ↓
CONFIRMED CUTOFF TOTALS
attendance_cutoff_summary
```

Meaning:

```text
employee_logs
"What taps did we receive?"

timesheets
"How did the system interpret those taps?"

employee_attendance
"What happened on this specific schedule block?"

attendance_cutoff_summary
"What are the employee's confirmed attendance totals for this cutoff?"
```

Do not implement payroll schema or monetary computation as part of this specification.
