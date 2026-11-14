# Official Business Application — Backend Process

Build the Official Business (OB) online application flow, following the approval and detail structure used by Overtime Application.

## Tables

### official_business_applications

```text
id
employee_id
ob_type
destination
reason
status
submitted_at
created_at
updated_at
```

OB Type:

```text
ob   (Official Business)
wfh  (Work From Home)
```

Status:

```text
pending
approved
disapproved
cancelled
```

### official_business_application_details

```text
id
official_business_application_id
date
start_time
end_time
created_at
updated_at
```

One application can have:
- multiple dates
- multiple start/end pairs on the same date

## Rules

```text
ob_type is required (ob or wfh).
destination is required when ob_type = ob (optional when ob_type = wfh).
reason is required.
end_time > start_time for each detail.
OB periods in the same application must not overlap.
OB period must not overlap an existing pending or approved OB of the same employee.
Employee can only submit for themselves.
Admin can submit for one or multiple employees.
If approver flow is enabled, consecutive approver_setup for official_business is required.
If auto-approved (allow_approver = false), status starts as approved with no approval snapshot.
```

Examples:

```text
Type: OB (Official Business)
Destination: Client Office - Makati
Purpose: System deployment and user training
Date: 2026-11-16
Time: 09:00 - 17:00

Type: WFH (Work From Home)
Destination: (optional)
Purpose: Sprint feature development and testing
Date: 2026-11-17
Time: 08:00 - 17:00
```

## Submit Flow

```text
Employee (or Admin) creates Official Business
↓
Select employee(s) (Admin only; employees auto-use their own ID)
↓
Select OB type (OB or WFH)
↓
Enter destination (required for OB, optional for WFH) and purpose / reason
↓
Add date and start/end time
↓
Repeat for additional dates or time ranges if needed
↓
Validate required fields, time order, and overlapping periods
↓
Save official_business_applications + official_business_application_details
↓
Check allow_approver flag
├── Yes → Copy approver_setup (official_business) to application_approvals → status = pending
└── No  → Skip approval snapshot → status = approved
```

## Approval & Cancel Flow

```text
application_type = official_business

Approver Level 1 acts
├── Approved → Move to next approver sequence (if any)
└── Disapproved → Application status = disapproved (flow stops)

All approver sequences approved → Application status = approved
Admin disapproves pending/approved OB → Application status = disapproved
Applicant cancels pending OB → Pending approvals & application status = cancelled
```

## Visibility & Access

```text
Menu code: official_business (under Online Applications)

View list / details:
- Admin → sees all OB applications
- Employee → sees own OB applications + OB applications where they are an assigned approver

Create:
- Employees can file their own OB
- Admins can file OB for one or multiple employees

Update (Approve / Disapprove / Cancel):
- Approve → current pending approver in sequence
- Disapprove → current pending approver or Admin override
- Cancel → applicant only, while status is pending
```

## Endpoints

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/api/official-business-applications` | `official_business:view` |
| GET | `/api/official-business-applications/{application_id}` | `official_business:view` |
| POST | `/api/official-business-applications` | `official_business:create` |
| POST | `/api/official-business-applications/{application_id}/approve` | `official_business:update` |
| POST | `/api/official-business-applications/{application_id}/disapprove` | `official_business:update` |
| POST | `/api/official-business-applications/{application_id}/cancel` | `official_business:update` |

## Important

```text
Register official_business in Relation::morphMap so ApplicationApprovalService works automatically.
ApproverSetup::APPLICATION_TYPES already supports official_business.
Support multi-status and ob_type (ob, wfh) filtering, plus search across destination, reason, employee, and approvers.
Attendance credit / time-log impact for approved OB is handled separately inside EmployeeOBService during attendance processing.
```
