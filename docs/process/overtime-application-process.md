# Overtime Application — Agent Prompt

Build simple OT application flow.

## Tables

### overtime_applications

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

### overtime_application_details

```text
id
overtime_application_id
date
start_time
end_time
break_seconds
requested_seconds
created_at
updated_at
```

One application can have:
- multiple dates
- multiple start/end pairs on same date
- optional break duration per detail

## Break

Store break as integer seconds.

Example:

```text
30 minutes break = 1800
1 hour break = 3600
```

UI/API may display break as:

```text
00:30
01:00
```

Do not store break start/end time.

## Rules

```text
OT only outside employee schedule.
OT period must not overlap scheduled work.
OT period must not overlap another OT detail in same application.
end_time > start_time.
break_seconds >= 0.
break_seconds must be less than OT duration.
requested_seconds = OT duration - break_seconds.
requested_seconds calculated by backend.
Rest day OT can be allowed.
```

Example:

```text
OT: 17:00-21:00
Break: 00:30

Total duration = 4 hours
Break = 30 minutes
Requested OT = 3 hours 30 minutes
```

## Submit Flow

```text
Employee creates OT
↓
Add date
↓
Add start/end time
↓
Enter optional break duration
↓
Repeat for more periods/dates
↓
Load employee schedule
↓
Validate
↓
Calculate requested_seconds
↓
Save application + details
↓
Copy approver_setup to application_approvals
↓
status = pending
```

## Approval

```text
application_type = overtime

All approved → OT approved.
Any disapproved → OT disapproved.
Employee cancels → OT cancelled.
```

## Important

```text
Use overtime_application_details.
Store break internally as seconds.
Display break as HH:MM.
Store requested OT only.
Actual rendered OT is handled during attendance processing.
Keep implementation simple.
```
