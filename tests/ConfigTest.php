<?php

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testFontLinksEscapeEnvironmentProvidedUrls(): void
    {
        $originalPreconnect = getenv('FONT_PRECONNECT_URL');
        $originalStylesheet = getenv('FONT_STYLESHEET_URL');

        putenv('FONT_PRECONNECT_URL=https://fonts.example.test/?a=1&b=2');
        putenv('FONT_STYLESHEET_URL=https://fonts.example.test/css?family=Example&display=swap');

        try {
            $links = render_font_links();
            self::assertStringContainsString(
                'href="https://fonts.example.test/?a=1&amp;b=2"',
                $links
            );
            self::assertStringContainsString(
                'href="https://fonts.example.test/css?family=Example&amp;display=swap"',
                $links
            );
        } finally {
            putenv('FONT_PRECONNECT_URL' . ($originalPreconnect === false ? '' : '=' . $originalPreconnect));
            putenv('FONT_STYLESHEET_URL' . ($originalStylesheet === false ? '' : '=' . $originalStylesheet));
        }
    }
}
