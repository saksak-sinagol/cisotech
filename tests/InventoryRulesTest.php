<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/inventory_rules.php';

final class InventoryRulesTest extends TestCase
{
    public function testNormalizesRestockQuantitiesToAtLeastOne(): void
    {
        self::assertSame(3, normalize_inventory_quantity('3'));
        self::assertSame(1, normalize_inventory_quantity('0'));
        self::assertSame(1, normalize_inventory_quantity('-4'));
        self::assertSame(1, normalize_inventory_quantity('not-a-number'));
    }

    public function testLowStockThresholdCanBeZeroButNotNegative(): void
    {
        self::assertSame(5, normalize_low_stock_threshold());
        self::assertSame(0, normalize_low_stock_threshold('0'));
        self::assertSame(0, normalize_low_stock_threshold('-2'));
    }

    public function testTrimsOnePropertyNumberForEachUnit(): void
    {
        self::assertSame(
            ['valid' => true, 'error' => null, 'unit_pns' => ['PN-001', 'PN-002']],
            validate_unit_property_numbers([' PN-001 ', 'PN-002'], 2)
        );
    }

    public function testRejectsMissingPropertyNumberForAnyUnit(): void
    {
        self::assertSame(
            [
                'valid' => false,
                'error' => 'A PN Number is required for every unit.',
                'unit_pns' => [],
            ],
            validate_unit_property_numbers(['PN-001', '  '], 2)
        );
    }

    public function testSummarizesNewAndRestockedItems(): void
    {
        self::assertSame(
            ['added' => 2, 'restocked' => 1, 'message' => '2 new items added and 1 item restocked'],
            summarize_inventory_restock_results([
                ['is_restock' => false],
                ['is_restock' => true],
                ['is_restock' => false],
            ])
        );
    }

    public function testEmptyRestockSummaryHasNoMessage(): void
    {
        self::assertSame(
            ['added' => 0, 'restocked' => 0, 'message' => ''],
            summarize_inventory_restock_results([])
        );
    }
}
