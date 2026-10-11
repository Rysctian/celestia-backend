# Online applications: approvals and overtime

- Controllers, requests, services, models, query traits, factories, seeders, and
  tests live in their respective `OnlineApplication` folders.
- Fresh schema replaces the old overtime migration/model/service. Run
  `php artisan migrate:fresh --seed` when ready; existing records are not converted.
- `employee_id` and `approver_id` are employee business keys, not numeric user IDs.
  Actions use the authenticated user's linked employee; admin permissions do not
  bypass ownership, approval sequence, or finalized status.
- Application types: `overtime`, `official_business`, `correction`, `leave`.
  Only overtime has an application module today. The other types can have templates.

## Approver setup

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/api/approver-setups` | `approver_setup:view` |
| GET | `/api/approver-setups/{employee_id}/{application_type}` | `approver_setup:view` |
| PUT | `/api/approver-setups` | `approver_setup:update` |

PUT replaces only that employee/type's template. It requires one or more approvers,
with distinct, consecutive sequences starting at 1; payload order does not matter.
GET lists support `employee_id`, `application_type`, `limit` (1–100), and `offset`.
The detail endpoint returns the complete ordered template; an unset template is `[]`.

```json
{
  "employee_id": "EMP-20260001",
  "application_type": "overtime",
  "approvers": [
    { "approver_id": "EMP-20260002", "sequence": 1 },
    { "approver_id": "EMP-20260003", "sequence": 2 }
  ]
}
```

## Overtime

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/api/overtime-applications` | `overtime_application:view` |
| GET | `/api/overtime-applications/{application_id}` | `overtime_application:view` |
| POST | `/api/overtime-applications` | `overtime_application:create` |
| POST | `/api/overtime-applications/{application_id}/approve` | `overtime_application:update` |
| POST | `/api/overtime-applications/{application_id}/disapprove` | `overtime_application:update` |
| POST | `/api/overtime-applications/{application_id}/cancel` | `overtime_application:update` |

POST submits immediately; there is no draft/edit/delete/bypass route. Submission
derives the applicant from authentication. Optional `employee_id` must match them.
Parent, details, and approval snapshot are saved in one transaction; missing setup
rolls the submission back. Reads expose only the applicant's and snapshot approvers'
applications. Lists include ordered `details` and `approvals` without invented totals.

```json
{
  "reason": "Finish month-end reports.",
  "details": [
    { "date": "2026-10-12", "start_time": "17:00", "end_time": "21:00", "break_seconds": 1800 },
    { "date": "2026-10-12", "start_time": "21:00", "end_time": "22:00" }
  ]
}
```

- Each detail uses `YYYY-MM-DD` and same-day `HH:MM` start/end; end must be later.
  Multiple dates and multiple non-overlapping periods per date are supported.
  Adjacent periods are allowed. An overnight request must be split across dates.
- An assignment and configured weekday are required. Rest days are allowed.
  Work overlap checks include the previous day's overnight shifts, use each
  schedule's timezone, and cover the full scheduled window, including its breaks.
- `break_seconds` is an optional nonnegative integer, defaults to zero, and must be
  shorter than the period. `requested_seconds` is calculated by the backend.
  Client-supplied calculated fields and extra detail keys are rejected.
- Details return `break_duration` as `HH:MM` for display; storage remains seconds.
  A frontend entering `00:30` should send `1800`. Exact seconds remain in the response.
- These are requested hours only. Actual rendered OT and attendance credits are
  outside this module; there is no legacy attendance approval endpoint.
- List filters: scalar `employee_id`, `status`, `search` (reason/employee ID),
  `date_from`/`date_to` on `submitted_at`, `limit`, `offset`.
  Sorts: `id`, `employee_id`, `status`, `submitted_at`, `created_at`;
  default ID descending with stable ID tie-breaking.
- Decisions accept optional `{ "remarks": "..." }`. The current approver is the
  first pending sequence whose predecessors are all approved. Approval advances
  the chain; the last approval finalizes the parent. Disapproval finalizes it and
  prevents later actions, leaving untouched pending rows as audit history.
- Only the applicant can cancel a pending application. Already approved rows stay
  intact; remaining pending rows become cancelled with `acted_at` and optional remarks.
  Every acted row receives `acted_at`. Terminal applications cannot be acted on again.
- Responses use `message`/`data`, 201 for submission, 200 for other successful
  operations, 422 for validation/state, 403 for denied actions, and 404 for
  missing or inaccessible detail reads.

## Reusing approvals

`ApplicationApprovalService` knows no overtime fields. A future application model
needs `employee_id`, `status`, an ID, and timestamps. Register its application-type
alias in `AppServiceProvider`'s morph map and add the ordered `approvals()` relation
shown in `OvertimeApplication`.

- Call `snapshot($application)` inside the application's submission transaction.
- Call `decide($application, $actorEmployeeId, 'approved'/'disapproved', $remarks)`.
- Call `cancel($application, $actorEmployeeId, $remarks)`.
- Each domain keeps its own validation, visibility, route permissions, and details.
  Setup edits never replace existing snapshots. Application-type + ID + sequence
  isolate different modules even when their numeric IDs coincide.
- Factories can build all four tables. Fresh seeding creates missing templates for
  non-admin employees and one submitted OT example through the same services.
  Online-application seeders preserve existing templates and snapshots on rerun.

See [approver rules](process/approver-setup.md) and [overtime rules](process/overtime-application-process.md).
