# cisotech

## Local configuration

The application reads configuration from process environment variables and an optional root-level `.env` file. The `.env` file is ignored by Git.

1. Copy `.env.example` to `.env`.
2. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` for your MySQL/MariaDB instance. Create the database named by `DB_NAME` and import `database.sql` into it.
3. Set `APP_TIMEZONE` and, if desired, the database admin and font URLs.
4. Before running `setup.php`, replace all four `BOOTSTRAP_*` values with unique credentials. Bootstrap passwords must be at least 16 characters. Do not reuse the example placeholders.
5. Run `setup.php` once to create or repair the configured administrator accounts, then remove the bootstrap username/password variables from `.env`.

The VS Code browser launch configuration uses `CISOTECH_DEBUG_URL` from the VS Code process environment. The bundled Adminer file is a third-party application and retains its own upstream links and internal defaults; those are not application deployment settings.

## Tests

Install the development dependencies with `composer install`, then run the PHPUnit suite with `composer test`.