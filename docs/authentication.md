# Authentication and Scramble testing

## Local Scramble testing

1. Keep `APP_ENV=local` on your development machine.
2. Call `POST /api/dev/token` with your existing user's `email` and `password`.
3. Paste the returned `token` into Scramble's bearer authorization field.
4. The token expires after one hour; `expires_at` is included in the response.
5. Call `POST /api/logout` using the token to revoke it. Other tokens are left valid.

The dev token endpoint is stateless: it validates credentials without logging a
browser session in. Five failed attempts for the same email/IP block both login
methods for 60 seconds. Successful authentication clears that counter.

Sanctum still requires CSRF protection for requests from a configured first-party
origin, including Scramble requests. For POST/PUT/logout requests from that origin,
first GET `/sanctum/csrf-cookie`, then send the URL-decoded `XSRF-TOKEN` cookie value
in the `X-XSRF-TOKEN` header and include cookies. A bearer token does not bypass
this check. Do not disable CSRF protection to address a 419 response.

Avoid mixing a logged-in browser session with bearer testing: Sanctum prefers the
session when both are supplied. Logout ends the authentication method Sanctum used.

## Browser session login

GET `/sanctum/csrf-cookie` and send the CSRF header and cookies as above, then POST
`/api/login` with email/password and optional boolean `remember`. This route always
starts a session and checks CSRF, even without an Origin/Referer header. Login
rotates the session ID. Session logout invalidates the session and rotates CSRF.

Configure the frontend origin in `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS`
(including its port), and send credentials with frontend requests.

## Scope and existing credentials

Expiry applies to newly issued dev tokens. Previously issued tokens are unchanged
and should be revoked separately if no longer needed. No existing passwords or
database records were modified by this change.

Employee authorization and surname-based initial passwords still need separate
changes before production. Production must use HTTPS, `APP_ENV=production`,
`APP_DEBUG=false`, and secure session cookies (`SESSION_SECURE_COOKIE=true`).
