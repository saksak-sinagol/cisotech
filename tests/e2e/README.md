# Browser end-to-end tests

These tests use Playwright with Chromium and a separately provisioned MariaDB
database. They cover the current account flow (the app does not expose public
sign-up): login, creating an inventory category and type, adding an inventory
item, and logging out. A failed-password case is included as well.

## Install

Use Python 3.9 or newer and PHP with `mysqli`. Install the Python test
dependencies and the matching Playwright browser:

```sh
python3 -m venv .venv
. .venv/bin/activate
python -m pip install -r requirements-e2e.txt
python -m playwright install chromium
```

## Isolated database

Create a throwaway database named with the `cisotech_test_e2e_` prefix, import
`database.sql`, and create a dedicated account with privileges only on that
database. Do not use the development or production database or its credentials.

```sh
mariadb --host=127.0.0.1 --port=TEST_PORT --user=TEST_ADMIN \
  -e 'CREATE DATABASE cisotech_test_e2e_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
mariadb --host=127.0.0.1 --port=TEST_PORT --user=TEST_ADMIN \
  cisotech_test_e2e_local < database.sql
```

## Run

The test fixture starts and stops its own loopback PHP server. Before every
test, `prepare.php` truncates the configured test database and inserts the
temporary superadmin account. The fixture refuses a non-loopback database host
or a database name outside the test-only prefix.

```sh
export CISOTECH_E2E_DB_HOST=127.0.0.1
export CISOTECH_E2E_DB_PORT=TEST_PORT
export CISOTECH_E2E_DB_NAME=cisotech_test_e2e_local
export CISOTECH_E2E_DB_USER=TEST_USER
export CISOTECH_E2E_DB_PASS=TEST_PASSWORD
export CISOTECH_E2E_USERNAME=e2e-admin
export CISOTECH_E2E_PASSWORD='temporary-test-only-password'
python -m pytest
```
