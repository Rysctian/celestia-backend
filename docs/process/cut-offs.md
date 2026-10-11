# Cut-Off API

Implements the two-table configuration in [Cut-Off process](../cut-off-process.md).
The module uses `cut_offs` and `cut_off_periods`; schedule type belongs on the
parent. There is no Quarter or separate schedule/release table.

## Files and flow

`routes/api.php` -> `CutOffRequest` / `CutOffController` -> `CutOffService` ->
`CutOff`, `CutOffPeriod`, and `CutOffQuery`.

Requests validate field shapes and date/time ranges. The service enforces exactly
period 1 for `monthly`, or periods 1 and 2 for `semi_monthly`. Parent and period
writes use a transaction; updates lock the parent and replace its periods.
Deletion cascades to periods. Reads eager-load periods ordered by `cut_off_no`.

## Routes and permissions

All routes require Sanctum authentication and the `cut_off` menu code.

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/api/cut-offs` | view |
| GET | `/api/cut-offs/{cut_off_id}` | view |
| POST | `/api/cut-offs` | create |
| PUT | `/api/cut-offs/{cut_off_id}` | update |
| DELETE | `/api/cut-offs/{cut_off_id}` | delete |

Creation returns 201; reads, updates, and deletion return 200. Reads/writes use
the existing `message`/`data` envelope. Deletion returns a message. Invalid fields
or period configuration return 422; missing records return 404. PUT expects the
full configuration and replaces all periods, including when switching schedule
type. The old `/api/payroll-cutoffs` routes are removed.

## Monthly payload

```json
{
  "schedule_type": "monthly",
  "name": "October 2026",
  "year": 2026,
  "month": 10,
  "is_active": true,
  "periods": [
    {
      "cut_off_no": 1,
      "dtr_date_from": "2026-09-26",
      "dtr_date_to": "2026-10-25",
      "no_dtr": false,
      "confirmation_start_date": "2026-10-26",
      "confirmation_end_date": "2026-10-27",
      "confirmation_time_from": "08:00",
      "confirmation_time_to": "17:00",
      "payroll_date_from": "2026-10-01",
      "payroll_date_to": "2026-10-31",
      "release_date": "2026-11-05"
    }
  ]
}
```

For `semi_monthly`, supply two complete period objects with `cut_off_no` 1 and 2.
The values are named 1st Cut-Off and 2nd Cut-Off. Each can have its own DTR,
confirmation, payroll, and release dates. The two date ranges are independent and
can cross calendar months; year/month label the configuration.

Names are required and limited to 100 characters. Year is 1900-9999; month is
1-12. `is_active` defaults to true on creation and remains unchanged on update
when omitted. Dates use `YYYY-MM-DD`. Input confirmation times use `HH:mm` and
responses use `HH:mm:ss`. Every date range must end on or after its start.
Confirmation's end date/time must not precede its start date/time. Release cannot
precede the payroll end date.

For a `no_dtr=true` period, DTR dates and confirmation fields may be omitted;
submitted DTR/confirmation values are excluded and stored as null. Payroll dates
and release date remain required. `no_dtr` is per period, so the other period can
still require attendance. Re-enabling DTR requires the complete DTR configuration.

This API configures the period ranges and skip flag for attendance/payroll
consumers. It does not introduce payroll calculation, release execution, or
attendance confirmation/locking endpoints; those operations require the
corresponding processing modules and their own persistence contracts.

## List contract

`search` matches name. Exact filters are `schedule_type`, `year`, `month`, and
`is_active`. Inactive values `false` and `0` are supported. `date_from` / `date_to`
bound the parent's `created_at`, rather than either period range.

Allowed sort fields: `id`, `schedule_type`, `name`, `year`, `month`, `is_active`,
and `created_at`. Defaults are `id` descending, limit 15, offset 0. Limit is
clamped to 1-100 and offset is nonnegative. Sorting adds an ID tie-breaker. Lists
return arrays with nested periods and no invented pagination totals.

## Database replacement

The old payroll migration files, classes, factories, and seeder are removed.
The module has one creation migration:
`2026_10_11_000001_create_cut_off_tables`. Menu and access seeders define Cut-Off
directly; no legacy menu conversion or table cleanup migrations are retained.

The supported clean setup is:

```shell
php artisan migrate:fresh --seed
```

This rebuilds the schema and runs `DatabaseSeeder`, which calls `CutOffSeeder`.
It creates a semi-monthly sample parent and calls `CutOffPeriodSeeder` to create
the 1st and 2nd Cut-Off periods. The menu catalog seeds the `cut_off` entry and
administrator grants. The retired payroll tables and menu are absent.

`CutOffFactory::withPeriods()` creates a monthly period;
`semiMonthly()->withPeriods()` creates two periods. `CutOffSeeder` creates a sample
only when no Cut-Off records exist. `CutOffPeriodSeeder` fills missing period
numbers using `CutOffPeriodFactory::forCutOff()` without replacing existing
period settings. Both seeders can be rerun without duplicating Cut-Off data.
