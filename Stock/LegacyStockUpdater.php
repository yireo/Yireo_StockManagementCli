<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Stock;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class LegacyStockUpdater
{
    public function __construct(
        private readonly StockRegistryInterface $stockRegistry
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function update(string $sku, ?float $qty, ?bool $manageStock): bool
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        if (!$stockItem->getItemId()) {
            return false;
        }

        if ($qty !== null) {
            $stockItem->setQty($qty);
            $stockItem->setIsInStock($qty > 0 || $stockItem->getBackorders() > 0);
        }

        if ($manageStock !== null) {
            $stockItem->setUseConfigManageStock(false);
            $stockItem->setManageStock($manageStock);
        }

        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

        return true;
    }
}
