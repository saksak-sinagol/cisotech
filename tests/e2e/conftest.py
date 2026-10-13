import os
import re
import socket
import subprocess
import time
import urllib.error
import urllib.request
from pathlib import Path
from tempfile import TemporaryFile

import pytest
from playwright.sync_api import Browser, Page, sync_playwright


PROJECT_ROOT = Path(__file__).resolve().parents[2]
LOOPBACK_HOSTS = {"127.0.0.1", "::1", "localhost"}


def _test_environment() -> dict[str, str]:
    prefix = "CISOTECH_E2E_DB_"
    required = ["HOST", "PORT", "NAME", "USER", "PASS"]
    missing = [prefix + key for key in required if not os.environ.get(prefix + key)]
    if missing:
        raise pytest.UsageError(
            "Set the isolated E2E database environment variables: " + ", ".join(missing)
        )

    database_name = os.environ[prefix + "NAME"]
    if not re.fullmatch(r"cisotech_test_e2e_[a-z0-9_]+", database_name):
        raise pytest.UsageError("CISOTECH_E2E_DB_NAME must start with cisotech_test_e2e_.")
    if os.environ[prefix + "HOST"] not in LOOPBACK_HOSTS:
        raise pytest.UsageError("The E2E database host must be localhost or loopback.")
    if not os.environ.get("CISOTECH_E2E_USERNAME") or not os.environ.get("CISOTECH_E2E_PASSWORD"):
        raise pytest.UsageError("Set CISOTECH_E2E_USERNAME and CISOTECH_E2E_PASSWORD.")

    env = os.environ.copy()
    env.update(
        {
            "APP_TIMEZONE": env.get("APP_TIMEZONE", "Asia/Manila"),
            "DB_HOST": env[prefix + "HOST"],
            "DB_PORT": env[prefix + "PORT"],
            "DB_NAME": database_name,
            "DB_USER": env[prefix + "USER"],
            "DB_PASS": env[prefix + "PASS"],
            "FONT_PRECONNECT_URL": "",
            "FONT_STYLESHEET_URL": "",
        }
    )
    return env


def _available_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


@pytest.fixture(scope="session")
def app_server() -> dict[str, str]:
    env = _test_environment()
    port = _available_port()
    base_url = f"http://127.0.0.1:{port}"

    with TemporaryFile(mode="w+t") as server_log:
        process = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", str(PROJECT_ROOT)],
            cwd=PROJECT_ROOT,
            env=env,
            stdout=server_log,
            stderr=subprocess.STDOUT,
            text=True,
        )
        try:
            deadline = time.monotonic() + 15
            while time.monotonic() < deadline:
                if process.poll() is not None:
                    server_log.seek(0)
                    raise RuntimeError("PHP test server exited:\n" + server_log.read())
                try:
                    with urllib.request.urlopen(base_url + "/login.php", timeout=1) as response:
                        if response.status == 200:
                            break
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
            else:
                server_log.seek(0)
                raise RuntimeError("PHP test server did not become ready:\n" + server_log.read())

            yield {"base_url": base_url, "env": env}
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=5)


@pytest.fixture(autouse=True)
def isolated_test_account(app_server: dict[str, str]) -> None:
    result = subprocess.run(
        ["php", str(PROJECT_ROOT / "tests/e2e/prepare.php")],
        cwd=PROJECT_ROOT,
        env=app_server["env"],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError("Could not prepare the isolated E2E database:\n" + result.stderr + result.stdout)


@pytest.fixture
def page(app_server: dict[str, str]) -> Page:
    with sync_playwright() as playwright:
        browser: Browser = playwright.chromium.launch()
        context = browser.new_context()
        page = context.new_page()
        page.set_default_timeout(8000)
        page.goto(app_server["base_url"] + "/login.php")
        yield page
        context.close()
        browser.close()
