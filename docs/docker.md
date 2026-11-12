# Docker guide (Celestia backend)

A beginner-friendly guide to running the backend with Docker. You do not need PHP,
Composer or MySQL installed. You only need **Docker Desktop**.

## 1. The big picture

`docker compose up` starts four containers that work together:

```text
Browser / Frontend
        |
        v  port 8000
   [ web ]  nginx          serves /public, forwards .php to "app"
        |
        v  port 9000 (internal)
   [ app ]  php-fpm        runs Laravel. Also runs migrations on start.
        |
        v  port 3306 (internal)
   [ db  ]  MySQL 8.4      stores all data (also sessions, cache, queue)

   [queue]  same image as "app", runs `php artisan queue:work`
```

Why a `queue` container? `.env` uses `QUEUE_CONNECTION=database`, so something must
keep reading jobs from the database.

## 2. What each file does

| File | Purpose |
| --- | --- |
| `Dockerfile` | Recipe to build the Laravel image (PHP 8.5 + extensions + your code). |
| `docker-compose.yml` | Describes the 4 containers, ports, volumes and how they connect. |
| `docker/php/entrypoint.sh` | Runs when `app` starts: waits for DB, migrates, seeds menus, caches config. |
| `docker/php/opcache.ini` | PHP speed settings for production. |
| `docker/nginx/default.conf` | nginx config: serve `public/`, send PHP to `app:9000`. |
| `.env.docker.example` | Template for your settings. Copy it to `.env.docker`. |
| `.dockerignore` | Files NOT copied into the image (`.env`, `vendor`, `.git`, tests...). |

### Dockerfile, step by step

The Dockerfile has three **stages**. Each starts with `FROM`.

1. **`base`**: starts from `php:8.5-fpm-alpine` and installs the PHP extensions
   Laravel needs: `pdo_mysql` (MySQL), `bcmath`, `pcntl`, `zip` (`opcache` is
   already built into PHP 8.5 core).
   Sets the timezone to `Asia/Manila`.
2. **`vendor`**: copies only `composer.json` and `composer.lock`, then runs
   `composer install --no-dev` (or full install when `INSTALL_DEV=true`). This is a
   separate stage so Docker can **cache** the dependencies and not reinstall them on
   every code change.
3. **`production`**: copies your code, then copies `vendor/` from stage 2, clears any
   local `bootstrap/cache/*.php` manifests, runs `package:discover`, creates the
   `storage/` folders and sets permissions.
   `ENTRYPOINT` runs `entrypoint.sh`, and `CMD ["php-fpm"]` is what it launches.

### docker-compose.yml, step by step

- `x-app: &app` is a reusable block shared by `app` and `queue` (same image, same
  env file, same `storage` volume).
- `app` runs setup (`RUN_SETUP` defaults to `true`).
- `queue` reuses the block, overrides `command`, and sets `RUN_SETUP: "false"` so it
  does **not** run migrations at the same time as `app`.
- `web` publishes port `8000` (change with `WEB_PORT`) and mounts your `public/`
  folder read-only.
- `db` is MySQL. It reads `DB_*` settings from `.env.docker` (mapping them to
  `MYSQL_*` on start) and has a **healthcheck**; `app` waits until it is healthy.
- **Volumes** keep data when containers are removed:
  - `db-data`: the MySQL files (your database).
  - `storage`: Laravel's `storage/` (logs, uploads).

## 3. First-time setup

Run these from the `celestia-backend` folder.

```powershell
# 1. Create your settings files
copy .env.docker.example .env.docker
```

Your local `.env` is for non-Docker development, so **do not overwrite it**. All
containers (`app`, `queue`, and `db`) read `.env.docker` directly.

Now edit `.env.docker` and set:

- `DB_PASSWORD` and `DB_ROOT_PASSWORD` (any strong values; keep `DB_PORT=3306` — `DB_FORWARD_PORT=3307` is only for host tools)
- `APP_URL`: backend URL (`http://localhost:8000` locally)
- `FRONTEND_URL`: frontend origin with no trailing slash (`http://localhost:5173` locally)
- `SANCTUM_STATEFUL_DOMAINS`: host + port only, no `http://` or trailing slash (`localhost:5173,127.0.0.1:5173` locally)
- `SESSION_SECURE_COOKIE=false` when developing over plain `http://` (set `true` only behind HTTPS in production)
- `APP_KEY` (next step)

Generate the app key and paste it into `APP_KEY=`:

```powershell
docker compose run --rm --no-deps app php artisan key:generate --show
```

Start everything:

```powershell
docker compose up -d --build
```

Check it works: open <http://localhost:8000/up>. You should see a healthy page.

## 4. Everyday commands

Add `--env-file .env.docker` only if you change `WEB_PORT` or `DB_FORWARD_PORT`
in `.env.docker` (otherwise Compose uses the defaults `8000` and `3307`).

| Task | Command |
| --- | --- |
| Start (build if needed) | `docker compose up -d --build` |
| Stop (keep data) | `docker compose down` |
| Stop and **delete the database** | `docker compose down -v` |
| See running containers | `docker compose ps` |
| Follow logs (all) | `docker compose logs -f` |
| Follow logs (one service) | `docker compose logs -f app` |
| Run an Artisan command | `docker compose exec app php artisan <command>` |
| Run migrations again | `docker compose exec app php artisan migrate --force` |
| Open a shell in the app | `docker compose exec app sh` |
| Open MySQL shell | `docker compose exec db mysql -u root -p` |
| Restart one service | `docker compose restart queue` |

