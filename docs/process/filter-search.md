# List search, filters, sorting, and pagination

Frontend handoff for `GET /api/schedules`, `GET /api/users`, and `GET /api/roles`.
These endpoints now use `scopeFilterSearch` traits in `app/Queries`, following
`EmployeeQuery`: grouped text search, exact filters, creation date filters,
an allowed sort list, and bounded `limit` / `offset`.

## Shared query parameters

All parameters are optional. Send them in the URL query string.

| Parameter | Behavior / default |
| --- | --- |
| `search` | Partial text match across the endpoint's search fields; matches any field using SQL `LIKE`. Case sensitivity follows the database collation. |
| `date_from` | Inclusive earliest `created_at` date; send `YYYY-MM-DD`. |
| `date_to` | Inclusive latest `created_at` date; send `YYYY-MM-DD`. |
| `sort_by` | One of the endpoint's allowed fields below; missing or unsupported values fall back to `created_at`. |
| `sort_order` | `asc` or `desc`; defaults to `desc`. Only the exact value `asc` selects ascending order. |
| `limit` | Default `15`; converted to an integer and clamped to `1`–`100`. |
| `offset` | Default `0`; converted to an integer and clamped to at least `0`. |

Text search is grouped before combining it with other filters. All supplied
filters must match. Omit unused parameters or send empty values to skip filters.
As in Employees, query inputs are read directly rather than returning validation
errors for unsupported sort fields or out-of-range pagination values. Send valid
dates, numeric IDs, and the boolean values documented below.

## Endpoint fields

| Endpoint | `search` matches | Exact filters | Allowed `sort_by` |
| --- | --- | --- | --- |
| `/api/schedules` | `name`, `timezone` | `name`, `timezone`, `is_active` | `id`, `name`, `timezone`, `is_active`, `created_at` |
| `/api/users` | `name`, `email`, `employee_id` | `employee_id`, `role_id`, `email` | `id`, `employee_id`, `name`, `email`, `role_id`, `created_at` |
| `/api/roles` | `name`, `code` | `name`, `code` | `id`, `code`, `name`, `created_at` |

For schedules, send `is_active=1` / `true` for active or `is_active=0` / `false`
for inactive. Omitting `is_active` returns both. Inactive filtering works even
when the supplied value is `0`. Date filters refer to the template's creation
date, not employee assignment dates or working days.

For users, `role_id` is a numeric role ID and `employee_id` is the business ID,
such as `EMP-20260001`. Search uses user fields, not nested role names or employee
profile fields. Filter by the selected role using `role_id`.

## Responses and frontend pagination

The response envelope is unchanged:

```json
{
  "message": "success",
  "data": []
}
```

`data` is an array, with no `total`, `current_page`, `last_page`, or pagination
links. Schedule rows still include `details`; user rows still contain `id`,
`employee_id`, `name`, `email`, `role_id`, and nested `role` (`id`, `code`, `name`).
Passwords and tokens are not returned. Role rows retain their existing fields.

**Behavior change:** these three lists previously returned every row in ascending
ID order. They now default to at most 15 rows ordered by `created_at desc`, matching
Employees. Pass `sort_by=id&sort_order=asc` when ascending ID order is desired.
For predictable paging when timestamps are equal, use the unique `id` sort.

Use `offset = (page - 1) * limit`. Reset `offset` to `0` when search, filters,
sort, or page size changes. A response shorter than `limit` signals the end;
a full page may require another request to determine whether more rows exist.
Role dropdowns must fetch additional batches when more than 15 roles exist
(maximum batch size is 100).

```text
GET /api/schedules?search=Office&is_active=1&sort_by=name&sort_order=asc&limit=15&offset=0
GET /api/schedules?is_active=0&timezone=Asia%2FSingapore
GET /api/users?search=alex&role_id=2&sort_by=id&sort_order=asc&limit=25&offset=25
GET /api/roles?search=manager&sort_by=name&sort_order=asc
GET /api/roles?date_from=2026-10-01&date_to=2026-10-31
```

Authentication and grants are unchanged: Sanctum is required; schedules require
Schedule List `view`, while users and roles require User Management `view`.
