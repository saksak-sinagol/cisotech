<?php

final class EndpointIntegrationTest extends IntegrationTestCase
{
    public function testSecurityHeadersArePresentAndCORSRejectsForeignOrigins(): void
    {
        $client = $this->newClient();
        $response = $this->request($client, 'login.php');
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('X-Content-Type-Options: nosniff', $response['headers']);
        self::assertStringContainsString('X-Frame-Options: DENY', $response['headers']);
        self::assertStringContainsString('Referrer-Policy: strict-origin-when-cross-origin', $response['headers']);
        self::assertStringContainsString('Content-Security-Policy:', $response['headers']);
        self::assertStringNotContainsString('Access-Control-Allow-Origin:', $response['headers']);

        $sameOrigin = parse_url(self::$baseUrl, PHP_URL_SCHEME) . '://' . parse_url(self::$baseUrl, PHP_URL_HOST);
        $port = parse_url(self::$baseUrl, PHP_URL_PORT);
        if ($port !== null) {
            $sameOrigin .= ':' . $port;
        }
        $accepted = $this->request($client, 'login.php', 'GET', [], ['Origin: ' . $sameOrigin]);
        self::assertSame(200, $accepted['status']);

        $rejected = $this->request($client, 'login.php', 'GET', [], ['Origin: https://attacker.example']);
        self::assertSame(403, $rejected['status']);
        self::assertStringContainsString('Cross-origin requests are not allowed.', $rejected['body']);
    }

    public function testLoginIsRateLimitedPerUsernameAndClientAddress(): void
    {
        $client = $this->newClient();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->request($client, 'login.php', 'POST', [
                'username' => 'rate-limit-integration-user',
                'password' => 'incorrect-password',
            ]);
            self::assertSame(200, $response['status']);
        }

