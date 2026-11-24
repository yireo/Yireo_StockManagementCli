<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Stock;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

/**
 * MSI is an optional dependency, so its classes are resolved at runtime instead of being injected.
 * Injecting them would break setup:di:compile on installations without the MSI packages.
 */
class MsiStockUpdater
{
    private const SOURCE_ITEMS_SAVE = 'Magento\InventoryApi\Api\SourceItemsSaveInterface';
    private const SOURCE_ITEM_FACTORY = 'Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory';
    private const SOURCE_REPOSITORY = 'Magento\InventoryApi\Api\SourceRepositoryInterface';
    private const STATUS_IN_STOCK = 1;
    private const STATUS_OUT_OF_STOCK = 0;

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function isAvailable(): bool
    {
        return interface_exists(self::SOURCE_ITEMS_SAVE)
            && $this->moduleManager->isEnabled('Magento_Inventory');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function assertSourceExists(string $sourceCode): void
    {
        $this->objectManager->get(self::SOURCE_REPOSITORY)->get($sourceCode);
    }

    public function updateQty(string $sku, string $sourceCode, float $qty): void
    {
        $sourceItem = $this->objectManager->get(self::SOURCE_ITEM_FACTORY)->create();
        $sourceItem->setSku($sku);
        $sourceItem->setSourceCode($sourceCode);
        $sourceItem->setQuantity($qty);
        $sourceItem->setStatus($qty > 0 ? self::STATUS_IN_STOCK : self::STATUS_OUT_OF_STOCK);

        $this->objectManager->get(self::SOURCE_ITEMS_SAVE)->execute([$sourceItem]);
    }
}
