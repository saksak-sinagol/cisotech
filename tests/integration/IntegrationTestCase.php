<?php

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected static mysqli $database;
    protected static string $baseUrl;
    protected int $adminId;
    protected int $staffId;
    protected int $departmentId;
    protected int $itemTypeId;
    protected int $itemId;
    protected int $batchId;
    protected int $categoryId;
    protected int $itemCategoryId;

    private array $cookieFiles = [];

    public static function setUpBeforeClass(): void
    {
        $baseUrl = getenv('CISOTECH_TEST_BASE_URL');
        $host = getenv('CISOTECH_TEST_DB_HOST');
        $port = getenv('CISOTECH_TEST_DB_PORT');
        $name = getenv('CISOTECH_TEST_DB_NAME');
        $user = getenv('CISOTECH_TEST_DB_USER');
        $password = getenv('CISOTECH_TEST_DB_PASS');
        if (!$baseUrl || !$host || !$port || !$name || $user === false || $password === false) {
            self::fail('Integration tests require CISOTECH_TEST_BASE_URL and CISOTECH_TEST_DB_* environment variables.');
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true)) {
            self::fail('CISOTECH_TEST_BASE_URL must target localhost or a loopback address.');
        }
        if (!preg_match('/^cisotech_test_[a-z0-9_]+$/', $name)) {
            self::fail('CISOTECH_TEST_DB_NAME must start with cisotech_test_.');
        }
        if (app_env('DB_NAME') === $name) {
            self::fail('The integration database must not be the application database.');
        }

        self::$baseUrl = rtrim($baseUrl, '/');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            self::$database = new mysqli($host, $user, $password, $name, (int)$port);
        } catch (mysqli_sql_exception $exception) {
            self::fail('Could not connect to the isolated integration database: ' . $exception->getMessage());
        }
        self::$database->set_charset('utf8mb4');

        $check = self::$database->query("SHOW TABLES LIKE 'users'");
        if ($check->num_rows !== 1) {
            self::fail('The integration database is missing the project schema; import database.sql first.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $tablesResult = self::$database->query('SHOW TABLES');
        $tables = [];
        while ($row = $tablesResult->fetch_row()) {
            $tables[] = '`' . str_replace('`', '``', $row[0]) . '`';
        }
        self::$database->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            self::$database->query('TRUNCATE TABLE ' . $table);
        }
        self::$database->query('SET FOREIGN_KEY_CHECKS = 1');

        $passwordHash = password_hash('Test-Only-Password-90210', PASSWORD_DEFAULT);
        $admin = self::$database->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $adminName = 'Integration Admin';
        $adminUsername = 'integration-admin';
        $admin->bind_param('sss', $adminName, $adminUsername, $passwordHash);
        $admin->execute();
        $this->adminId = (int)self::$database->insert_id;

        $staff = self::$database->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, 'staff', 'active')");
        $staffName = 'Integration Staff';
        $staffUsername = 'integration-staff';
        $staff->bind_param('sss', $staffName, $staffUsername, $passwordHash);
        $staff->execute();
        $this->staffId = (int)self::$database->insert_id;

        $categoryName = 'Integration Category';
        $category = self::$database->prepare('INSERT INTO categories (name) VALUES (?)');
        $category->bind_param('s', $categoryName);
        $category->execute();
        $this->categoryId = (int)self::$database->insert_id;

        $departmentName = 'Integration Department';
        $department = self::$database->prepare('INSERT INTO departments (name) VALUES (?)');
        $department->bind_param('s', $departmentName);
        $department->execute();
        $this->departmentId = (int)self::$database->insert_id;

        $person = self::$database->prepare('INSERT INTO department_people (department_id, name) VALUES (?, ?)');
        $personName = 'Integration Recipient';
        $person->bind_param('is', $this->departmentId, $personName);
        $person->execute();

        $itemCategoryName = 'Integration Hardware';
        $itemCategory = self::$database->prepare('INSERT INTO item_categories (name) VALUES (?)');
        $itemCategory->bind_param('s', $itemCategoryName);
        $itemCategory->execute();
        $this->itemCategoryId = (int)self::$database->insert_id;

        $typeName = 'Integration Router';
        $itemType = self::$database->prepare('INSERT INTO item_types (name, category_id, created_by) VALUES (?, ?, ?)');
        $itemType->bind_param('sii', $typeName, $this->itemCategoryId, $this->adminId);
        $itemType->execute();
        $this->itemTypeId = (int)self::$database->insert_id;

        $itemName = 'Integration Router Model';
        $quantity = 8;
        $threshold = 1;
        $date = date('Y-m-d');
        $item = self::$database->prepare('INSERT INTO inventory_items (item_name, item_type_id, quantity_total, low_stock_threshold, date_added, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $item->bind_param('siiisi', $itemName, $this->itemTypeId, $quantity, $threshold, $date, $this->adminId);
        $item->execute();
        $this->itemId = (int)self::$database->insert_id;

        $poNumber = 'IT-PO-001';
        $pnNumber = 'IT-PN-';
        $time = date('H:i:s');
        $notes = 'Integration fixture';
        $batch = self::$database->prepare('INSERT INTO inventory_batches (item_id, quantity, batch_date, batch_time, notes, pn_number, po_number, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $batch->bind_param('iisssssi', $this->itemId, $quantity, $date, $time, $notes, $pnNumber, $poNumber, $this->adminId);
        $batch->execute();
        $this->batchId = (int)self::$database->insert_id;

        $serial = self::$database->prepare('INSERT INTO inventory_batch_serials (batch_id, serial_number, pn_number) VALUES (?, ?, ?)');
        for ($index = 1; $index <= $quantity; $index++) {
            $serialNumber = 'IT-SN-' . $index;
            $unitPn = 'IT-PN-' . $index;
            $serial->bind_param('iss', $this->batchId, $serialNumber, $unitPn);
            $serial->execute();
        }
    }

    protected function newClient(): array
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cisotech-cookie-');
        $this->cookieFiles[] = $cookieFile;
        return ['cookie' => $cookieFile];
    }

    protected function login(array $client, string $username = 'integration-admin'): array
    {
        $response = $this->request($client, 'login.php', 'POST', [
            'username' => $username,
            'password' => 'Test-Only-Password-90210',
        ]);
        self::assertSame(302, $response['status'], $response['body']);
        return $client;
    }

    protected function csrfToken(array $client, string $page): string
    {
        $response = $this->request($client, $page);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertSame(1, preg_match('/(?:name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']|fd\.append\(["\']csrf_token["\'],\s*["\']([^"\']+)["\']\))/i', $response['body'], $matches), 'Expected a CSRF token on ' . $page);
        return html_entity_decode($matches[1] ?: $matches[2], ENT_QUOTES, 'UTF-8');
    }

    protected function request(array $client, string $path, string $method = 'GET', array $data = [], array $headers = []): array
    {
        $handle = curl_init(self::$baseUrl . '/' . ltrim($path, '/'));
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => $client['cookie'],
            CURLOPT_COOKIEJAR => $client['cookie'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($headers !== []) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        if ($raw === false) {
            $message = curl_error($handle);
            curl_close($handle);
            self::fail('Integration HTTP request failed: ' . $message);
        }
        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        if (!str_contains(strtolower($headers), 'application/json')) {
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    protected function jsonResponse(array $response): array
    {
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringContainsString('application/json', strtolower($response['headers']));
        $decoded = json_decode($response['body'], true);
        self::assertIsArray($decoded, $response['body']);
        return $decoded;
    }

    protected function assertDatabaseCount(string $table, string $where, string $value, int $expected): void
    {
        $statement = self::$database->prepare("SELECT COUNT(*) AS total FROM `$table` WHERE $where");
        $statement->bind_param('s', $value);
        $statement->execute();
        $actual = (int)$statement->get_result()->fetch_assoc()['total'];
        self::assertSame($expected, $actual);
    }

    protected function tearDown(): void
    {
        foreach ($this->cookieFiles as $cookieFile) {
            if (is_file($cookieFile)) {
                unlink($cookieFile);
            }
        }
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$database)) {
            self::$database->close();
        }
    }
}
