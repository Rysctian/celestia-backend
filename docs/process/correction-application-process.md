# Correction of Logs — Backend Process

Build the Correction of Logs online application flow, following the approval and detail structure used by Overtime and Official Business Applications, and pairing AM/PM log entries per date just like Manual Attendance Logs.

## Tables

### correction_applications

```text
id
employee_id
reason
status
submitted_at
created_at
updated_at
```

Status:

```text
pending
approved
disapproved
cancelled
```

### correction_application_details

```text
id
correction_application_id
date
am_in
am_out
pm_in
pm_out
created_at
updated_at
```

One application can have:
- multiple dates
- one AM/PM log row per date (any combination of AM In, AM Out, PM In, PM Out)

## Rules

```text
reason is required.
At least one date row is required, with no duplicate dates in the same application.
At least one log time (am_in, am_out, pm_in, or pm_out) is required per date row.
If both am_in and am_out are filled, am_out must be later than am_in.
If both pm_in and pm_out are filled, pm_out must be later than pm_in.
If both AM and PM times are filled on the same date, PM times must not be earlier than AM times.
A date cannot be filed if the employee already has a pending or approved correction application on that date.
Employee can only submit for themselves.
Admin can submit for one or multiple employees.
If approver flow is enabled, consecutive approver_setup for correction is required.
If auto-approved (allow_approver = false), status starts as approved with no approval snapshot and logs apply immediately.
```

Examples:

```text
Case 1 — Missed single punch (PM Out):
Date: 2026-11-16
AM In: —   AM Out: —
PM In: —   PM Out: 17:00
Reason: Biometric scanner was offline during dismissal

Case 2 — Missed full day / half-day punches:
Date: 2026-11-17
AM In: 08:00   AM Out: 12:00
PM In: 13:00   PM Out: 17:00
Reason: Power outage at branch office, manual DTR attached
```

## Submit Flow

```mermaid
flowchart TD
    A["Employee or Admin opens Correction of Logs"] --> B{"Is Admin?"}
    B -- "Yes" --> C["Select one or more employees and allow_approver option"]
    B -- "No" --> D["Auto-use logged-in employee_id and allow_approver = true"]
    C --> E["Enter reason and add Date + AM/PM log times (AM In, AM Out, PM In, PM Out)"]
    D --> E
    E --> F["Validate required fields, chronological AM/PM order, no duplicate dates, no pending/approved conflict"]
    F -- "Invalid" --> G["Return 422 Validation Error"]
    F -- "Valid" --> H["Save correction_applications + correction_application_details"]
    H --> I{"allow_approver = true?"}
    I -- "Yes" --> J["Check consecutive approver_setup for type 'correction'"]
    J -- "Missing or non-consecutive" --> K["Rollback and return 422 Approver Setup Error"]
    J -- "Valid" --> L["Copy steps to application_approvals and set status = pending"]
    I -- "No" --> M["Set status = approved (no approval snapshot)"]
    M --> N["Sync non-empty AM/PM timestamps to employee_logs"]
```

## Approval, Sync & Cancel Flow

```mermaid
flowchart TD
    S["Pending Correction Application"] --> A{"Who acts?"}
    A -- "Applicant cancels" --> C1["Mark pending approvals and application status = cancelled"]
    A -- "Admin overrides disapprove" --> D1["Mark application status = disapproved"]
    A -- "Current sequence approver acts" --> B{"Decision?"}
    B -- "Disapproved" --> D2["Update current step = disapproved and application status = disapproved"]
    B -- "Approved" --> E["Update current step = approved"]
    E --> F{"More pending approver steps?"}
    F -- "Yes" --> G["Wait for next sequence approver"]
    F -- "No" --> H["Update application status = approved"]
    H --> I["EmployeeCorrectionService writes non-empty AM/PM timestamps to employee_logs (source = correction)"]

    J["Already Approved Application"] --> K{"Admin disapproves?"}
    K -- "Yes" --> L["Set application status = disapproved and remove unlocked logs created by CORRECTION-APP-id"]
```

## Visibility & Access

```text
Menu code: correction_application (under Online Applications, path: /correction-applications)

View list / details:
- Admin → sees all correction applications
- Employee → sees own correction applications + correction applications where they are an assigned approver

Create:
- Employees can file their own correction of logs
- Admins can file correction of logs for one or multiple employees

Update (Approve / Disapprove / Cancel):
- Approve → current pending approver in sequence
- Disapprove → current pending approver or Admin override
- Cancel → applicant only, while status is pending
```

## Endpoints

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/api/correction-applications` | `correction_application:view` |
| GET | `/api/correction-applications/{application_id}` | `correction_application:view` |
| POST | `/api/correction-applications` | `correction_application:create` |
| POST | `/api/correction-applications/{application_id}/approve` | `correction_application:update` |
| POST | `/api/correction-applications/{application_id}/disapprove` | `correction_application:update` |
| POST | `/api/correction-applications/{application_id}/cancel` | `correction_application:update` |

## Important

```text
Register correction in Relation::morphMap so ApplicationApprovalService works automatically.
ApproverSetup::APPLICATION_TYPES already supports correction.
Support multi-status filtering and search across reason, date, employee, and approvers.
Approved correction entries sync to employee_logs via EmployeeCorrectionService so attendance processing picks them up automatically.
```
