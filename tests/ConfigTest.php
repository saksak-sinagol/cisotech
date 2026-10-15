<?php

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testFontLinksEscapeEnvironmentProvidedUrls(): void
    {
        $this->withEnvironment([
            'FONT_PRECONNECT_URL' => 'https://fonts.example.test/?a=1&b=2',
            'FONT_STYLESHEET_URL' => 'https://fonts.example.test/css?family=Example&display=swap',
        ], function (): void {
            $links = render_font_links();
            self::assertStringContainsString(
                'href="https://fonts.example.test/?a=1&amp;b=2"',
                $links
            );
            self::assertStringContainsString(
                'href="https://fonts.example.test/css?family=Example&amp;display=swap"',
                $links
            );
        });
    }

    public function testFontLinksCanBeDisabled(): void
    {
        $this->withEnvironment([
            'FONT_PRECONNECT_URL' => '',
            'FONT_STYLESHEET_URL' => '',
        ], function (): void {
            self::assertSame('', render_font_links());
        });
    }

    public function testRequiredEnvironmentValueThrowsWhenMissing(): void
    {
        $this->withEnvironment(['CISOTECH_TEST_REQUIRED' => null], function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Required environment setting is missing: CISOTECH_TEST_REQUIRED');
            app_env_required('CISOTECH_TEST_REQUIRED');
        });
    }

    public function testEnvironmentFileDoesNotOverrideProcessEnvironment(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cisotech-env-test-');
        self::assertNotFalse($path);
        $names = ['CISOTECH_TEST_DOTENV_VALUE', 'CISOTECH_TEST_PROCESS_VALUE'];
        $original = [];
        foreach ($names as $name) {
            $original[$name] = [
                'environment' => getenv($name),
                'env' => $_ENV[$name] ?? null,
                'has_env' => array_key_exists($name, $_ENV),
                'server' => $_SERVER[$name] ?? null,
                'has_server' => array_key_exists($name, $_SERVER),
            ];
        }

        try {
            putenv($names[0]);
            unset($_ENV[$names[0]], $_SERVER[$names[0]]);
            putenv($names[1] . '=from-process');
            $_ENV[$names[1]] = 'from-process';
            $_SERVER[$names[1]] = 'from-process';
            self::assertNotFalse(file_put_contents(
                $path,
                $names[0] . '="from-file"' . "\n" . $names[1] . '="also-from-file"' . "\n"
            ));

            app_load_env_file($path);

            self::assertSame('from-file', app_env($names[0]));
            self::assertSame('from-process', app_env($names[1]));
        } finally {
            foreach ($original as $name => $values) {
                putenv($name . ($values['environment'] === false ? '' : '=' . $values['environment']));
                if ($values['has_env']) {
                    $_ENV[$name] = $values['env'];
                } else {
                    unset($_ENV[$name]);
                }
                if ($values['has_server']) {
                    $_SERVER[$name] = $values['server'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testBootstrapCredentialsEnforceMinimumStrengthAndRealUsername(): void
    {
        $this->withEnvironment([
            'CISOTECH_TEST_USERNAME' => 'integration-admin',
            'CISOTECH_TEST_PASSWORD' => 'a-strong-test-password',
        ], function (): void {
            self::assertSame('integration-admin', app_bootstrap_username('CISOTECH_TEST_USERNAME'));
            self::assertSame('a-strong-test-password', app_bootstrap_password('CISOTECH_TEST_PASSWORD'));
        });

        $this->withEnvironment(['CISOTECH_TEST_PASSWORD' => 'short'], function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('must be a unique password of at least 16 characters');
            app_bootstrap_password('CISOTECH_TEST_PASSWORD');
        });
    }

    public function testBootstrapCredentialsRejectPlaceholders(): void
    {
        $this->withEnvironment([
            'CISOTECH_TEST_USERNAME' => 'replace_with_username',
            'CISOTECH_TEST_PASSWORD' => 'replace_with_a_password_that_is_long_enough',
        ], function (): void {
            try {
                app_bootstrap_username('CISOTECH_TEST_USERNAME');
                self::fail('Placeholder username should be rejected.');
            } catch (RuntimeException $exception) {
                self::assertSame('CISOTECH_TEST_USERNAME must be set to a real username.', $exception->getMessage());
            }

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('must be a unique password of at least 16 characters');
            app_bootstrap_password('CISOTECH_TEST_PASSWORD');
        });
    }

    private function withEnvironment(array $values, callable $callback): void
    {
        $original = [];
        foreach ($values as $name => $value) {
            $original[$name] = getenv($name);
            putenv($name . ($value === null ? '' : '=' . $value));
        }

        try {
            $callback();
        } finally {
            foreach ($original as $name => $value) {
                putenv($name . ($value === false ? '' : '=' . $value));
            }
        }
    }
}
