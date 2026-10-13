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
