<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/spec_schema.php';

final class SpecSchemaTest extends TestCase
{
    public function testRecognizesSupportedTypeNamesCaseInsensitivelyAndWithWhitespace(): void
    {
        self::assertSame('pc', spec_schema_key_for_type_name('  pc/system unit '));
        self::assertSame('laptop', spec_schema_key_for_type_name('LAPTOP'));
        self::assertSame('projector', spec_schema_key_for_type_name('Projector'));
    }

    public function testRejectsUnknownAndNonStringTypeNames(): void
    {
        self::assertNull(spec_schema_key_for_type_name('desktop'));
        self::assertNull(spec_schema_key_for_type_name(null));
        self::assertNull(spec_schema_key_for_type_name(['projector']));
    }

    public function testSupportedSchemaFieldsHaveLabelsAndHints(): void
    {
        foreach (SPEC_SCHEMA as $kind => $fields) {
            self::assertNotEmpty($fields, $kind);
            foreach ($fields as $key => $metadata) {
                self::assertNotSame('', $key);
                self::assertCount(2, $metadata);
                self::assertNotSame('', $metadata[0]);
                self::assertNotSame('', $metadata[1]);
            }
        }
    }
}
