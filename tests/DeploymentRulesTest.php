<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/deployment_rules.php';

final class DeploymentRulesTest extends TestCase
{
    private array $item = [
        'item_name' => 'Router',
        'quantity_total' => 8,
        'deployed_qty' => 3,
    ];

    public function testAvailableQuantityNeverRoundsNumericDatabaseValues(): void
    {
        self::assertSame(5, available_inventory_quantity('8', '3'));
        self::assertSame(0, available_inventory_quantity(4, 4));
        self::assertSame(-2, available_inventory_quantity(3, 5));
    }

    public function testAcceptsDeploymentAtExactAvailableQuantity(): void
    {
        self::assertNull(deployment_validation_error($this->item, 2, 'PO-10', 'PN-10', 5));
    }

    public function testRejectsMissingItemOrDepartment(): void
    {
        self::assertSame(
            'Please select a valid item and department.',
            deployment_validation_error(null, 2, 'PO-10', 'PN-10', 1)
        );
        self::assertSame(
            'Please select a valid item and department.',
            deployment_validation_error($this->item, 0, 'PO-10', 'PN-10', 1)
        );
    }

    public function testRejectsMissingPurchaseOrPropertyNumber(): void
    {
        self::assertSame(
            'PO Number is required for every item.',
            deployment_validation_error($this->item, 2, '', 'PN-10', 1)
        );
        self::assertSame(
            'PN Number is required.',
            deployment_validation_error($this->item, 2, 'PO-10', '', 1)
        );
    }

    public function testRejectsQuantityAboveAvailableStock(): void
    {
        self::assertSame(
            'Only 5 unit(s) of "Router" left to deploy.',
            deployment_validation_error($this->item, 2, 'PO-10', 'PN-10', 6)
        );
        self::assertSame(
            'Only 0 unit(s) of "Router" left to deploy.',
            deployment_validation_error(['item_name' => 'Router', 'quantity_total' => 4, 'deployed_qty' => 4], 2, 'PO-10', 'PN-10', 1)
        );
    }

    public function testOnlyAllowsKnownDeploymentTypes(): void
    {
        self::assertSame('new', normalize_deployment_type('new'));
        self::assertSame('replacement', normalize_deployment_type('replacement'));
        self::assertSame('new', normalize_deployment_type('pullout'));
        self::assertSame('new', normalize_deployment_type(null));
    }

    public function testPlansFifoBatchConsumptionAndSkipsEmptyBatches(): void
    {
        self::assertSame(
            [
                'allocations' => [
                    ['batch_id' => 10, 'quantity' => 2],
                    ['batch_id' => 12, 'quantity' => 3],
                ],
                'remaining' => 0,
            ],
            plan_deployment_batch_consumption(5, [
                ['id' => 10, 'batch_left' => 2],
                ['id' => 11, 'batch_left' => 0],
                ['id' => 12, 'batch_left' => 4],
            ])
        );
    }

    public function testReportsRemainingQuantityWhenBatchStockIsInsufficient(): void
    {
        self::assertSame(
            [
                'allocations' => [['batch_id' => 10, 'quantity' => 2]],
                'remaining' => 1,
            ],
            plan_deployment_batch_consumption(3, [
                ['id' => 10, 'batch_left' => 2],
                ['id' => 11, 'batch_left' => -4],
            ])
        );
    }

    public function testZeroQuantityNeedsNoBatchAllocations(): void
    {
        self::assertSame(
            ['allocations' => [], 'remaining' => 0],
            plan_deployment_batch_consumption(0, [['id' => 1, 'batch_left' => 4]])
        );
    }
}
