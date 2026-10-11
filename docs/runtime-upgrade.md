# PHP and Laravel upgrade verification

Verified on 2026-10-11.

## Versions

| Component | Before | After |
| --- | --- | --- |
| PHP CLI used by the backend | 8.2.12 | 8.5.11 |
| Laravel | 12.69.3 | 13.35.0 |
| Composer used with PHP 8.5 | 2.8.6 | 2.10.3 |
| Laravel Tinker | 2.11.1 | 3.0.2 |
| PHPUnit | 11.5.56 | 12.5.38 |

PHP 8.5.11 was already installed in `C:\xampp\php\php85`, and this directory
was already first in the saved Windows machine PATH. Existing terminals inherited
an older PATH and still resolved PHP to `C:\xampp\php\php.exe`. Open a fresh
terminal, or prepend the PHP 8.5 directory to the current session's PATH as
documented in the backend README.

The existing PHP binary matches the official Windows download, whose archive
SHA-256 checksum was verified. Its configuration now enables `pdo_sqlite`,
`sqlite3`, and `zip`, uses an absolute extension directory, and allows 512 MB of
memory. The previous configuration is backed up beside `php.ini` as
`php.ini.celestia-upgrade-20261011.bak`.

Composer 2.10.3 was installed beside PHP 8.5 using the official Composer installer,
whose SHA-384 checksum was verified. `composer.bat` in that directory explicitly
uses the adjacent PHP binary. The protected Composer installation in
`C:\ProgramData\ComposerSetup\bin` remains at its previous version.

The application now requires PHP `^8.5` and Laravel `^13.0`, and its lock file
contains the resolved stable dependencies. Sanctum uses Laravel 13's
`PreventRequestForgery` middleware. XAMPP Apache retains its separate runtime;
the verified backend runs through `php artisan serve`.

## Checks

- Composer dependency update and Laravel package discovery completed.
- `composer validate --no-check-publish` passed.
- `composer check-platform-reqs` passed.
- Composer reported no security vulnerability advisories during the update.
- `php artisan migrate:status` connected to the existing MySQL database on port
  3307; all 22 migrations were already applied. No migrations or seeders were run
  against the live database.
- The complete test suite ran before and after the upgrade. Both runs produced
  **48 passing tests, 29 failing tests, and 381 assertions**.

HTTP checks against the running PHP 8.5 server at `http://127.0.0.1:8000`:

| Request | Result |
| --- | --- |
| `GET /` | 200, reports Laravel 13.35.0 |
| `GET /up` | 200 |
| `GET /sanctum/csrf-cookie` | 204 |
| `POST /api/login` without a CSRF token | 419 |
| `GET /api/me` without authentication | 401 |
| `GET /api/employees` without authentication | 401 |
| `GET /docs/api.json` | 200, OpenAPI 3.1.0 |

## Existing failures

The upgrade did not introduce additional failures in the existing suite. The
suite is not fully passing:

- **25 attendance tests** reference absent routes, services, models, or seeders,
  including `EmployeeLogService` and `/api/employee-attendance/process`.
- **3 authentication tests** fail on the incorrect-password validation field,
  session logout, and CSRF enforcement for logout.
- **1 payroll-cutoff test** receives 403 when an administrator attempts deletion.

These failures occurred on Laravel 12 and PHP 8.2 before the upgrade. They remain
unresolved and prevent declaring all application features verified.

Composer reported namespace/path mismatches involving `UserSeeder.php` and the
former overtime stubs. The overtime classes were replaced under `OnlineApplication`
on October 12, 2026; the `UserSeeder.php` mismatch remains.

The full upgraded test output and JUnit results are available locally in
`storage/logs/upgrade-results.txt` and `storage/logs/upgrade-results.xml`.
Server logs are in `storage/logs/php85-server.log` and
`storage/logs/php85-server-errors.log`.

Sources: [PHP Windows releases](https://windows.php.net/downloads/releases/releases.json),
[Laravel package versions](https://repo.packagist.org/p2/laravel/framework.json),
and [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade).
