<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/http_security.php';

final class HttpSecurityTest extends TestCase
{
    public function testRequestStringsRejectArraysAndEnforceByteLimits(): void
    {
        self::assertSame('Inventory', request_string(['name' => ' Inventory '], 'name', 20));
        self::assertNull(request_string(['name' => ['unexpected']], 'name', 20));
        self::assertNull(request_string(['name' => str_repeat('x', 21)], 'name', 20));
        self::assertSame('', request_string([], 'name', 20, false));
        self::assertFalse(request_strings_are_valid(['name' => ['unexpected']], ['name' => 20]));
    }

    public function testRequestIntegerValidatorsRejectMalformedAndOutOfRangeValues(): void
    {
        self::assertSame(12, request_positive_int(['id' => '12'], 'id'));
        self::assertNull(request_positive_int(['id' => '0'], 'id'));
        self::assertNull(request_positive_int(['id' => '12abc'], 'id'));
        self::assertNull(request_positive_int(['id' => ['12']], 'id'));
        self::assertSame(0, request_nonnegative_int(['id' => 0], 'id'));
        self::assertNull(request_nonnegative_int(['id' => '-1'], 'id'));
    }

    public function testDateAndTimeValidatorsRejectImpossibleValues(): void
    {
        self::assertTrue(valid_request_date('2024-02-29'));
        self::assertFalse(valid_request_date('2023-02-29'));
        self::assertFalse(valid_request_date('2024-2-09'));
        self::assertTrue(valid_request_time('23:59'));
        self::assertTrue(valid_request_time('00:00:00'));
        self::assertFalse(valid_request_time('24:00'));
        self::assertFalse(valid_request_time('9:00'));
    }
}
