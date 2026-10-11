# Backend review findings

These observations were moved out of [the coding guidelines](BACKEND_CODING_GUIDELINES.md)
so temporary defects and module-specific limitations remain separate from reusable
coding conventions. They describe the backend reviewed on 2026-10-11, including
existing working-tree changes. They are observations from code, not fixes or new
standards; verify them against the current implementation before using affected files.

## Employee writes

References: [EmployeeRequest](../app/Http/Requests/EmployeeRequest.php),
[EmployeeService](../app/Services/EmployeeService.php),
[EmployeeController](../app/Http/Controllers/EmployeeController.php),
[Employee](../app/Models/Employee.php),
[employee migration](../database/migrations/2026_09_29_134804_create_employees_table.php).

- `EmployeeRequest` has GET/POST rules but no PUT rules, although the update route uses it.
- `createEmployee` creates an employee and a user without a transaction. ID generation
  reads the latest employee row without a lock; concurrent creation can choose the
  same sequence. The helper name `generateEmployeId` also contains a spelling error.
- `updateUserAndEmployee` calls `findById`, which returns an Employee, instead of
  looking up a User. Its subsequent name/email update therefore targets the wrong model.
- `civil_status` exists in request rules and schema but is absent from Employee's `$fillable`.
- The update action returns 201. Its failure branch puts `401` inside the JSON array
  instead of passing it as the HTTP status argument.

## Delete permissions and identifiers

References: [API routes](../routes/api.php), [User](../app/Models/User.php),
[RoleRequest](../app/Http/Requests/RoleRequest.php), [RoleController](../app/Http/Controllers/RoleController.php).

- Role DELETE routes pass `destroy` to menu middleware, while
  `User::hasMenuAccess` only accepts `view`, `create`, `update`, and `delete`.
  This action mismatch denies those requests.
- The replaced Cut-Off DELETE route uses `delete`; the old payroll route is removed.
- Role deletion validates and uses a body `id`, despite the route containing `{role_id}`.

## Schedule authorization and schema

References: [ScheduleRequest](../app/Http/Requests/ScheduleRequest.php),
[AppServiceProvider](../app/Providers/AppServiceProvider.php),
[ScheduleDetail](../app/Models/ScheduleDetail.php), [ScheduleHelper](../app/Helpers/ScheduleHelper.php),
[schedule-details migration](../database/migrations/2026_10_01_000002_create_schedule_details_table.php).

- `ScheduleRequest` checks `manage-schedules` for creation and update, but that gate
  tests update permission. A create-only grant can pass route middleware and fail the request.
- `unpaid_break_minutes` appears in request/model/helper code but is absent from
  the schedule-details migration.
- `tardy_start`, `undertime_start`, and `early_dismissal_start` exist in that migration
  but are absent from ScheduleDetail's `$fillable` and ScheduleRequest's field rules.

## Incomplete implementations and namespace mismatches

- [EmployeeLogController](../app/Http/Controllers/Attendance/EmployeeLogController.php)
  references missing `AttendanceReadRequest`, `EmployeeLogRequest`, and
  `EmployeeLogService`. It calls `filterSearch`, but [EmployeeLog](../app/Models/EmployeeLog.php)
  does not compose that scope, and the controller is not registered in API routes.
- [EmployeeLeaveService](../app/Services/Attendance/EmployeeLeaveService.php),
  [EmployeeOBService](../app/Services/Attendance/EmployeeOBService.php), and
  [EmployeeCorrectionService](../app/Services/Attendance/EmployeeCorrectionService.php)
  are empty files. [EmployeeAttendance](../app/Models/EmployeeAttendance.php) has an
  empty `getAttendanceBetweenDates` method.
- [OvertimeApplicationController](../app/Http/Controllers/OnlineApplication/OvertimeApplicationController.php)
  has empty actions and a namespace that omits its `OnlineApplication` folder.
  [OvertimeAppService](../app/Services/OvertimeAppService.php) is a stub whose declared
  `App\Services\OnlineApplication` namespace does not match its path or the controller's import.
- [UserSeeder](../database/seeders/UserSeeder.php) declares a duplicate
  `Database\Factories\UserFactory`, rather than a seeder matching its path.
  These mismatches are not examples of the intended namespace convention.

## Attendance calculation limits

References: [EmployeeAttendanceService](../app/Services/Attendance/EmployeeAttendanceService.php),
[AttendanceProcess](../app/Pipelines/Attendance/AttendanceProcess.php),
[EmployeeProcessLogsService](../app/Services/Attendance/EmployeeProcessLogsService.php),
[AttendanceCalculator](../app/Services/Attendance/AttendanceCalculator.php),
[attendance configuration](../config/attendance.php).

- The routed attendance read flow calculates results through a single pipeline
  stage and flattens working slots into rows; it does not persist derived attendance.
  This is a module-specific implementation, not a general CRUD pipeline convention.
- `ScheduleHelper` uses schedule timezones and overnight rollover. The calculator
  parses same-day start/end timestamps without supplying the schedule timezone;
  it does not share those behaviors or subtract the helper's unpaid-break value.
- Existing configuration and [AttendanceDuration](../app/Helpers/AttendanceDuration.php)
  utilities do not establish that duplicate, night, approval, or exception rules
  are integrated into the current calculator.

## Variations that are not universal conventions

- Local variables/parameters mix camelCase and snake_case. Return types appear more
  consistently in requests/helpers/calculators than in CRUD controllers/services.
  Follow the surrounding implemented module rather than asserting one uniform style.
- [EmployeeScheduleQuery](../app/Queries/EmployeeScheduleQuery.php) and
  [AttendanceQuery](../app/Queries/AttendanceQuery.php) add `id` tie-breakers;
  several other filtering traits do not. Sort defaults and date-column meanings
  also differ by module; preserve the endpoint's intended behavior.
- Several domain seeders preserve existing data, but
  [DatabaseSeeder](../database/seeders/DatabaseSeeder.php) creates a new employee/user
  batch each run. Do not assume every seeder is repeatable without adding records.

## Tests and documentation drift

- [AttendanceTest](../tests/Feature/AttendanceTest.php) refers to missing routes and
  components, including `/api/employee-attendance/process`, attendance-cutoff routes,
  `Timesheet`, and `EmployeeLogService`. It describes behavior absent from the current code.
- [AuthenticationTest](../tests/Feature/AuthenticationTest.php) expects an email-field
  validation error for incorrect passwords, while [LoginRequest](../app/Http/Requests/LoginRequest.php)
  returns a password-field error.
- [The runtime upgrade report](../docs/runtime-upgrade.md) records existing attendance,
  authentication, and payroll-delete test failures and namespace/path warnings.
  Those are prior results; the documentation revision did not rerun the suite.

Compare module documentation and tests with current routes, classes, and schema
before treating them as implementation evidence. No application code was changed
as part of moving or documenting these findings.
