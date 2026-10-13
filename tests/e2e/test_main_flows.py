import os
import re

from playwright.sync_api import Page, expect


def test_admin_can_log_in_create_inventory_item_and_log_out(page: Page) -> None:
    username = os.environ["CISOTECH_E2E_USERNAME"]
    password = os.environ["CISOTECH_E2E_PASSWORD"]

    page.locator('input[name="username"]').fill(username)
    page.locator('input[name="password"]').fill("incorrect-test-password")
    page.get_by_role("button", name="Login").click()
    expect(page.locator(".login-error")).to_contain_text("Incorrect password.")

    page.locator('input[name="password"]').fill(password)
    page.get_by_role("button", name="Login").click()
    expect(page).to_have_url(re.compile(r"/index\.php$"))
    expect(page.locator(".topbar-title")).to_contain_text("Dashboard")

    page.get_by_role("link", name="Item Types / Departments / Categories").click()
    expect(page).to_have_url(re.compile(r"/settings\.php$"))

    category = "E2E Hardware"
    page.locator("#settingsTab-itemCategories").get_by_role("button", name="+ Add Category").click()
    category_modal = page.locator("#settingsModal-itemCategories")
    category_modal.locator('input[name="name"]').fill(category)
    category_modal.get_by_role("button", name="Add Category").click()
    expect(page.locator(".alert-success")).to_contain_text(f'Category "{category}" added.')
    expect(page.locator("#settingsTab-itemCategories")).to_contain_text(category)

    page.get_by_role("button", name="Model/Brand").click()
    type_name = "E2E Network Device"
    type_panel = page.locator("#settingsTab-itemTypes")
    type_panel.get_by_role("button", name="+ Item").click()
    type_modal = page.locator("#settingsModal-itemTypes")
    type_modal.locator('input[name="name"]').fill(type_name)
    type_modal.locator('select[name="category_id"]').select_option(label=category)
    type_modal.get_by_role("button", name="Submit").click()
    expect(page.locator(".alert-success")).to_contain_text(f'Item type "{type_name}" added.')
    expect(type_panel).to_contain_text(type_name)

    page.locator('.nav-item[href="inventory.php"]').click()
    expect(page.locator("#restockForm")).to_be_visible()
    page.locator('#restockForm input[name="po_number"]').fill("E2E-PO-1001")
    page.locator("#restockForm .restock-type-select").select_option(label=type_name)
    item_name = "E2E Router Model"
    page.locator('#restockForm input[name="item_name[]"]').fill(item_name)
    page.locator('#restockForm input[name="unit_pn[0][]"]').fill("E2E-PN-001")
    page.locator("#restockForm button[type=submit]").click()
    expect(page.locator(".alert-success")).to_contain_text("1 new item added")
    expect(page.locator(".data-table")).to_contain_text(item_name)

    page.locator(".account-btn").click()
    page.locator("#accountDropdown").get_by_role("link", name="Logout").click()
    expect(page).to_have_url(re.compile(r"/login\.php$"))
    page.goto(page.url.replace("/login.php", "/inventory.php"))
    expect(page).to_have_url(re.compile(r"/login\.php$"))
