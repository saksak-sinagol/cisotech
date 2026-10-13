<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/auth.php';

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testUnauthenticatedSessionHasNoUserOrPrivileges(): void
    {
        self::assertFalse(is_logged_in());
        self::assertNull(current_user());
        self::assertFalse(is_admin());
        self::assertFalse(is_superadmin());
        self::assertFalse(can_use_deployments());
    }

    public function testAdminAndSuperadminRolesHaveExpectedPrivileges(): void
    {
        $_SESSION = [
            'user_id' => '12',
            'full_name' => 'Test Admin',
            'username' => 'test-admin',
            'role' => 'admin',
        ];

        self::assertTrue(is_logged_in());
        self::assertSame(
            ['id' => 12, 'full_name' => 'Test Admin', 'username' => 'test-admin', 'role' => 'admin'],
            current_user()
        );
        self::assertTrue(is_admin());
        self::assertFalse(is_superadmin());
        self::assertTrue(can_use_deployments());

        $_SESSION['role'] = 'superadmin';
        self::assertTrue(is_admin());
        self::assertTrue(is_superadmin());

        $_SESSION['role'] = 'staff';
        self::assertFalse(is_admin());
        self::assertFalse(is_superadmin());
    }

    public function testCsrfTokenIsSessionBoundAndFieldContainsSameToken(): void
    {
        $token = csrf_token();
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        self::assertSame($token, csrf_token());
        self::assertTrue(verify_csrf_token($token));
        self::assertStringContainsString('value="' . $token . '"', csrf_field());
    }

    public function testCsrfValidationRejectsMissingWrongAndNonStringTokens(): void
    {
        csrf_token();
        self::assertFalse(verify_csrf_token(''));
        self::assertFalse(verify_csrf_token('incorrect'));
        self::assertFalse(verify_csrf_token(['not', 'a', 'token']));
    }

    public function testHtmlEscapingProtectsTextAndQuotes(): void
    {
        self::assertSame('&lt;script&gt;&quot;&amp;&#039;&lt;/script&gt;', h('<script>"&\'</script>'));
        self::assertSame('', h(null));
    }

    public function testAuthorizationGuardsStopUnauthorizedRolesAndAllowPermittedRoles(): void
    {
        $cases = [
            ['require_admin_for_write', ['role' => 'staff'], false],
            ['require_admin_for_write', ['role' => 'admin'], false],
        ];

        foreach ($cases as [$guard, $session, $authorized]) {
            $output = $this->runGuardInChildProcess($guard, $session);
            self::assertSame(
                $authorized ? 'AUTHORIZED' : '',
                $output,
                $guard . ' with session ' . json_encode($session)
            );
        }
    }

    public function testCsrfGuardStopsInvalidTokenAndAllowsValidToken(): void
    {
        self::assertSame(
            'CSRF token validation failed.',
            $this->runGuardInChildProcess('require_csrf_token', [], [])
        );
        self::assertSame(
            'AUTHORIZED',
            $this->runGuardInChildProcess(
                'require_csrf_token',
                ['csrf_token' => 'valid-test-token'],
                ['csrf_token' => 'valid-test-token']
            )
        );
    }

    private function runGuardInChildProcess(string $guard, array $session, array $post = []): string
    {
        $authPath = var_export(dirname(__DIR__) . '/includes/auth.php', true);
        $sessionLiteral = var_export($session, true);
        $postLiteral = var_export($post, true);
        $code = "require_once $authPath; \$_SESSION = $sessionLiteral; \$_POST = $postLiteral; $guard(); echo 'AUTHORIZED';";
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
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
    }
}
