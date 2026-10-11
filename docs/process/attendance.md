# Attendance API and processing

The module follows [the attendance specification](HRIS_ATTENDANCE_SCHEMA_AND_PROCESS.md):
`employee_logs` → `timesheets` → `employee_attendance` → `attendance_cutoff_summary`.
Controllers return the existing `message` / `data` JSON shape. FormRequests validate
input, concrete services handle processing, and helpers handle schedule timestamps
and duration arithmetic. All durations are integer seconds. No payroll or monetary
calculation is included.

## Setup

Apply the new migrations to an existing database:

```shell
php artisan migrate
```

For demo attendance on existing employee schedule assignments:

```shell
php artisan db:seed --class=AttendanceSeeder
```

`DatabaseSeeder` also calls `AttendanceSeeder` after employees and schedules are
created. The demo seeder captures taps for the first working date in each
assignment's first week, processes them through the real services, and builds open
cutoff summaries. The first block has five minutes of tardiness. Stable external
IDs prevent duplicate demo taps when the attendance seeder is repeated. Demo
summaries stay open for review.

The individual seeders are `EmployeeLogSeeder`, `TimesheetSeeder`,
`EmployeeAttendanceSeeder`, and `AttendanceCutoffSummarySeeder`. Timesheet and
attendance seeding use the same processor to keep the pipeline consistent.
Optional `AttendanceExceptionSeeder` and `AttendanceCorrectionSeeder` add one
review example to existing demo data when run individually; they are not part of
the default seed chain.

## Access

All endpoints require Sanctum authentication. The Attendance menu under Daily
Time Record grants `view`, `create`, and `update` actions. Default admin grants
include attendance; existing custom grants are preserved. HR roles can receive
attendance grants through the existing role-access API.

Employees with Employee 201 File view access can read their own logs, timesheets,
attendance, and cutoffs. They cannot write attendance or read another employee's
records. Review/approval fields always come from the authenticated user.

## Routes

| Method | Route | Purpose / permission |
| --- | --- | --- |
| GET | `/api/employee-logs` | Raw taps, self or Attendance view |
| POST | `/api/employee-logs` | Capture one raw tap, Attendance create |
| GET | `/api/timesheets` | Interpreted taps, self or Attendance view |
| GET | `/api/employee-attendance` | Per-block attendance and correction history, self or Attendance view |
| POST | `/api/employee-attendance/process` | Process/reprocess an employee/date, Attendance update |
| POST | `/api/employee-attendance/{attendance_id}/corrections` | Approve corrected timestamps, Attendance update |
| POST | `/api/attendance-exceptions` | Approve a daily attendance exception, Attendance update |
| POST | `/api/attendance-overtime/{overtime_id}/approve` | Approve an existing overtime application, Attendance update |
| GET | `/api/attendance-cutoffs` | Cutoff summaries, self or Attendance view |
| GET | `/api/attendance-cutoffs/{cutoff_id}` | One summary, self or Attendance view |
| POST | `/api/attendance-cutoffs` | Generate/update an open cutoff, Attendance create |
| POST | `/api/attendance-cutoffs/{cutoff_id}/confirm` | Confirm and lock, Attendance update |
| POST | `/api/attendance-cutoffs/{cutoff_id}/reopen` | Reopen with a reason, Attendance update |

