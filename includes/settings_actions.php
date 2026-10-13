<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_item_type') {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $reopenModal = 'itemTypes';
        if ($name === '') {
            $errorMsg = 'Please enter a name for the item type.';
        } elseif ($categoryId === 0) {
            $errorMsg = 'Please choose a category for this item type.';
        } else {

            $dupCheck = $conn->prepare("SELECT id, name FROM item_types WHERE LOWER(name) = LOWER(?)");
            $dupCheck->bind_param('s', $name);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();
            if ($dup) {
                $errorMsg = "Item type \"{$dup['name']}\" already exists — pick a different name, or use the existing one.";
            } else {
                $stmt = $conn->prepare("INSERT INTO item_types (name, category_id, created_by) VALUES (?, ?, ?)");
                $stmt->bind_param('sii', $name, $categoryId, $userId);
                if ($stmt->execute()) {
                    log_activity($conn, 'Added item type', $name, 'settings');
                    $successMsg = "Item type \"$name\" added.";
                    $reopenModal = '';
                } else {
                    $errorMsg = 'Could not add item type: ' . h($conn->error);
                }
            }
        }
    } elseif ($action === 'set_item_type_components') {
        $parentTypeId = (int)($_POST['parent_type_id'] ?? 0);
        $componentIds = array_map('intval', $_POST['component_type_ids'] ?? []);
        $componentIds = array_values(array_unique(array_filter($componentIds, function($id) use ($parentTypeId) {
            return $id > 0 && $id !== $parentTypeId;
        })));
        if ($parentTypeId === 0) {
            $errorMsg = 'Something went wrong — please try again.';
        } else {
            $del = $conn->prepare("DELETE FROM item_type_components WHERE parent_type_id = ?");
            $del->bind_param('i', $parentTypeId);
            $del->execute();
            if (!empty($componentIds)) {
                $ins = $conn->prepare("INSERT INTO item_type_components (parent_type_id, component_type_id) VALUES (?, ?)");
                foreach ($componentIds as $compId) {
                    $ins->bind_param('ii', $parentTypeId, $compId);
                    $ins->execute();
                }
            }
            $nameRes = $conn->prepare("SELECT name FROM item_types WHERE id=?");
            $nameRes->bind_param('i', $parentTypeId);
            $nameRes->execute();
            $ptName = $nameRes->get_result()->fetch_assoc()['name'] ?? '';
            log_activity($conn, 'Updated item type components', $ptName . ' (' . count($componentIds) . ' component(s))', 'settings');
            $successMsg = "Components for \"$ptName\" updated.";
        }
    } elseif ($action === 'add_item_category') {
        $name = trim($_POST['name'] ?? '');
        $reopenModal = 'itemCategories';
        if ($name === '') {
            $errorMsg = 'Please enter a name for the category.';
        } else {
            $dupCheck = $conn->prepare("SELECT id, name FROM item_categories WHERE LOWER(name) = LOWER(?)");
            $dupCheck->bind_param('s', $name);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();
            if ($dup) {
                $errorMsg = "Category \"{$dup['name']}\" already exists — pick a different name, or use the existing one.";
            } else {
                $stmt = $conn->prepare("INSERT INTO item_categories (name, created_by) VALUES (?, ?)");
                $stmt->bind_param('si', $name, $userId);
                if ($stmt->execute()) {
                    log_activity($conn, 'Added item category', $name, 'settings');
                    $successMsg = "Category \"$name\" added.";
                    $reopenModal = '';
                } else {
                    $errorMsg = 'Could not add category: ' . h($conn->error);
                }
            }
        }
    } elseif ($action === 'delete_item_category') {
        $id = (int)($_POST['id'] ?? 0);

        $check = $conn->prepare("SELECT COUNT(*) c FROM item_types WHERE category_id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['c'];
        if ($count > 0) {
            $errorMsg = 'Cannot delete: there are item types using this category.';
        } else {
            $nameRes = $conn->prepare("SELECT name FROM item_categories WHERE id=?");
            $nameRes->bind_param('i', $id);
            $nameRes->execute();
            $name = $nameRes->get_result()->fetch_assoc()['name'] ?? '';
            $stmt = $conn->prepare("DELETE FROM item_categories WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity($conn, 'Deleted item category', $name, 'settings');
            $successMsg = 'Category deleted.';
        }
    } elseif ($action === 'delete_item_type') {
        $id = (int)($_POST['id'] ?? 0);

        $check = $conn->prepare("SELECT COUNT(*) c FROM inventory_items WHERE item_type_id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['c'];
        if ($count > 0) {
            $errorMsg = 'Cannot delete: there are inventory items using this type.';
        } else {
            $nameRes = $conn->prepare("SELECT name FROM item_types WHERE id=?");
            $nameRes->bind_param('i', $id);
            $nameRes->execute();
            $name = $nameRes->get_result()->fetch_assoc()['name'] ?? '';
            $stmt = $conn->prepare("DELETE FROM item_types WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity($conn, 'Deleted item type', $name, 'settings');
            $successMsg = 'Item type deleted.';
        }
    } elseif ($action === 'add_department') {
        $name = trim($_POST['name'] ?? '');
        $reopenModal = 'departments';
        if ($name === '') {
            $errorMsg = 'Please enter a department name.';
        } else {
            $dupCheck = $conn->prepare("SELECT id, name FROM departments WHERE LOWER(name) = LOWER(?)");
            $dupCheck->bind_param('s', $name);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();
            if ($dup) {
                $errorMsg = "Department \"{$dup['name']}\" already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
                $stmt->bind_param('s', $name);
                if ($stmt->execute()) {
                    log_activity($conn, 'Added department', $name, 'settings');
                    $successMsg = "Department \"$name\" added.";
                    $reopenModal = '';
                } else {
                    $errorMsg = 'Could not add department: ' . h($conn->error);
                }
            }
        }
    } elseif ($action === 'delete_department') {
        $id = (int)($_POST['id'] ?? 0);
        $check = $conn->prepare("SELECT COUNT(*) c FROM deployments WHERE department_id = ?");
        $check->bind_param('i', $id);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['c'];
        if ($count > 0) {
            $errorMsg = 'Cannot delete: there are deployment records for this department.';
        } else {
            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity($conn, 'Deleted department', '', 'settings');
            $successMsg = 'Department deleted.';
        }
    } elseif ($action === 'add_department_person') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $reopenModal = 'deptPeople';
        if ($deptId <= 0 || $name === '') {
            $errorMsg = 'Select a department and enter a name.';
        } else {
            $dupCheck = $conn->prepare("SELECT id, name FROM department_people WHERE department_id = ? AND LOWER(name) = LOWER(?)");
            $dupCheck->bind_param('is', $deptId, $name);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();
            if ($dup) {
                $errorMsg = "\"{$dup['name']}\" is already on file for this department.";
            } else {
                $stmt = $conn->prepare("INSERT INTO department_people (department_id, name) VALUES (?, ?)");
                $stmt->bind_param('is', $deptId, $name);
                if ($stmt->execute()) {
                    log_activity($conn, 'Added faculty/staff', $name, 'settings');
                    $successMsg = "\"$name\" added.";
                    $reopenModal = '';
                } else {
                    $errorMsg = 'Could not add faculty/staff: ' . h($conn->error);
                }
            }
        }
    } elseif ($action === 'delete_department_person') {
        $id = (int)($_POST['id'] ?? 0);
        $nameRes = $conn->prepare("SELECT name FROM department_people WHERE id=?");
        $nameRes->bind_param('i', $id);
        $nameRes->execute();
        $name = $nameRes->get_result()->fetch_assoc()['name'] ?? '';
        $stmt = $conn->prepare("DELETE FROM department_people WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        log_activity($conn, 'Deleted faculty/staff', $name, 'settings');
        $successMsg = 'Faculty/staff removed.';
    } elseif ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $reopenModal = 'categories';
        if ($name === '') {
            $errorMsg = 'Please enter a category name.';
        } else {
            $dupCheck = $conn->prepare("SELECT id, name FROM categories WHERE LOWER(name) = LOWER(?)");
            $dupCheck->bind_param('s', $name);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();
            if ($dup) {
                $errorMsg = "Category \"{$dup['name']}\" already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->bind_param('s', $name);
                if ($stmt->execute()) {
                    log_activity($conn, 'Added work log category', $name, 'settings');
                    $successMsg = "Category \"$name\" added.";
                    $reopenModal = '';
                } else {
                    $errorMsg = 'Could not add category: ' . h($conn->error);
                }
            }
        }
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        log_activity($conn, 'Deleted work log category', '', 'settings');
        $successMsg = 'Category deleted.';
    }
}
