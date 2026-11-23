# Official Business (OB) Online Application — Implementation Plan

This document outlines the full backend and frontend implementation plan for adding **Official Business (`official_business`)** under **Online Applications**, following the existing **Overtime Application** architecture and approval workflow.

---

## 1. Existing Foundation Already in Repo

- **`ApproverSetup::APPLICATION_TYPES`** (`celestia-backend/app/Models/OnlineApplication/ApproverSetup.php`) already includes `'official_business'`.
- **`ApplicationApprovalService`** (`celestia-backend/app/Services/OnlineApplication/ApplicationApprovalService.php`) supports any Eloquent model registered in `Relation::morphMap(...)` for `snapshot()`, `decide()`, and `cancel()`.
- **`EmployeeOBService.php`** (`celestia-backend/app/Services/Attendance/EmployeeOBService.php`) exists as an empty placeholder for future attendance integration.

---

## 2. Backend (`celestia-backend`)

### 2.1 Morph Map & Database Schema
1. **Morph Map Registration**
   - Update `app/Providers/AppServiceProvider.php`:
     ```php
     Relation::morphMap([
         'overtime' => OvertimeApplication::class,
         'official_business' => OfficialBusinessApplication::class,
     ]);
     ```
2. **Migration** — `database/migrations/2026_11_14_000001_create_official_business_application_tables.php`
   - `official_business_applications`:
     - `id`
     - `employee_id` (`string(30)`, FK -> `employees.employee_id`, `cascadeOnDelete`)
     - `destination` (`string(255)`) — client / site / office visited
     - `reason` (`text`) — purpose of the official business
     - `status` (`enum('pending', 'approved', 'disapproved', 'cancelled')`, default `'pending'`)
     - `submitted_at` (`timestamp`)
     - `timestamps`
     - Index: `['employee_id', 'status']`
   - `official_business_application_details`:
     - `id`
     - `official_business_application_id` (FK -> `official_business_applications.id`, `cascadeOnDelete`)
     - `date` (`date`)
     - `start_time` (`time`)
     - `end_time` (`time`)
     - `timestamps`
     - Index: `['official_business_application_id', 'date']`

### 2.2 Models & Query Trait
- `app/Models/OnlineApplication/OfficialBusinessApplication.php`
  - Relations: `employee()`, `details()`, `approvals()` (`morphMany(ApplicationApproval::class, 'application')`)
  - Uses `HasFactory` and `OfficialBusinessApplicationQuery`
- `app/Models/OnlineApplication/OfficialBusinessApplicationDetail.php`
  - Relation: `application()` (`belongsTo(OfficialBusinessApplication::class)`)
- `app/Queries/OnlineApplication/OfficialBusinessApplicationQuery.php`
  - Filter by `employee_id`, `status` (`whereIn('status', Arr::wrap($request->status))`), `date_from` / `date_to` on `submitted_at`
  - Search across `destination`, `reason`, `employee_id`, `employee` (`first_name`, `last_name`), and `approvals.approver`
  - Sorting (`id`, `employee_id`, `destination`, `status`, `submitted_at`, `created_at`) and pagination (`limit`, `offset`)

### 2.3 Validation, Service, Controller & Routes
1. **Form Request** — `app/Http/Requests/OnlineApplication/OfficialBusinessApplicationRequest.php`
   - `employee_id` (`sometimes|nullable|string|exists:employees,employee_id`)
   - `employee_ids` / `employee_ids.*` (for admin multi-employee filing)
   - `allow_approver` / `auto_approved` (`sometimes|boolean`)
   - `destination` (`required|string|max:255`)
   - `reason` (`required|string|max:5000`)
   - `details` (`required|array|list|min:1|max:100`)
   - `details.*.date` (`required|date_format:Y-m-d`)
   - `details.*.start_time` (`required|date_format:H:i`)
   - `details.*.end_time` (`required|date_format:H:i|after:details.*.start_time`)
   - Reuse existing `ApplicationDecisionRequest` for approve/disapprove/cancel remarks.
2. **Service** — `app/Services/OnlineApplication/OfficialBusinessApplicationService.php`
   - `findAll(Request $request, User $actor)` & `findById(int $id, User $actor)` with role/applicant/approver visibility scoping (mirroring `OvertimeApplicationService`)
   - `submit(array $data, User $actor)` inside `DB::transaction` with `ApplicationApprovalService::snapshot()` when `allow_approver` is true
   - `decide(int $id, User $actor, string $decision, ?string $remarks = null)`
   - `cancel(int $id, User $actor, ?string $remarks = null)`
