<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Enums\InventoryCostState;
use App\Domain\Inventory\Enums\InventoryMovementKind;
use App\Models\InventoryAdjustmentReason;
use App\Models\InventoryLocation;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class StockMovementCommand
{
    public function __construct(
        public InventoryLocation $location,
        public ProductVariant $variant,
        public InventoryMovementKind $kind,
        public string $quantityBase,
        public string $sourceType,
        public string $sourceReference,
        public string $sourceLineReference = 'main',
        public string $sourceEffect = 'stock',
        public ?ProductBatch $batch = null,
        public ?User $actor = null,
        public ?InventoryAdjustmentReason $adjustmentReason = null,
        public ?StockMovement $reverses = null,
        public ?string $reasonText = null,
        public ?string $unitCost = null,
        public InventoryCostState $costState = InventoryCostState::Pending,
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
