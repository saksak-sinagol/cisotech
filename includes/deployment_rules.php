<?php

function available_inventory_quantity($totalQuantity, $deployedQuantity): int
{
    return (int)$totalQuantity - (int)$deployedQuantity;
}

function deployment_validation_error(?array $item, int $departmentId, string $poNumber, string $pnNumber, int $quantity): ?string
{
    if (!$item || $departmentId === 0) {
        return 'Please select a valid item and department.';
    }
    if ($poNumber === '') {
        return 'PO Number is required for every item.';
    }
    if ($pnNumber === '') {
        return 'PN Number is required.';
    }

    $available = available_inventory_quantity($item['quantity_total'], $item['deployed_qty']);
    if ($quantity > $available) {
        return "Only $available unit(s) of \"{$item['item_name']}\" left to deploy.";
    }

    return null;
}

function normalize_deployment_type($deploymentType): string
{
    return in_array($deploymentType, ['new', 'replacement'], true) ? $deploymentType : 'new';
}

function plan_deployment_batch_consumption(int $quantity, array $batches): array
{
    $remaining = $quantity;
    $allocations = [];

    foreach ($batches as $batch) {
        if ($remaining <= 0) {
            break;
        }
        $batchLeft = (int)$batch['batch_left'];
        if ($batchLeft <= 0) {
            continue;
        }
        $take = min($batchLeft, $remaining);
        $allocations[] = ['batch_id' => $batch['id'], 'quantity' => $take];
        $remaining -= $take;
    }

    return ['allocations' => $allocations, 'remaining' => $remaining];
}
