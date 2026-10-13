# cisotech

## Local configuration

The application reads configuration from process environment variables and an optional root-level `.env` file. The `.env` file is ignored by Git.

1. Copy `.env.example` to `.env`.
2. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` for your MySQL/MariaDB instance. Create the database named by `DB_NAME` and import `database.sql` into it.
3. Set `APP_TIMEZONE` and, if desired, the database admin and font URLs.
4. Before running `setup.php`, replace all four `BOOTSTRAP_*` values with unique credentials. Bootstrap passwords must be at least 16 characters. Do not reuse the example placeholders.
5. Run `setup.php` once to create or repair the configured administrator accounts, then remove the bootstrap username/password variables from `.env`.

The VS Code browser launch configuration uses `CISOTECH_DEBUG_URL` from the VS Code process environment. The bundled Adminer file is a third-party application and retains its own upstream links and internal defaults; those are not application deployment settings.

## HTTP security defaults

The application rejects requests that include an `Origin` other than the exact
same scheme, host, and port used by the request. It intentionally sends no
`Access-Control-Allow-Origin` header and does not enable cross-origin browser
access. CORS is a browser policy, not authentication or authorization; the
application's session and CSRF checks remain in place.

PHP responses include `X-Content-Type-Options: nosniff`, `X-Frame-Options:
DENY`, a restrictive `Referrer-Policy`, a `Permissions-Policy` disabling
camera, microphone, and geolocation, and a Content Security Policy restricting
scripts, styles, fonts, images, connections, forms, and framing to the app and
its configured Google Fonts. Inline scripts and styles remain allowed for
compatibility with the current server-rendered pages. HSTS is emitted only
when PHP sees HTTPS; configure TLS at the web server or trusted proxy rather
than trusting arbitrary forwarded-protocol headers.

Login allows five attempts for each normalized username/client-IP pair per
15-minute window. Authenticated POST mutations are limited to 30 per
user/client-IP pair per minute. The setup endpoint is limited to five requests
per client IP per hour. Client IPs come only from `REMOTE_ADDR`, so reverse
proxy deployments must configure the web server to set `REMOTE_ADDR` safely;
the app deliberately does not trust user-supplied forwarding headers.

Unhandled PHP exceptions, runtime warnings, and fatal errors are handled
centrally. Browser requests receive a generic recovery page with a reference
ID; JSON requests receive a generic error payload with the same ID. Detailed
messages, request bodies, passwords, and CSRF/session tokens are never included
in application error logs. Structured one-line JSON records contain the UTC
timestamp, severity, event, request ID, method, route, authenticated user ID
when available, client IP, error type/code, and source location. They are sent
through PHP's configured `error_log` destination (commonly the web-server error
log or process stderr); configure and rotate that destination in PHP/server
operations.

Fresh databases receive the `request_rate_limits` table from `database.sql`.
Before deploying this code to an existing database, apply the schema change
from `migrate_v20_add_request_rate_limits.php` using an existing authenticated
superadmin session, or create the table directly:

```sql
CREATE TABLE request_rate_limits (
  rate_key CHAR(64) PRIMARY KEY,
  window_started_at BIGINT UNSIGNED NOT NULL,
  request_count INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Tests

Install the development dependencies with `composer install`, then run the PHPUnit suite with `composer test`.

## PHP formatting

Run `composer lint:php` to check PHP formatting without modifying files, or
`composer format:php` to apply the configured safe fixes. PHP-CS-Fixer uses a
small project rule set to preserve the existing PHP/template style; the
third-party `adminer.php` file is excluded. Review the diff after formatting.
