<?php

function normalize_inventory_quantity($quantity): int
{
    return max(1, (int)$quantity);
}

function normalize_low_stock_threshold($threshold = 5): int
{
    return max(0, (int)$threshold);
}

function validate_unit_property_numbers(array $unitPns, int $quantity): array
{
    $normalizedPns = [];
    for ($unit = 0; $unit < $quantity; $unit++) {
        $pn = trim((string)($unitPns[$unit] ?? ''));
        if ($pn === '') {
            return [
                'valid' => false,
                'error' => 'A PN Number is required for every unit.',
                'unit_pns' => [],
            ];
        }
        $normalizedPns[] = $pn;
    }

    return ['valid' => true, 'error' => null, 'unit_pns' => $normalizedPns];
}

function summarize_inventory_restock_results(array $results): array
{
    $addedCount = count(array_filter($results, static function ($result) {
        return !$result['is_restock'];
    }));
    $restockedCount = count($results) - $addedCount;
    $parts = [];

    if ($addedCount > 0) {
        $parts[] = $addedCount . ' new item' . ($addedCount === 1 ? '' : 's') . ' added';
    }
    if ($restockedCount > 0) {
        $parts[] = $restockedCount . ' item' . ($restockedCount === 1 ? '' : 's') . ' restocked';
    }

    return [
        'added' => $addedCount,
        'restocked' => $restockedCount,
        'message' => implode(' and ', $parts),
    ];
}