3. **Controller** — `app/Http/Controllers/OnlineApplication/OfficialBusinessApplicationController.php`
   - `index`, `find`, `store`, `approve`, `disapprove`, `cancel`
4. **API Routes** — `routes/api.php`
   - `GET /official-business-applications` (`can:official_business,view`)
   - `GET /official-business-applications/{application_id}` (`can:official_business,view`)
   - `POST /official-business-applications` (`can:official_business,create`)
   - `POST /official-business-applications/{application_id}/approve` (`can:official_business,update`)
   - `POST /official-business-applications/{application_id}/disapprove` (`can:official_business,update`)
   - `POST /official-business-applications/{application_id}/cancel` (`can:official_business,update`)

### 2.4 Menu, Permissions & Tests
- Add `official_business` menu entry under `online_application` (`/official-business-applications`, `sort_order: 1`) in `database/seeders/MenuSeeder.php`.
- Grant default permissions in `database/seeders/AccessSeeder.php`:
  - `admin`: `view`, `create`, `update`, `delete`
  - `employee`: `view`, `create`, `update` (to cancel own pending OB applications)
- Add `database/factories/OnlineApplication/OfficialBusinessApplicationFactory.php` and `tests/Feature/OfficialBusinessApplicationTest.php` covering permissions, filing, approver workflow, admin disapproval override, cancellation, and multi-status filtering.

---

## 3. Frontend (`celestia-frontend`)

### 3.1 Types & API Client
- `src/types/official-business.ts`:
  - `OfficialBusinessStatus` (`'pending' | 'approved' | 'disapproved' | 'cancelled'`)
  - `OfficialBusinessDetail` (`id`, `date`, `start_time`, `end_time`)
  - `OfficialBusinessApplication` (`id`, `employee_id`, `destination`, `reason`, `status`, `submitted_at`, `employee`, `details`, `approvals`)
  - `OfficialBusinessFormInput` & `OfficialBusinessDetailInput`
- `src/api/official-business.ts`:
  - `getOfficialBusinessApplications`, `getOfficialBusinessApplication`, `submitOfficialBusiness`, `approveOfficialBusiness`, `disapproveOfficialBusiness`, `cancelOfficialBusiness`

### 3.2 Feature Module (`src/features/official-business/`)
- `table-config.ts` & `search-params.ts` (`status: 'text[]'` for multi-select checkboxes, `date_from`, `date_to`, `employee_id`)
- `official-business-utils.ts` (`MODULE = 'official_business'`, status badge styling, `canActOnOfficialBusiness`, duration helpers)
- `official-business-table.tsx` (DataTable with Status multi-select + Submitted date filter, destination/dates summary, quick approve/disapprove/cancel/view actions, bulk selection checkboxes)
- `official-business-bulk-action-bar.tsx` (bulk approve/disapprove bar)
- `official-business-view-modal.tsx` (applicant details, destination, reason, date/time schedule breakdown, and step-by-step approval trail)
- `form/official-business-modal-form.tsx` (Employee/multi-employee picker for admins, Destination/Location input, multi-row Date + Start/End Time editor, Purpose/Reason textarea, approver/auto-approve toggle)
- `official-business-page.tsx`

### 3.3 Routing & Navigation
- Add `src/routes/official-business-applications.tsx` (`MODULE = 'official_business'`).
- Check sidebar icon mapping (if applicable) so **Official Business** renders cleanly under **Online Applications**.

---

## 4. Open Questions / Decisions to Confirm
1. **Form Fields**: Are `destination`, `reason` (purpose), and `details` (`date`, `start_time`, `end_time`) sufficient, or should we also include extra fields (e.g. client contact person, remarks/notes, or transportation details)?
2. **Attendance Computation Integration**: Should approved Official Business automatically count toward rendered hours / excuse absences in `EmployeeProcessLogsService` (`EmployeeOBService`) in this phase, or keep this phase focused on the Official Business application + approval module first?
3. **Default Employee Permissions**: Confirm whether `employee` role should receive `view`, `create`, and `update` by default (matching `overtime_application`).