        $limited = $this->request($client, 'login.php', 'POST', [
            'username' => 'rate-limit-integration-user',
            'password' => 'incorrect-password',
        ]);
        self::assertSame(429, $limited['status']);
        self::assertStringContainsString('Retry-After:', $limited['headers']);
    }

    public function testSensitiveAuthenticatedWritesAreRateLimited(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'worklog.php');

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $response = $this->request($client, 'worklog.php', 'POST', [
                'csrf_token' => $token,
                'action' => 'add_category',
                'category_name' => 'Rate limit test ' . $attempt,
            ]);
            self::assertSame(302, $response['status'], $response['body']);
        }

        $limited = $this->request($client, 'worklog.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_category',
            'category_name' => 'Rate limit test extra',
        ]);
        self::assertSame(429, $limited['status']);
    }

    public function testDeploymentAjaxRejectsMalformedIdsAndOversizedCart(): void
    {
        $client = $this->login($this->newClient());
        $badId = $this->request($client, 'deployments.php?ajax=items_by_category&type_id=0');
        self::assertSame(400, $badId['status']);
        self::assertSame(['ok' => false, 'error' => 'A valid item type is required.'], json_decode($badId['body'], true));

        $token = $this->csrfToken($client, 'deployments.php');
        $oversizedCart = json_encode(array_fill(0, 101, [
            'item_id' => $this->itemId,
            'po_number' => 'IT-PO-001',
            'pn_number' => 'IT-PN-1',
        ]));
        $response = $this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'csrf_token' => $token,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'mr_number' => 'IT-MR-LARGE',
            'cart' => $oversizedCart,
        ]);
        self::assertSame(400, $response['status']);
        self::assertSame(0, (int)self::$database->query("SELECT COUNT(*) FROM deployments WHERE mr_number = 'IT-MR-LARGE'")->fetch_row()[0]);
    }

    public function testProtectedPagesAndAjaxEndpointRejectGuests(): void
    {
        $client = $this->newClient();
        $paths = [
            'account.php',
            'departments.php',
            'deployments.php?ajax=current_ph_time',
            'inventory.php',
            'messages.php',
            'reports.php',
            'settings.php',
            'staff.php',
            'worklog.php',
        ];

        foreach ($paths as $path) {
            $response = $this->request($client, $path);
            self::assertSame(302, $response['status'], $path . ' should redirect guests to login.');
            self::assertStringContainsString('Location: login.php', $response['headers']);
        }

        $response = $this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'department_id' => $this->departmentId,
            'cart' => '[]',
        ]);
        self::assertSame(302, $response['status']);
    }

    public function testLoginRejectsUnknownUserAndAcceptsFixtureAccount(): void
    {
        $client = $this->newClient();
        $invalid = $this->request($client, 'login.php', 'POST', [
            'username' => 'missing-integration-user',
            'password' => 'wrong',
        ]);
        self::assertSame(200, $invalid['status']);
        self::assertStringContainsString('Account not found.', $invalid['body']);
        $this->login($client);
    }

    public function testAllDeploymentAjaxEndpointsReturnValidJsonForAuthenticatedUser(): void
    {
        $client = $this->login($this->newClient());
        $routes = [
            'deployments.php?ajax=items_by_category&type_id=' . $this->itemTypeId => 'items',
            'deployments.php?ajax=pn_numbers_for_item&item_id=' . $this->itemId => 'pn_numbers',
            'deployments.php?ajax=preset_specs_for_item&item_id=' . $this->itemId => 'specs',
            'deployments.php?ajax=current_ph_time' => 'date',
            'deployments.php?ajax=active_deployments_for_person&department_id=' . $this->departmentId . '&recipient_name=' . rawurlencode('Integration Recipient') => 'items',
            'deployments.php?ajax=active_deployments_for_person' => 'items',
        ];

        foreach ($routes as $route => $expectedKey) {
            $payload = $this->jsonResponse($this->request($client, $route));
            self::assertTrue($payload['ok'] ?? false, $route);
            self::assertArrayHasKey($expectedKey, $payload, $route);
        }
        $items = $this->jsonResponse($this->request($client, 'deployments.php?ajax=items_by_category&type_id=' . $this->itemTypeId));
        self::assertSame('Integration Router Model', $items['items'][0]['name']);
        self::assertSame(8, $items['items'][0]['available']);
    }

    public function testSpecValuesReplaceExistingValuesTrimInputAndSkipBlankFields(): void
    {
        save_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId, [
            'processor' => '  Ryzen Test  ',
            'ram' => '   ',
            'storage' => '512 GB',
        ]);

        self::assertSame(
            ['processor' => 'Ryzen Test', 'storage' => '512 GB'],
            load_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId)
        );

        save_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId, [
            'processor' => 'Updated Processor',
        ]);
        self::assertSame(
            ['processor' => 'Updated Processor'],
            load_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId)
        );
        self::assertDatabaseCount('inventory_item_spec_values', 'item_id = ?', (string)$this->itemId, 1);

        save_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId, [
            'processor' => '',
            'ram' => '  ',
        ]);
        self::assertSame([], load_spec_values(self::$database, 'inventory_item_spec_values', 'item_id', $this->itemId));
    }

    public function testUnreadMessageCountReturnsZeroForGuestAndCountsOnlyUnreadRecipientMessages(): void
    {
        $originalSession = $_SESSION;
        try {
            $_SESSION = [];
            self::assertSame(0, unread_message_count(self::$database));

            $_SESSION = [
                'user_id' => $this->adminId,
                'full_name' => 'Integration Admin',
                'username' => 'integration-admin',
                'role' => 'admin',
            ];

            $firstMessage = self::$database->prepare('INSERT INTO messages (sender_id, recipient_id, body, is_read) VALUES (?, ?, ?, ?)');
            $body = 'Unread for admin';
            $isRead = 0;
            $firstMessage->bind_param('iisi', $this->staffId, $this->adminId, $body, $isRead);
            $firstMessage->execute();

            $secondMessage = self::$database->prepare('INSERT INTO messages (sender_id, recipient_id, body, is_read) VALUES (?, ?, ?, ?)');
            $body = 'Already read for admin';
            $isRead = 1;
            $secondMessage->bind_param('iisi', $this->staffId, $this->adminId, $body, $isRead);
            $secondMessage->execute();

            $otherRecipient = self::$database->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
            $otherBody = 'Unread for staff';
            $otherRecipient->bind_param('iis', $this->adminId, $this->staffId, $otherBody);
            $otherRecipient->execute();

            self::assertSame(1, unread_message_count(self::$database));
        } finally {
            $_SESSION = $originalSession;
        }
    }

    public function testActivityLoggerRecordsCurrentUserAndAnonymousActor(): void
    {
        $originalSession = $_SESSION;
        try {
            $_SESSION = [
                'user_id' => $this->adminId,
                'full_name' => 'Integration Admin',
                'username' => 'integration-admin',
                'role' => 'admin',
            ];
            log_activity(self::$database, 'Integration action', 'authenticated details', 'test');

            $_SESSION = [];
            log_activity(self::$database, 'Anonymous action');

            $rows = self::$database->query("SELECT user_id, actor_name, actor_role, action, details, module FROM activity_log ORDER BY id")->fetch_all(MYSQLI_ASSOC);
            self::assertCount(2, $rows);
            self::assertSame((string)$this->adminId, (string)$rows[0]['user_id']);
            self::assertSame('Integration Admin', $rows[0]['actor_name']);
            self::assertSame('admin', $rows[0]['actor_role']);
            self::assertSame('authenticated details', $rows[0]['details']);
            self::assertSame('test', $rows[0]['module']);
            self::assertNull($rows[1]['user_id']);
            self::assertSame('Unknown', $rows[1]['actor_name']);
            self::assertSame('staff', $rows[1]['actor_role']);
            self::assertSame('other', $rows[1]['module']);
        } finally {
            $_SESSION = $originalSession;
        }
    }

    public function testDeploymentCartReturnsValidationErrorsForInvalidRequests(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $cases = [
            [['department_id' => 0], 'Please select a department.'],
            [['department_id' => 99999], 'Please select a valid department.'],
            [['department_id' => $this->departmentId], 'Please select a faculty/staff name.'],
            [['department_id' => $this->departmentId, 'recipient_name' => 'Unknown'], 'Please select a valid faculty/staff name for this department.'],
            [['department_id' => $this->departmentId, 'recipient_name' => 'Integration Recipient'], 'Please enter an MR (Material Request) Number.'],
            [['department_id' => $this->departmentId, 'recipient_name' => 'Integration Recipient', 'mr_number' => 'MR-1', 'cart' => '[]'], 'Add at least one item before deploying.'],
        ];

        foreach ($cases as [$fields, $expectedError]) {
            $payload = $this->jsonResponse($this->request($client, 'deployments.php', 'POST', [
                'action' => 'submit_deployment_cart',
                'csrf_token' => $token,
                'cart' => '[]',
                ...$fields,
            ]));
            self::assertFalse($payload['ok'], $expectedError);
            self::assertSame($expectedError, $payload['error']);
        }
    }

    public function testDeploymentCartCreatesDeploymentAndConsumesUnit(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $payload = $this->jsonResponse($this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'csrf_token' => $token,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'mr_number' => 'IT-MR-1',
            'deployment_type' => 'new',
            'deployed_date' => date('Y-m-d'),
            'deployed_time' => date('H:i'),
            'cart' => json_encode([[
                'item_id' => $this->itemId,
                'po_number' => 'IT-PO-001',
                'pn_number' => 'IT-PN-1',
                'item_details' => 'Integration deployment',
            ]]),
        ]));

        self::assertTrue($payload['ok']);
        self::assertSame(1, $payload['deployed']);
        self::assertDatabaseCount('deployments', 'mr_number = ?', 'IT-MR-1', 1);
        self::assertDatabaseCount('deployment_batch_consumption', 'batch_id = ?', (string)$this->batchId, 1);
    }

    public function testCartRejectsMalformedUnavailableUnitWithoutCreatingDeployment(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $payload = $this->jsonResponse($this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'csrf_token' => $token,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'mr_number' => 'IT-MR-2',
            'cart' => json_encode([[
                'item_id' => $this->itemId,
                'po_number' => 'IT-PO-001',
                'pn_number' => 'NOT-A-UNIT',
            ]]),
        ]));

        self::assertFalse($payload['ok']);
        self::assertStringContainsString('not an available unit', $payload['error']);
        self::assertDatabaseCount('deployments', 'mr_number = ?', 'IT-MR-2', 0);
    }

    public function testInventoryPostRejectsInvalidInputAndAddsValidStock(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'inventory.php');
        $invalid = $this->request($client, 'inventory.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item',
            'po_number' => '',
            'item_name[0]' => 'Invalid Item',
            'item_type_id[0]' => $this->itemTypeId,
            'quantity_total[0]' => 1,
            'unit_pn[0][0]' => 'IT-X',
        ]);
        self::assertSame(200, $invalid['status']);
        self::assertStringContainsString('PO Number is required.', $invalid['body']);

        $missingPn = $this->request($client, 'inventory.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item',
            'po_number' => 'IT-PO-NEW',
            'item_name[0]' => 'Missing PN Item',
            'item_type_id[0]' => $this->itemTypeId,
            'quantity_total[0]' => 2,
            'unit_pn[0][0]' => 'IT-X1',
        ]);
        self::assertStringContainsString('A PN Number is required for every unit.', $missingPn['body']);

        $success = $this->request($client, 'inventory.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item',
            'po_number' => 'IT-PO-NEW',
            'date_added' => date('Y-m-d'),
            'time_added' => date('H:i'),
            'low_stock_threshold' => 0,
            'item_name[0]' => 'New Integration Item',
            'item_type_id[0]' => $this->itemTypeId,
            'quantity_total[0]' => 2,
            'unit_pn[0][0]' => 'IT-NEW-1',
            'unit_pn[0][1]' => 'IT-NEW-2',
        ]);
        self::assertSame(200, $success['status']);
        self::assertStringContainsString('1 new item added', $success['body']);
        self::assertDatabaseCount('inventory_items', 'item_name = ?', 'New Integration Item', 1);
    }

    public function testInventoryRemarkRequiresTextAndCanBeAdded(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'inventory.php');
        $blank = $this->request($client, 'inventory.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_remark',
            'item_id' => $this->itemId,
            'remark_text' => ' ',
            'remark_date' => date('Y-m-d'),
        ]);
        self::assertStringContainsString('Please enter a remark.', $blank['body']);

        $valid = $this->request($client, 'inventory.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_remark',
            'item_id' => $this->itemId,
            'remark_text' => 'Integration test remark',
            'remark_date' => date('Y-m-d'),
        ]);
        self::assertStringContainsString('Remark added to', $valid['body']);
        self::assertDatabaseCount('inventory_remarks', 'remark = ?', 'Integration test remark', 1);
    }

    public function testDirectDeploymentPostValidatesAndCreatesRecord(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $invalid = $this->request($client, 'deployments.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'deploy_item',
            'item_id' => $this->itemId,
            'department_id' => $this->departmentId,
            'pn_number' => 'IT-PN-2',
        ]);
        self::assertStringContainsString('PO Number is required for every item.', $invalid['body']);
        self::assertDatabaseCount('deployments', 'pn_number = ?', 'IT-PN-2', 0);

        $valid = $this->request($client, 'deployments.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'deploy_item',
            'item_id' => $this->itemId,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'po_number' => 'IT-PO-001',
            'pn_number' => 'IT-PN-2',
            'quantity_deployed' => 1,
            'deployed_date' => date('Y-m-d'),
            'deployed_time' => date('H:i'),
        ]);
        self::assertStringContainsString('Item deployed successfully.', $valid['body']);
        self::assertDatabaseCount('deployments', 'pn_number = ?', 'IT-PN-2', 1);
    }

    public function testWorklogPostValidatesAndCreatesEntry(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'worklog.php');
        $invalid = $this->request($client, 'worklog.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_entry',
            'log_time' => '',
            'description' => '',
        ]);
        self::assertStringContainsString('Please fill in at least the time and task description.', $invalid['body']);
        self::assertDatabaseCount('work_logs', 'description = ?', 'Integration worklog entry', 0);

        $valid = $this->request($client, 'worklog.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_entry',
            'log_date' => date('Y-m-d'),
            'log_time' => '10:00 am',
            'item_name' => 'Integration Router',
            'description' => 'Integration worklog entry',
            'category_id' => $this->categoryId,
            'status' => 'done',
            'remarks' => 'ok',
            'note_color' => 'none',
        ]);
        self::assertStringContainsString('Task entry saved.', $valid['body']);
        self::assertDatabaseCount('work_logs', 'description = ?', 'Integration worklog entry', 1);

        $category = $this->request($client, 'worklog.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_category',
            'category_name' => 'Integration Worklog Category',
        ]);
        self::assertSame(302, $category['status']);
        self::assertDatabaseCount('categories', 'name = ?', 'Integration Worklog Category', 1);
    }

    public function testMessagePostRejectsSelfMessageAndSendsToAnotherUser(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'messages.php?with=' . $this->staffId);
        $self = $this->request($client, 'messages.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'send_message',
            'recipient_id' => $this->adminId,
            'body' => 'Self message',
        ]);
        self::assertStringContainsString("You can't send a message to yourself.", $self['body']);

        $success = $this->request($client, 'messages.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'send_message',
            'recipient_id' => $this->staffId,
            'body' => 'Integration message',
        ]);
        self::assertSame(302, $success['status']);
        self::assertDatabaseCount('messages', 'body = ?', 'Integration message', 1);
    }

    public function testAccountPostRejectsIncorrectCurrentPasswordAndSavesValidProfile(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'account.php');
        $invalid = $this->request($client, 'account.php', 'POST', [
            'csrf_token' => $token,
            'full_name' => 'Integration Admin',
            'username' => 'integration-admin',
            'current_password' => 'incorrect',
        ]);
        self::assertStringContainsString('Current password is incorrect.', $invalid['body']);

        $valid = $this->request($client, 'account.php', 'POST', [
            'csrf_token' => $token,
            'full_name' => 'Updated Integration Admin',
            'username' => 'integration-admin',
            'current_password' => 'Test-Only-Password-90210',
        ]);
        self::assertStringContainsString('Account updated successfully.', $valid['body']);
        self::assertDatabaseCount('users', 'full_name = ?', 'Updated Integration Admin', 1);
    }

    public function testStaffManagementRejectsWeakPasswordAndCreatesUser(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'staff.php');
        $invalid = $this->request($admin, 'staff.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_user',
            'full_name' => 'Weak Test User',
            'username' => 'weak-test-user',
            'password' => 'short',
        ]);
        self::assertStringContainsString('Password must be at least 6 characters.', $invalid['body']);

        $valid = $this->request($admin, 'staff.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_user',
            'full_name' => 'Created Integration User',
            'username' => 'created-integration-user',
            'password' => 'Integration-User-Password',
            'role' => 'staff',
        ]);
        self::assertStringContainsString('Account for "Created Integration User" created as staff.', $valid['body']);
        self::assertDatabaseCount('users', 'username = ?', 'created-integration-user', 1);

        $staff = $this->login($this->newClient(), 'integration-staff');
        $denied = $this->request($staff, 'staff.php', 'POST', [
            'csrf_token' => 'not-required-before-admin-check',
            'action' => 'add_user',
            'full_name' => 'Unauthorized User',
            'username' => 'unauthorized-user',
            'password' => 'Integration-User-Password',
        ]);
        self::assertSame(302, $denied['status']);
        self::assertDatabaseCount('users', 'username = ?', 'unauthorized-user', 0);
    }

    public function testSettingsPostValidatesAndCreatesDepartment(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'settings.php');
        $invalid = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_department',
            'name' => ' ',
        ]);
        self::assertStringContainsString('Please enter a department name.', $invalid['body']);

        $valid = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_department',
            'name' => 'New Integration Department',
        ]);
        self::assertStringContainsString('Department "New Integration Department" added.', $valid['body']);
        self::assertDatabaseCount('departments', 'name = ?', 'New Integration Department', 1);

        $staff = $this->login($this->newClient(), 'integration-staff');
        $denied = $this->request($staff, 'settings.php', 'POST', [
            'csrf_token' => 'irrelevant',
            'action' => 'add_department',
            'name' => 'Unauthorized Department',
        ]);
        self::assertSame(302, $denied['status']);
        self::assertDatabaseCount('departments', 'name = ?', 'Unauthorized Department', 0);
    }

    public function testSettingsPostHandlesCategoryTypeComponentsAndPeopleActions(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'settings.php');

        $blankCategory = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item_category',
            'name' => ' ',
        ]);
        self::assertStringContainsString('Please enter a name for the category.', $blankCategory['body']);

        $itemCategory = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item_category',
            'name' => 'New Integration Category',
        ]);
        self::assertStringContainsString('Category "New Integration Category" added.', $itemCategory['body']);
        self::assertDatabaseCount('item_categories', 'name = ?', 'New Integration Category', 1);

        $blankType = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item_type',
            'name' => 'New Integration Type',
            'category_id' => 0,
        ]);
        self::assertStringContainsString('Please choose a category for this item type.', $blankType['body']);

        $newType = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_item_type',
            'name' => 'New Integration Type',
            'category_id' => $this->itemCategoryId,
        ]);
        self::assertStringContainsString('Item type "New Integration Type" added.', $newType['body']);
        self::assertDatabaseCount('item_types', 'name = ?', 'New Integration Type', 1);

        $components = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'set_item_type_components',
            'parent_type_id' => $this->itemTypeId,
            'component_type_ids[]' => '',
        ]);
        self::assertStringContainsString('Components for "Integration Router" updated.', $components['body']);

        $blankPerson = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_department_person',
            'department_id' => 0,
            'name' => '',
        ]);
        self::assertStringContainsString('Select a department and enter a name.', $blankPerson['body']);

        $addedPerson = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_department_person',
            'department_id' => $this->departmentId,
            'name' => 'New Integration Person',
        ]);
        self::assertStringContainsString('"New Integration Person" added.', $addedPerson['body']);
        self::assertDatabaseCount('department_people', 'name = ?', 'New Integration Person', 1);

        $personId = (int)self::$database->query("SELECT id FROM department_people WHERE name = 'New Integration Person'")->fetch_assoc()['id'];
        $deletedPerson = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_department_person',
            'id' => $personId,
        ]);
        self::assertStringContainsString('Faculty/staff removed.', $deletedPerson['body']);
        self::assertDatabaseCount('department_people', 'name = ?', 'New Integration Person', 0);
    }

    public function testSettingsDeleteActionsProtectReferencedRecordsAndDeleteUnusedOnes(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'settings.php');

        $usedCategory = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_item_category',
            'id' => $this->itemCategoryId,
        ]);
        self::assertStringContainsString('Cannot delete: there are item types using this category.', $usedCategory['body']);

        $usedType = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_item_type',
            'id' => $this->itemTypeId,
        ]);
        self::assertStringContainsString('Cannot delete: there are inventory items using this type.', $usedType['body']);

        $unusedCategory = self::$database->prepare('INSERT INTO item_categories (name) VALUES (?)');
        $unusedCategoryName = 'Unused Integration Category';
        $unusedCategory->bind_param('s', $unusedCategoryName);
        $unusedCategory->execute();
        $unusedCategoryId = (int)self::$database->insert_id;
        $deleteCategory = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_item_category',
            'id' => $unusedCategoryId,
        ]);
        self::assertStringContainsString('Category deleted.', $deleteCategory['body']);
        self::assertDatabaseCount('item_categories', 'name = ?', $unusedCategoryName, 0);

        $unusedType = self::$database->prepare('INSERT INTO item_types (name, category_id, created_by) VALUES (?, ?, ?)');
        $unusedTypeName = 'Unused Integration Type';
        $unusedType->bind_param('sii', $unusedTypeName, $this->itemCategoryId, $this->adminId);
        $unusedType->execute();
        $unusedTypeId = (int)self::$database->insert_id;
        $deleteType = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_item_type',
            'id' => $unusedTypeId,
        ]);
        self::assertStringContainsString('Item type deleted.', $deleteType['body']);
        self::assertDatabaseCount('item_types', 'name = ?', $unusedTypeName, 0);

        $deployment = self::$database->prepare('INSERT INTO deployments (item_id, department_id, deployed_date) VALUES (?, ?, ?)');
        $deploymentDate = date('Y-m-d');
        $deployment->bind_param('iis', $this->itemId, $this->departmentId, $deploymentDate);
        $deployment->execute();
        $deleteUsedDepartment = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_department',
            'id' => $this->departmentId,
        ]);
        self::assertStringContainsString('Cannot delete: there are deployment records for this department.', $deleteUsedDepartment['body']);
        self::assertDatabaseCount('departments', 'name = ?', 'Integration Department', 1);

        $newDepartment = self::$database->prepare('INSERT INTO departments (name) VALUES (?)');
        $departmentName = 'Unused Integration Department';
        $newDepartment->bind_param('s', $departmentName);
        $newDepartment->execute();
        $newDepartmentId = (int)self::$database->insert_id;
        $deleteDepartment = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_department',
            'id' => $newDepartmentId,
        ]);
        self::assertStringContainsString('Department deleted.', $deleteDepartment['body']);
        self::assertDatabaseCount('departments', 'name = ?', $departmentName, 0);
    }

    public function testSettingsWorklogCategoryActionsValidateAndDelete(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'settings.php');
        $blank = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_category',
            'name' => '',
        ]);
        self::assertStringContainsString('Please enter a category name.', $blank['body']);

        $added = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'add_category',
            'name' => 'Settings Integration Category',
        ]);
        self::assertStringContainsString('Category "Settings Integration Category" added.', $added['body']);
        self::assertDatabaseCount('categories', 'name = ?', 'Settings Integration Category', 1);

        $categoryId = (int)self::$database->query("SELECT id FROM categories WHERE name = 'Settings Integration Category'")->fetch_assoc()['id'];
        $deleted = $this->request($admin, 'settings.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_category',
            'id' => $categoryId,
        ]);
        self::assertStringContainsString('Category deleted.', $deleted['body']);
        self::assertDatabaseCount('categories', 'name = ?', 'Settings Integration Category', 0);
    }

    public function testStaffActionsPreventSelfChangesAndManageOtherUsers(): void
    {
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'staff.php');

        foreach ([
            ['toggle_status', "You can't disable your own account."],
            ['change_role', "You can't change your own role here."],
            ['delete_user', "You can't delete your own account."],
        ] as [$action, $message]) {
            $response = $this->request($admin, 'staff.php', 'POST', [
                'csrf_token' => $token,
                'action' => $action,
                'id' => $this->adminId,
            ]);
            self::assertStringContainsString($message, $response['body']);
        }

        $disabled = $this->request($admin, 'staff.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'toggle_status',
            'id' => $this->staffId,
        ]);
        self::assertStringContainsString('Account status updated.', $disabled['body']);
        self::assertSame('disabled', self::$database->query('SELECT status FROM users WHERE id = ' . $this->staffId)->fetch_assoc()['status']);

        $changedRole = $this->request($admin, 'staff.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'change_role',
            'id' => $this->staffId,
            'role' => 'admin',
        ]);
        self::assertStringContainsString('Role updated.', $changedRole['body']);
        self::assertSame('admin', self::$database->query('SELECT role FROM users WHERE id = ' . $this->staffId)->fetch_assoc()['role']);

        $deleted = $this->request($admin, 'staff.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'delete_user',
            'id' => $this->staffId,
        ]);
        self::assertStringContainsString('Account deleted.', $deleted['body']);
        self::assertDatabaseCount('users', 'username = ?', 'integration-staff', 0);
    }

    public function testExistingSessionLosesAdminAccessAfterRoleDemotion(): void
    {
        $client = $this->login($this->newClient());
        $roleChange = self::$database->prepare("UPDATE users SET role = 'staff' WHERE id = ?");
        $roleChange->bind_param('i', $this->adminId);
        $roleChange->execute();

        $adminPage = $this->request($client, 'settings.php');
        self::assertSame(302, $adminPage['status']);
        self::assertStringContainsString('Location: index.php?err=forbidden', $adminPage['headers']);

        $regularPage = $this->request($client, 'worklog.php');
        self::assertSame(200, $regularPage['status']);
        self::assertStringContainsString('role-staff', $regularPage['body']);
        self::assertStringNotContainsString('role-admin', $regularPage['body']);
    }

    public function testExistingSessionIsRevokedWhenAccountIsDisabledOrDeleted(): void
    {
        $disabledClient = $this->login($this->newClient(), 'integration-staff');
        $disable = self::$database->prepare("UPDATE users SET status = 'disabled' WHERE id = ?");
        $disable->bind_param('i', $this->staffId);
        $disable->execute();

        $disabledResponse = $this->request($disabledClient, 'worklog.php');
        self::assertSame(302, $disabledResponse['status']);
        self::assertStringContainsString('Location: login.php', $disabledResponse['headers']);

        $secondStaff = self::$database->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES ('Deleted Session User', 'deleted-session-user', ?, 'staff', 'active')");
        $passwordHash = password_hash('Test-Only-Password-90210', PASSWORD_DEFAULT);
        $secondStaff->bind_param('s', $passwordHash);
        $secondStaff->execute();
        $deletedUserId = (int)self::$database->insert_id;
        $deletedClient = $this->login($this->newClient(), 'deleted-session-user');

        $delete = self::$database->prepare('DELETE FROM users WHERE id = ?');
        $delete->bind_param('i', $deletedUserId);
        $delete->execute();

        $deletedResponse = $this->request($deletedClient, 'worklog.php');
        self::assertSame(302, $deletedResponse['status']);
        self::assertStringContainsString('Location: login.php', $deletedResponse['headers']);
    }

    public function testDisposalReportStatusPostUpdatesOnlyAllowedStatusForAdmin(): void
    {
        $flag = self::$database->prepare('INSERT INTO inventory_disposal_flags (item_id, flagged_date, last_activity_date, days_inactive) VALUES (?, ?, ?, ?)');
        $flagDate = date('Y-m-d');
        $daysInactive = 45;
        $flag->bind_param('issi', $this->itemId, $flagDate, $flagDate, $daysInactive);
        $flag->execute();
        $flagId = (int)self::$database->insert_id;
        $admin = $this->login($this->newClient());
        $token = $this->csrfToken($admin, 'reports.php');

        $invalid = $this->request($admin, 'reports.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'update_disposal_status',
            'flag_id' => $flagId,
            'new_status' => 'pending',
        ]);
        self::assertSame(302, $invalid['status']);
        self::assertSame('pending', self::$database->query('SELECT status FROM inventory_disposal_flags WHERE id = ' . $flagId)->fetch_assoc()['status']);

        $valid = $this->request($admin, 'reports.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'update_disposal_status',
            'flag_id' => $flagId,
            'new_status' => 'reviewed',
        ]);
        self::assertSame(302, $valid['status']);
        self::assertSame('reviewed', self::$database->query('SELECT status FROM inventory_disposal_flags WHERE id = ' . $flagId)->fetch_assoc()['status']);

        $staff = $this->login($this->newClient(), 'integration-staff');
        $denied = $this->request($staff, 'reports.php', 'POST', [
            'action' => 'update_disposal_status',
            'flag_id' => $flagId,
            'new_status' => 'disposed',
        ]);
        self::assertSame(200, $denied['status']);
        self::assertSame('reviewed', self::$database->query('SELECT status FROM inventory_disposal_flags WHERE id = ' . $flagId)->fetch_assoc()['status']);
    }

    public function testReportStatusPostRejectsMissingCsrfToken(): void
    {
        $flag = self::$database->prepare('INSERT INTO inventory_disposal_flags (item_id, flagged_date, last_activity_date, days_inactive) VALUES (?, ?, ?, ?)');
        $flagDate = date('Y-m-d');
        $daysInactive = 45;
        $flag->bind_param('issi', $this->itemId, $flagDate, $flagDate, $daysInactive);
        $flag->execute();
        $flagId = (int)self::$database->insert_id;
        $admin = $this->login($this->newClient());

        $response = $this->request($admin, 'reports.php', 'POST', [
            'action' => 'update_disposal_status',
            'flag_id' => $flagId,
            'new_status' => 'reviewed',
        ]);

        self::assertSame(419, $response['status']);
        self::assertSame('pending', self::$database->query('SELECT status FROM inventory_disposal_flags WHERE id = ' . $flagId)->fetch_assoc()['status']);
    }

    public function testReportStatusFormsIncludeCsrfToken(): void
    {
        $flag = self::$database->prepare('INSERT INTO inventory_disposal_flags (item_id, flagged_date, last_activity_date, days_inactive) VALUES (?, ?, ?, ?)');
        $flagDate = date('Y-m-d');
        $daysInactive = 45;
        $flag->bind_param('issi', $this->itemId, $flagDate, $flagDate, $daysInactive);
        $flag->execute();

        $admin = $this->login($this->newClient());
        $response = $this->request($admin, 'reports.php');
        self::assertSame(200, $response['status']);
        self::assertSame(2, preg_match_all(
            '/<form method="POST"[^>]*>.*?<input type="hidden" name="csrf_token" value="[a-f0-9]{64}"/s',
            $response['body']
        ));
    }

    public function testDepartmentsReturnPostUsesIndependentRoute(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $deployment = $this->jsonResponse($this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'csrf_token' => $token,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'mr_number' => 'IT-MR-DEPT-RETURN',
            'cart' => json_encode([[
                'item_id' => $this->itemId,
                'po_number' => 'IT-PO-001',
                'pn_number' => 'IT-PN-1',
            ]]),
        ]));
        self::assertTrue($deployment['ok']);
        $deploymentId = (int)self::$database->query("SELECT id FROM deployments WHERE mr_number = 'IT-MR-DEPT-RETURN'")->fetch_assoc()['id'];

        $response = $this->request($client, 'departments.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'mark_returned',
            'deployment_id' => $deploymentId,
            'confirm_username' => 'integration-admin',
            'returned_date' => date('Y-m-d'),
            'returned_time' => date('H:i'),
            'back_department_id' => $this->departmentId,
            'back_staff' => 'Integration Recipient',
        ]);
        self::assertSame(302, $response['status']);
        self::assertStringContainsString('department_id=' . $this->departmentId, $response['headers']);
        self::assertSame(date('Y-m-d'), self::$database->query('SELECT returned_date FROM deployments WHERE id = ' . $deploymentId)->fetch_assoc()['returned_date']);
    }

    public function testReturnEndpointRejectsBadConfirmationAndCompletesReturn(): void
    {
        $client = $this->login($this->newClient());
        $token = $this->csrfToken($client, 'deployments.php');
        $deploy = $this->jsonResponse($this->request($client, 'deployments.php', 'POST', [
            'action' => 'submit_deployment_cart',
            'csrf_token' => $token,
            'department_id' => $this->departmentId,
            'recipient_name' => 'Integration Recipient',
            'mr_number' => 'IT-MR-RETURN',
            'cart' => json_encode([[
                'item_id' => $this->itemId,
                'po_number' => 'IT-PO-001',
                'pn_number' => 'IT-PN-1',
            ]]),
        ]));
        self::assertTrue($deploy['ok']);
        $deploymentId = (int)self::$database->query("SELECT id FROM deployments WHERE mr_number = 'IT-MR-RETURN'")->fetch_assoc()['id'];

        $badConfirmation = $this->request($client, 'deployments.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'mark_returned',
            'deployment_id' => $deploymentId,
            'confirm_username' => 'wrong-user',
        ]);
        self::assertStringContainsString('the username typed did not match', $badConfirmation['body']);
        self::assertDatabaseCount('deployments', 'returned_date = ?', date('Y-m-d'), 0);

        $success = $this->request($client, 'deployments.php', 'POST', [
            'csrf_token' => $token,
            'action' => 'mark_returned',
            'deployment_id' => $deploymentId,
            'confirm_username' => 'integration-admin',
            'returned_date' => date('Y-m-d'),
            'returned_time' => date('H:i'),
        ]);
        self::assertSame(302, $success['status']);
        self::assertSame(date('Y-m-d'), self::$database->query('SELECT returned_date FROM deployments WHERE id = ' . $deploymentId)->fetch_assoc()['returned_date']);
    }

    public function testCsrfProtectsStateChangingPost(): void
    {
        $client = $this->login($this->newClient());
        $response = $this->request($client, 'worklog.php', 'POST', [
            'action' => 'add_category',
            'category_name' => 'No CSRF Category',
        ]);
        self::assertSame(419, $response['status']);
        self::assertDatabaseCount('categories', 'name = ?', 'No CSRF Category', 0);
    }
}