List endpoints require `employee_id`. Optional parameters are `date_from`,
`date_to`, `limit` (default 15, maximum 100), and `offset` (default 0).
Logs, timesheets, and attendance also accept `sort_by` (`id`, `employee_id`,
`created_at`, or the endpoint's date column), and `sort_order` (`asc`/`desc`).
Timesheets and attendance accept `status`. Raw-log date filters use UTC calendar
dates; timesheet/attendance dates use the schedule's local work date. Cutoff date
filters find overlapping periods.

### Capture a tap

POST `/api/employee-logs`:

```json
{
  "employee_id": "EMP-20260001",
  "logged_at": "2026-10-06T07:58:04+08:00",
  "source": "biometric",
  "device_id": "lobby-reader",
  "external_log_id": "device-event-123",
  "raw_payload": {"original_device_data": "preserved"}
}
```

Timestamps require ISO 8601 with seconds and an explicit offset or `Z`. They are
stored as UTC DATETIME and returned in UTC; the original payload remains intact.
Do not send `log_type`. Only timesheets classify IN/OUT.

`device_id`, `external_log_id`, and `raw_payload` are optional. Supply an external
ID only when it is stable and unique within the source (include a device prefix if
the vendor reuses IDs across devices). An identical retry returns the existing
row with HTTP 200; a conflicting retry returns HTTP 422. Without an external ID,
every tap is stored, including duplicate taps. New captures return HTTP 201.

Raw taps have no update/delete API. Late device sync remains capturable even
after confirmation, without changing confirmed results. Capture does not
automatically process attendance; call the processing endpoint after ingestion.

### Process an employee/date

POST `/api/employee-attendance/process`:

```json
{"employee_id": "EMP-20260001", "date": "2026-10-06"}
```

The response contains `date`, daily `status`, `attendance`, and `timesheets`.
The processor resolves effective assignments, matches taps against schedule
boundaries, classifies one selected IN and OUT for each block, and preserves
other taps with statuses and reasons. Matching considers the preceding and
following work dates, so an overnight timeout belongs to the shift's start date.
A tap is used once. Repeated processing updates the same derived rows.
If a schedule change moves a tap to another work date, the previous date is
recomputed in the same transaction. A confirmed neighboring date must be reopened
before its tap can be reassigned.

Default matching and credit rules are explicit in `config/attendance.php`:

- Arrival matching starts four hours before a block; departure matching ends
  six hours after it. Interior taps use the nearest schedule boundary.
- Repeated taps within 90 seconds of a retained tap at the same boundary are
  duplicates, including taps from different devices. The earliest distinct IN
  and latest distinct OUT are selected.
- A nonzero-distance tie between boundaries stays unmatched for HR review.
  An exact adjoining boundary uses OUT; one tap cannot serve both blocks.
- Missing timestamps remain null. Incomplete blocks contribute no rendered
  duration until corrected. No taps on a working block means absent unless a
  daily approved exception applies. Rest/unassigned days have no regular rows.
- Tardiness and undertime are measured from the corresponding schedule boundary.
  There is no separate early-dismissal policy, so those fields stay zero/false.
- Overnight end times earlier than start times resolve to the following date;
  equal times and overlapping weekly slots are rejected by schedule validation.

These are initial attendance policies; tune configuration when HR defines
different matching windows, exception credits, or night hours.

### Review a block

POST `/api/employee-attendance/123/corrections`:

```json
{
  "time_in": "2026-10-06T13:02:00+08:00",
  "time_out": "2026-10-06T17:00:00+08:00",
  "reason": "HR verified the missing timeout."
}
```

Both timestamp keys are required but may be null. Corrections must be ordered,
fit the block's matching windows, and avoid overlap with another complete block.
Each approved correction is retained in `attendance_corrections`. The newest
correction supplies the block's timestamps on every reprocess. Interpreted raw
taps remain traceable to their original logs. Approval immediately reprocesses
the date and refreshes affected open summaries.

POST `/api/attendance-exceptions` accepts:

```json
{
  "employee_id": "EMP-20260001",
  "date": "2026-10-06",
  "type": "leave",
  "reason": "Approved leave reference LV-123."
}
```

One approved exception applies to all blocks on the employee/date. Types are
`leave`, `official_business`, `holiday`, `suspension`, and
`schedule_cancellation`. The first three credit scheduled duration by default;
suspension/cancellation remove required duration. Exceptions suppress absence,
tardiness, and undertime without inventing actual timestamps. This is a small HR
review input; separate leave/holiday application modules can integrate later.

### Build and confirm a cutoff

POST `/api/attendance-cutoffs`:

```json
{"employee_id": "EMP-20260001", "date_from": "2026-10-01", "date_to": "2026-10-15"}
```

Ranges are inclusive and limited to 366 dates. Generation processes every date,
including working dates without logs, before accumulating the summary.

- `scheduled_seconds`: required block duration minus configured unpaid breaks.
- `rendered_seconds`: complete actual attendance intervals minus excluded breaks.
- `regular_seconds`: rendered overlap with regular blocks, or the scheduled
  duration credited by an approved exception.
- `overtime_rendered_seconds`: actual duration outside regular blocks.
- `overtime_approved_seconds`: rendered extra duration intersecting approved
  overtime windows. Overlapping approvals are merged; unrendered approved hours
  and pending applications never create credits.
- `credited_seconds`: regular credited duration plus rendered approved overtime.
- `break_seconds`: breaks actually excluded from complete blocks. Gaps between
  split blocks are outside required time and are not invented as rendered breaks.
- `night_diff_seconds`: complete actual attendance inside the local 22:00–06:00
  window. Because legacy unpaid breaks have no timestamps, their duration is
  conservatively subtracted from night duration first.

An overtime application provides expected block context on a rest/unassigned
date. Such rows link `overtime_application_id` rather than a regular schedule
detail; a separate unique constraint prevents duplicate overtime rows. These
rows have zero required regular duration and do not count as regular present
days. Pending overtime can be rendered but receives no credits until approved.
Rest-day taps without an application stay unmatched for review. Creating overtime
applications uses the existing model; this module adds approval of existing ones.

Each work date counts once: any incomplete block makes an incomplete day;
otherwise any present regular block makes a present day; otherwise an entirely
absent date makes an absent day. Fully excused and rest dates do not increase
these three counters. `details.exception_days` lists excused work dates.

POST `/api/attendance-cutoffs/123/confirm` requires no body. It rebuilds the
summary, rejects unresolved incomplete attendance and unfinished/future shifts,
then sets attendance `confirmed_at` and summary `confirmed_at`, `confirmed_by`,
and `locked_at` together. Confirming again returns the existing confirmed result.
Overlapping confirmed periods block processing and review changes.

To reopen, POST `/api/attendance-cutoffs/123/reopen` with
`{"reason": "Review late device sync."}`. The summary retains the reopening
reason, actor, previous confirmation, and previous totals in its history. Once
reopened, process the affected date and confirm again after review.

Processing, corrections, exceptions, overtime approval, cutoff generation, and
confirmation use database transactions and a shared employee row lock. Foreign
keys restrict deletion of raw/confirmed history. The functional tests run on
SQLite; database-specific concurrency behavior needs validation on the deployed
database engine.

## Factories and checks

Factories exist for every new model. `EmployeeAttendanceFactory` supports
`absent()` and `incomplete()`. `OvertimeApplicationFactory` supports `approved()`.
Use services for realistic pipeline fixtures; standalone factories create
individual records without running attendance processing.

```shell
php artisan test --filter=AttendanceTest
php artisan test --filter=ScheduleTest
```
