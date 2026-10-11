# Approver Setup — Agent Prompt

Build one generic approval process for:

```text
OT
OB
Correction
Leave
```

## approver_setup

Template only.

```text
id
employee_id
application_type
approver_id
sequence
created_at
updated_at
```

Rules:

```text
1 or more approvers
sequence starts at 1
no duplicate sequence for same employee + application type
```

## application_approvals

Snapshot for an actual submitted application.

```text
id
employee_id
application_type
application_id
approver_id
sequence
status
remarks
acted_at
created_at
updated_at
```

Statuses only:

```text
pending
approved
disapproved
cancelled
```

## Setup Flow

```text
Select employee
↓
Select application type
↓
Add approver(s)
↓
Set sequence
↓
Save
```

## Submit Flow

```text
Employee submits application
↓
Find approver_setup
↓
Copy approvers to application_approvals
↓
All copied rows = pending
```

Important:

```text
Copy setup at submission time.
Old applications must not change when approver_setup changes.
```

## Approval Flow

Only the current sequence can act.

Current approver = first pending sequence where all previous sequences are approved.

```text
Sequence 1 approves
↓
Sequence 2 can act
↓
Sequence 3 can act
↓
All approved
↓
Application = approved
```

## Disapprove Flow

```text
Current approver disapproves
↓
Approval row = disapproved
↓
Application = disapproved
↓
Stop
```

## Cancel Flow

```text
Application cancelled
↓
Application = cancelled
↓
Remaining pending approval rows = cancelled
↓
Stop
```

## Rules

```text
No waiting status.
Do not skip sequence.
Keep remarks optional.
Set acted_at when approved, disapproved, or cancelled.
Use transaction for approval updates.
Reuse same approval process for OT, OB, Correction, and Leave.
Keep implementation simple.
