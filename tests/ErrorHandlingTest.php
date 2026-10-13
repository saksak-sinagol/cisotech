<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/error_handling.php';

final class ErrorHandlingTest extends TestCase
{
    public function testStructuredErrorRecordContainsRequestContextButNoMessageOrRequestData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/account.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = ['password' => 'do-not-log-this', 'csrf_token' => 'also-private'];
        $_SESSION['user_id'] = 42;

        $record = app_error_log_record('error', 'uncaught_exception', 'RuntimeException', '/app/account.php', 25, 503);

        self::assertSame('POST', $record['method']);
        self::assertSame('account.php', $record['route']);
        self::assertSame(42, $record['user_id']);
        self::assertSame(503, $record['error_code']);
        self::assertArrayNotHasKey('message', $record);
        self::assertArrayNotHasKey('password', $record);
        self::assertArrayNotHasKey('csrf_token', $record);
        self::assertStringNotContainsString('do-not-log-this', json_encode($record));
        self::assertStringNotContainsString('also-private', json_encode($record));
    }

    public function testUncaughtExceptionShowsGenericHtmlAndDoesNotExposeExceptionMessage(): void
    {
        $output = $this->runInChildProcess(
            "require_once " . var_export(dirname(__DIR__) . '/includes/error_handling.php', true) . ";"
            . "register_app_error_handlers();"
            . "throw new RuntimeException('password=private-password csrf_token=private-token');"
        );

        self::assertStringContainsString('We could not complete your request', $output);
        self::assertStringContainsString('Reference:', $output);
        self::assertStringNotContainsString('******', $output);
        self::assertStringNotContainsString('private-password', $output);
        self::assertStringNotContainsString('private-token', $output);
    }

    public function testUnexpectedAjaxFailureUsesFriendlyJsonResponse(): void
    {
        $output = $this->runInChildProcess(
            "require_once " . var_export(dirname(__DIR__) . '/includes/error_handling.php', true) . ";"
            . "\$_SERVER['SCRIPT_NAME'] = '/deployments.php';"
            . "\$_SERVER['REQUEST_METHOD'] = 'GET';"
            . "\$_GET['ajax'] = 'items_by_category';"
            . "app_render_server_error();"
        );
        $payload = json_decode($output, true);

        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
        self::assertSame('We could not complete your request. Please try again later.', $payload['error']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $payload['request_id']);
    }

    private function runInChildProcess(string $code): string
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'cisotech-error-test-');
        self::assertNotFalse($scriptPath);
        file_put_contents($scriptPath, "<?php\n" . $code);

        $pipes = [];
        try {
            $process = proc_open(
                [PHP_BINARY, $scriptPath],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            self::assertSame(0, $exitCode, $error);
            return $output;
        } finally {
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }
}
