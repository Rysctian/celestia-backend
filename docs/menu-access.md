# Menus and role access

Each user has one role. The `menus` table contains top-level items and one level of
children; stable `code` values connect child menus to backend actions. The
`role_menu_access` table stores `can_view`, `can_create`, `can_update`, and
`can_delete`. A missing row denies every action. `can_view` is required when
granting any other action. A parent menu appears when it has a visible child.

The migration creates and backfills Admin and Employee roles, then seeds the menu
catalog and starting grants. Existing users linked to an employee with
`type = admin` become Admin; everyone else becomes Employee. New users default to
Employee. `employee.type` remains available but no longer grants API access.
The menu seeder can be run again without changing edited grants:

```shell
php artisan db:seed --class=MenuAccessSeeder
```

Admin initially has every action on all seeded menu items. Employee can see
Dashboard and Employee 201 File and can read their own schedule through their
Employee 201 access. The seeded menu catalog includes Schedule List under Daily
Time Record, Employee 201 File under Employee Database, and User Management under
Administration. Only working pages are included. The frontend sidebar is still
hard-coded and needs separate integration with `GET /api/me/menus`.

## API

`GET /api/users` and `GET /api/roles` support search, filters, sorting, and
`limit` / `offset` pagination, defaulting to 15 rows ordered by `created_at desc`.
See the [frontend query guide](filter-search.md) before integrating lists or role
dropdowns.

All routes require Sanctum authentication. Responses use `message` and `data`.
The management routes require User Management `view`, `create`, or `update` access.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/me/menus` | Active, visible menu tree with action flags |
| GET | `/api/menus` | Complete menu catalog for management |
| GET | `/api/roles` | List roles |
| POST | `/api/roles` | Create a role with `code` and `name` |
| PUT | `/api/roles/{role}` | Change a role's `name`; codes stay stable |
| GET | `/api/roles/{role}/access` | List the role's grants |
| PUT | `/api/roles/{role}/access` | Replace all grants |
| GET | `/api/users` | List users and roles |
| GET | `/api/users/{user}` | Read one user and role |
| PUT | `/api/users/{user}/role` | Assign `role_id` |

Grant replacement accepts `{"access": [{"menu_id": 3, "can_view": true,
"can_create": false, "can_update": false, "can_delete": false}]}`. Send an
empty `access` array to revoke all grants. Only active leaf menus accept grants.
Role changes and grant changes take effect on the next request. Admin grants are
editable, including User Management access; restoring accidentally removed
management access requires database access.

Employee read/create/update endpoints use Employee 201 File grants. Schedule
template reads use Schedule List `view`; template and assignment writes use its
`create` or `update` action. Users may read their own schedule with Employee 201
`view`; reading another employee's schedule requires Schedule List `view`.
The `delete` flag is reserved for future endpoints.
