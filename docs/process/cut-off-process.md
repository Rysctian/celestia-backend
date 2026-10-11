# Cut-Off Module — Simple 2-Table Design

Use a simple 2-table structure for the Cut-Off module.

## 1. cut_offs

Purpose:

Store the main Cut-Off configuration.

Fields:

```text
id
schedule_type
name
year
month
is_active
created_at
updated_at
```

`schedule_type` fixed values:

```text
monthly
semi_monthly
```

Example:

```text
October 2026
Schedule: Semi-Monthly
```

## 2. cut_off_periods

Purpose:

Store the actual 1st / 2nd Cut-Off periods.

Fields:

```text
id
cut_off_id
cut_off_no

dtr_date_from
dtr_date_to
no_dtr

confirmation_start_date
confirmation_end_date
confirmation_time_from
confirmation_time_to

payroll_date_from
payroll_date_to
release_date

created_at
updated_at
```

`cut_off_no`:

```text
1 = 1st Cut-Off
2 = 2nd Cut-Off
```

## Relationship

```text
cut_offs
   ↓
cut_off_periods
```

One Cut-Off has many Cut-Off periods.

## Monthly

Monthly only needs one period.

```text
October 2026
└── 1st Cut-Off
    ├── DTR Cut-Off
    └── Payroll Cut-Off
```

## Semi-Monthly

Semi-Monthly needs two periods.

```text
October 2026
├── 1st Cut-Off
│   ├── DTR Cut-Off
│   └── Payroll Cut-Off
│
└── 2nd Cut-Off
    ├── DTR Cut-Off
    └── Payroll Cut-Off
```

## DTR Cut-Off

Used for attendance processing.

Fields:

```text
dtr_date_from
dtr_date_to
no_dtr
confirmation_start_date
confirmation_end_date
confirmation_time_from
confirmation_time_to
```

If `no_dtr` is enabled:

```text
skip DTR processing for that period
```

## Payroll Cut-Off

Used for payroll processing.

Fields:

```text
payroll_date_from
payroll_date_to
release_date
```

## Process

```text
Create Cut-Off
↓
Select Monthly or Semi-Monthly
↓
Create 1 or 2 Cut-Off periods
↓
Configure DTR Cut-Off
↓
Configure Payroll Cut-Off
↓
Save
```

Attendance:

```text
Find Cut-Off period
↓
Use DTR date range
↓
Process attendance
↓
DTR confirmation
↓
Confirm / lock attendance
```

Payroll:

```text
Confirmed attendance
↓
Find Cut-Off period
↓
Use payroll date range
↓
Compute payroll
↓
Release on release date
```

## Naming

Main module:

```text
Cut-Off
```

Tables:

```text
cut_offs
cut_off_periods
```

Do not create a separate schedule table.

Keep `schedule_type` directly in `cut_offs`.

Do not use `Quarter`.

Use:

```text
1st Cut-Off
2nd Cut-Off
```

Keep the implementation simple and follow the existing Celestia HRIS UI/UX and coding conventions.
