# Backend coding guidelines

Use this reference for new features and major backend changes in Celestia.
Follow the closest implemented module, preserve its public contracts, and keep
changes scoped. See [review findings](BACKEND_REVIEW_FINDINGS.md) before reusing
code with known issues. All implementation links are relative to this document.

## 1. Organization and responsibility

| Location | Put this responsibility here | Reference |
| --- | --- | --- |
| `routes/api.php` | Explicit routes, authentication groups, menu permissions | [API routes](../routes/api.php) |
| `app/Http/Controllers/` | Input handoff, operation delegation, HTTP responses | [PayrollCutoffController](../app/Http/Controllers/PayrollCutoffController.php) |
| `app/Http/Requests/` | FormRequest field rules and request authorization | [EmployeeScheduleRequest](../app/Http/Requests/EmployeeScheduleRequest.php) |
| `app/Services/` | Business checks, related writes, orchestration, output data | [PayrollCutoffService](../app/Services/PayrollCutoffService.php) |
| `app/Queries/` | Traits exposing reusable Eloquent filtering scopes | [ScheduleQuery](../app/Queries/ScheduleQuery.php) |
| `app/Models/` | Fillable fields, casts, relationships, focused model scopes | [EmployeeSchedule](../app/Models/EmployeeSchedule.php) |
| `app/Helpers/` | Focused static utilities reused by services | [DateHelper](../app/Helpers/DateHelper.php) |
| `app/Http/Middleware/`, `app/Providers/` | Access checks, aliases, gates, framework setup | [EnsureMenuAccess](../app/Http/Middleware/EnsureMenuAccess.php) |
| `database/`, `tests/Feature/` | Schema, factories, seed data, behavior verification | [PayrollCutoffTest](../tests/Feature/PayrollCutoffTest.php) |

Keep folders shallow. Most services, models, requests, and query traits live
straight under their type folder; follow existing domain subfolders when relevant.
Use the recurring flow **route -> FormRequest/controller -> concrete service ->
Eloquent model/query trait** for operations with business logic or related writes.
Simple role/user actions also use Eloquent directly in controllers; keep that
option for trivial operations. Reuse existing components before adding files;
avoid introducing repository, interface, DTO, or response-wrapper layers.

## 2. Naming and method style

