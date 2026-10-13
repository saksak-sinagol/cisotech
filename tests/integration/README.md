# HTTP integration tests

The integration suite sends HTTP requests to a running copy of the app and
resets every table in its configured test database before each test. Never
point it at the development or production database.

## Prepare an isolated test database

Create a disposable MySQL/MariaDB instance or database named with the
`cisotech_test_` prefix, then import the project schema:

```sh
mariadb --host=127.0.0.1 --port=TEST_PORT --user=TEST_ADMIN \
  -e 'CREATE DATABASE cisotech_test_endpoint CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
mariadb --host=127.0.0.1 --port=TEST_PORT --user=TEST_ADMIN \
  cisotech_test_endpoint < database.sql
```

Create a dedicated database account with privileges limited to that test
database. Do not use application or production credentials.

## Start the app and run the suite

Start the PHP built-in server with its database environment configured to use
the isolated test database. Then run PHPUnit with matching test-only connection
settings:

```sh
APP_TIMEZONE=Asia/Manila \
DB_HOST=127.0.0.1 DB_PORT=TEST_PORT \
DB_NAME=cisotech_test_endpoint DB_USER=TEST_USER DB_PASS=TEST_PASSWORD \
php -S 127.0.0.1:TEST_HTTP_PORT
```

In another shell, from the project root:

```sh
CISOTECH_TEST_BASE_URL=http://127.0.0.1:TEST_HTTP_PORT \
CISOTECH_TEST_DB_HOST=127.0.0.1 CISOTECH_TEST_DB_PORT=TEST_PORT \
CISOTECH_TEST_DB_NAME=cisotech_test_endpoint \
CISOTECH_TEST_DB_USER=TEST_USER CISOTECH_TEST_DB_PASS=TEST_PASSWORD \
composer test:integration
```

The runner refuses non-loopback HTTP hosts, database names without the required
prefix, and the database configured as the app's `DB_NAME`. The existing
`composer test` command remains limited to the fast unit test suite.