After changing PHP code, rebuild: `docker compose up -d --build`. The image contains a
**copy** of your code, so edits on your machine are not seen until you rebuild.

After changing `.env.docker`, recreate containers: `docker compose up -d`. Config is
cached on start, so a plain restart is not always enough.

## 5. Seeding data

- On every start, `entrypoint.sh` runs `migrate` and `MenuAccessSeeder` (safe to
  repeat; it keeps edited role grants).
- `DatabaseSeeder` (demo employees, schedules, attendance) calls `fake()` from
  **Faker** (`require-dev`), which is **not** installed in the default `--no-dev`
  production image (`Call to undefined function Database\Factories\fake()`).
  To load demo data, use either approach:
  1. **Inside Docker (dev build):**
     ```powershell
     docker compose build --build-arg INSTALL_DEV=true
     docker compose up -d
     docker compose exec app php artisan db:seed --force
     ```
     (Rebuild without `INSTALL_DEV=true` before deploying to production.)
  2. **From your host PHP** (without rebuilding), pointing at the forwarded Docker DB port (`127.0.0.1:3307`) with your `.env.docker` DB user/password:
     ```bash
     DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=celestia DB_PASSWORD=password php artisan db:seed
     ```
- Production has no default admin user. Create the first one yourself (for example
  with `php artisan tinker`). The demo `admin@example.com` / `a` account is for
  development only.

## 6. Connecting from your computer

- API: <http://localhost:8000>
- MySQL (for a GUI such as DBeaver/HeidiSQL): host `127.0.0.1`, port `3307`
  (`DB_FORWARD_PORT`), user/password from `.env.docker`.
- Frontend: run it as usual (`npm run dev` / `pnpm dev`). Vite proxies `/api` and
  `/sanctum` to `API_PROXY_TARGET` (`http://localhost:8000` by default). In `.env.docker`,
  set `APP_URL=http://localhost:8000`, `FRONTEND_URL=http://localhost:5173`,
  `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`, and `SESSION_SECURE_COOKIE=false`
  (otherwise login fails with 419).

## Troubleshooting

Always start with `docker compose ps` and `docker compose logs <service>`.

| Symptom | Likely cause and fix |
| --- | --- |
| `app` exits: "APP_KEY is not set" | Generate a key (section 3) and paste it into `.env.docker`. |
| `db` exits: "Set DB_PASSWORD in .env.docker" | Fill in `DB_PASSWORD` and `DB_ROOT_PASSWORD` in `.env.docker`. |
| `app` loops on "Waiting for database" | DB not ready or wrong `DB_HOST`. Must be `db`, not `localhost`. Check `docker compose logs db`. |
| Access denied for user | You changed `DB_PASSWORD` after the DB volume was created. MySQL keeps the old one. Run `docker compose down -v` (**deletes data**) or change the password inside MySQL. |
| Port 8000 / 3307 already in use | Change `WEB_PORT` / `DB_FORWARD_PORT`, or stop the other program. |
| 502 Bad Gateway | `app` is not running. `docker compose logs app`. |
| 404 on every API route except `/` | Check `web` is using `docker/nginx/default.conf` (`docker compose exec web nginx -T`). |
| 419 on login | `FRONTEND_URL` / `SANCTUM_STATEFUL_DOMAINS` don't match the frontend, or `SESSION_SECURE_COOKIE=true` while using plain `http://` locally. Set it to `false` for local HTTP. |
| 500 error, no details | `APP_DEBUG=false` hides errors. Read `docker compose exec app tail -n 100 storage/logs/laravel.log`. |
| Code change not showing | Rebuild: `docker compose up -d --build`. |
| `.env` change not showing | Config is cached. `docker compose up -d` (recreate), or `docker compose exec app php artisan config:clear`. |
| `permission denied` on storage | `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`. |
| `entrypoint.sh: not found` / `bad interpreter` | The file got Windows (CRLF) line endings. Convert to LF (`.gitattributes` forces this for new checkouts). |
| Jobs never run | Check the worker: `docker compose logs -f queue`. |
| `db:seed` fails: `undefined function ... fake()` | Image was built with `--no-dev` (no Faker). Rebuild with `docker compose build --build-arg INSTALL_DEV=true && docker compose up -d`, or seed from host PHP on port `3307` (see section 5). |

### Useful debugging moves

```powershell
docker compose logs -f --tail=100 app   # recent app output
docker compose exec app php -v          # confirm PHP 8.5
docker compose exec app php -m          # list loaded PHP extensions (pdo_mysql?)
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec db mysql -u root -p -e "SHOW DATABASES;"
```

## 7. Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`
- Real HTTPS in front of `web` (reverse proxy or load balancer) and
  `SESSION_SECURE_COOKIE=true`
- Strong DB passwords; don't publish MySQL's port publicly (remove the `ports:` entry
  of `db`)
- `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` set to your real frontend domain
- Never commit `.env` or `.env.docker`
- Back up the `db-data` volume regularly
- Replace the surname-based initial passwords (see `authentication.md`)

Related docs: [authentication](process/authentication.md),
[menu access](process/menu-access.md).