- Match namespaces to paths under the `App\` mapping in [composer.json](../composer.json).
  Use PascalCase domain classes with `Controller`, `Request`, `Service`, `Query`,
  `Factory`, or `Seeder` suffixes.
- Use camelCase methods such as `findById`, `createPayrollCutoff`, and `updateAssignment`.
  Controller actions commonly use `index`, `find`, `store`, `update`, and `destroy`;
  follow `show` and route model binding where the module already uses them.
- Use snake_case for database fields, request keys, and route placeholders;
  kebab-case for URL resources. Match adjacent variable names and signatures.
- Follow [.editorconfig](../.editorconfig): UTF-8, LF, four spaces, final newline.
  Put ordinary method braces on separate lines; retain the local constructor style.
- Import dependencies with `use` and inject concrete services through constructors,
  commonly with property promotion. Keep internal operation steps in private methods.
- Use guard clauses, direct returns, explicit arrays, and readable query/collection
  chains. Use arrow functions for simple callbacks and closures/loops for multiple steps.
- Explain business rules or ordering decisions in comments. Follow surrounding type
  declarations; FormRequests use `authorize(): bool` and `rules(): array`.

## 3. Controllers and response contracts

Keep actions focused on validated input, service delegation, and HTTP results.
Pass write payloads through `$request->validated()`. Supply the authenticated actor
separately when needed, as [EmployeeScheduleController](../app/Http/Controllers/Schedule/EmployeeScheduleController.php)
passes `$request->user()->id` to assignment creation.

Actual action from `PayrollCutoffController`:

```php
public function store(PayrollCutoffRequest $request)
{
    $payrollCutoff = $this->payrollCutoffService->createPayrollCutoff($request->validated());

    return response()->json(['message' => 'Payroll cut-off created successfully.', 'data' => $payrollCutoff], 201);
}
```

- Return the existing `message`/`data` JSON envelope: `success` for common reads and
  descriptive messages for writes. Use 201 for creation and 200 for reads/updates.
- Preserve endpoint-specific response shapes, selected fields, and status codes.
  [AuthenticationController](../app/Http/Controllers/Auth/AuthenticationController.php)
  demonstrates message-only login, a `data` user response, and 204 logout.
- Follow the module's missing-record handling: nullable service reads plus explicit
  JSON 404, or `findOrFail`/`firstOrFail` and framework handling. Use framework
  validation errors and 403 access denials instead of custom error abstractions.

## 4. FormRequests and authorization

- Put field validation in FormRequests using arrays of rules: `exists`, `distinct`,
  `Rule::in`, date formats, and wildcard rules for nested data. Limit allowed nested
  keys where needed, as [PayrollCutoffRequest](../app/Http/Requests/PayrollCutoffRequest.php) does.
- For requests shared across operations, dispatch by HTTP method into small rule
  methods, as `EmployeeScheduleRequest` does. Cover every supported write method;
  single-operation requests can return their rules directly.
- Use `after()` for cross-field checks and skip value parsing when earlier errors
  exist. Use `passedValidation()` for validated-payload checks where appropriate,
  as [RoleAccessRequest](../app/Http/Requests/RoleAccessRequest.php) does.
- Keep checks requiring current database state in the service's protected operation.
  Report business validation with `ValidationException::withMessages(['field' => '...'])`.
- Put protected routes under `auth:sanctum`. [bootstrap/app.php](../bootstrap/app.php)
  aliases `can` to `EnsureMenuAccess`, which calls `User::hasMenuAccess($code, $action)`.
  Use menu codes with actions `view`, `create`, `update`, and `delete`.
- Keep route permissions, request `authorize()` checks, gates, and ownership checks
  consistent. Return `true` from `authorize()` only when the route supplies the
  required access control; extend menu/grant seeders when adding permissions.

## 5. Services and persistence

- Expose domain operations accepting validated arrays, identifiers, and actor IDs.
  Return models, collections, or shaped arrays; let controllers build HTTP responses.
- Wrap related writes in `DB::transaction`, following payroll/schedule services.
  Separate child payloads from parent attributes and write children through relationships.
- Preserve update semantics. Existing release/detail replacement uses relationship
  `delete()` and `createMany()` inside the transaction.
- Use `lockForUpdate()` inside transactions where competing writes require it.
  Follow [EmployeeScheduleService](../app/Services/EmployeeScheduleService.php) for stable
  lock ordering and state checks protected by those locks.
- Eager-load needed relationships with `with`, `load`, or `loadMissing`; retain
  linking keys when selecting columns. Use `exists()` or `value()` for narrow reads.
- Shape reusable output in services with arrays/collections. After filtering lists,
  use `values()->all()` when sequential indices are required, as
  [MenuAccessService](../app/Services/MenuAccessService.php) does.

Actual method from `PayrollCutoffService`:

```php
public function createPayrollCutoff(array $data)
{
    return DB::transaction(function () use ($data) {
        $releases = $data['releases'];
        unset($data['releases']);

        $payrollCutoff = PayrollCutoff::create($data);
        $payrollCutoff->releases()->createMany($releases);

        return $payrollCutoff->load('releases');
    });
}
```

## 6. Query traits and lists

- Compose `app/Queries/*Query.php` traits into models with `use`.
  Implement `scopeFilterSearch($query, $request)` to return the builder; callers
  finish with `->get()`. Reuse focused model scopes for narrower reads.
- Allowlist sort columns, normalize direction, and retain the module's fallback.
  Common paging uses a default limit of 15, clamps it to 1-100, and clamps offset
  to nonnegative values. Preserve the existing list shape without paginator metadata.
- Combine filters with `when()`. Group search `orWhere()` calls inside one `where()`
  closure so other constraints still apply. Reuse `whereHas()` for related filters.
- Apply record/employee constraints before shared filters. Keep date-filter meaning,
  date boundaries, units, and ordering consistent with the endpoint being extended.
- Use `filled()` and `boolean()` so explicit false values remain filterable.
  Actual fragment from `ScheduleQuery`:

```php
->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
```

## 7. Models, migrations, and reusable setup

- Declare writable fields in `$fillable` and align them with validation and schema.
  Use `casts(): array` for relevant booleans, integers, arrays, and dates; follow the
  module's date casts. Preserve `User`'s hidden credentials and `hashed` password cast.
- Keep relationships on models. Distinguish string `employee_id` business keys from
  numeric model IDs and supply explicit keys where needed. From `EmployeeSchedule`:

```php
public function employee()
{
    return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
}
```

- Keep intrinsic ordering on relationships and specify `$table` for table-name exceptions.
- Use timestamped anonymous migrations with `up(): void` and `down(): void`.
  Align types, lengths, nullability, defaults, indexes, unique constraints, foreign
  keys, and deletion behavior with the actual relationship and data contract.
- Reuse factories and named states such as `forEmployee`, `withWeek`, and `withReleases`.
  Keep related foreign keys consistent; see
  [EmployeeAttendanceLogFactory](../database/factories/EmployeeAttendanceLogFactory.php).
- Follow domain seeders' `firstOrCreate`, `insertOrIgnore`, or existence checks when
  preserving existing data. Resolve actual database identifiers and reuse services
  for seeded business operations; see [EmployeeScheduleSeeder](../database/seeders/EmployeeScheduleSeeder.php).

## 8. Development and verification habits

Inspect the closest implemented controller/request/service/model/query before
changing a module. Reuse focused helpers for shared computations and private
methods for local steps; add only the abstraction needed by the feature.

Follow feature tests using `Tests\TestCase`, linked factories, `RefreshDatabase`
(or the module's `DatabaseMigrations`), and `Sanctum::actingAs`. Reuse payload/login
helpers and freeze or travel time for date-dependent behavior. Verify changed
validation, permission boundaries, JSON contracts, and database/transaction effects.
Run relevant checks from `backend`; `composer test` uses the in-memory SQLite
configuration in [phpunit.xml](../phpunit.xml). For major changes, check the affected
routes, request rules, services, model attributes, schema, and tests together.
