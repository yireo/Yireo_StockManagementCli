<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Test\Integration\Console\Command;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Yireo\StockManagementCli\Console\Command\UpdateProductStockCommand;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class UpdateProductStockCommandTest extends TestCase
{
    private const SKU_A = 'stock-cli-test-a';
    private const SKU_B = 'stock-cli-test-b';

    private ProductInterface $productA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productA = $this->createProduct(self::SKU_A, 'Stock CLI test A');
        $this->createProduct(self::SKU_B, $this->productA->getId() . ' Stock CLI test B');
    }

    public function testUpdatesStockOfProductItselfAndNotOfProductWhoseIdMatchesTheName(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['sku' => self::SKU_B, '--qty' => '3']));
        self::assertSame(3.0, (float)$this->stockItem(self::SKU_B)->getQty());
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    /**
     * @dataProvider invalidQuantities
     */
    #[DataProvider('invalidQuantities')]
    public function testRejectsInvalidQty(string $qty): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['sku' => self::SKU_A, '--qty' => $qty]));
        self::assertStringContainsString('--qty', $tester->getDisplay());
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'text' => ['abc'],
            'negative' => ['-5'],
            'nan' => ['NAN'],
            'infinite' => ['INF'],
            'overflow' => ['1e400'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider invalidManageStockValues
     */
    #[DataProvider('invalidManageStockValues')]
    public function testRejectsInvalidManageStock(string $manageStock): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['sku' => self::SKU_A, '--manage_stock' => $manageStock]));
        self::assertStringContainsString('--manage_stock', $tester->getDisplay());
        self::assertTrue($this->stockItem(self::SKU_A)->getUseConfigManageStock());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidManageStockValues(): array
    {
        return [
            'false' => ['false'],
            'yes' => ['yes'],
            'two' => ['2'],
        ];
    }

    public function testDisablesManageStockAndConfigFallback(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['sku' => self::SKU_A, '--manage_stock' => '0']));
        $stockItem = $this->stockItem(self::SKU_A);
        self::assertFalse($stockItem->getManageStock());
        self::assertFalse($stockItem->getUseConfigManageStock());
    }

    public function testStockStatusFollowsQty(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['sku' => self::SKU_A, '--qty' => '0']));
        self::assertFalse($this->stockItem(self::SKU_A)->getIsInStock());

        self::assertSame(Command::SUCCESS, $tester->execute(['sku' => self::SKU_A, '--qty' => '5']));
        self::assertTrue($this->stockItem(self::SKU_A)->getIsInStock());
        self::assertSame(5.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    public function testRequiresSomethingToUpdate(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['sku' => self::SKU_A]));
    }

    public function testRejectsWildcardSku(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['sku' => '*', '--qty' => '1']));
        self::assertStringContainsString('--all', $tester->getDisplay());
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    public function testAllRequiresForceWhenNotInteractive(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--all' => true, '--qty' => '1'], ['interactive' => false]));
        self::assertStringContainsString('--force', $tester->getDisplay());
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    public function testAllAbortsWhenNotConfirmed(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['no']);

        self::assertSame(Command::FAILURE, $tester->execute(['--all' => true, '--qty' => '1'], ['interactive' => true]));
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    public function testAllWithForceUpdatesAllProducts(): void
    {
        $tester = $this->tester();

        $tester->execute(['--all' => true, '--force' => true, '--qty' => '42'], ['interactive' => false]);
        self::assertSame(42.0, (float)$this->stockItem(self::SKU_A)->getQty());
        self::assertSame(42.0, (float)$this->stockItem(self::SKU_B)->getQty());
    }

    public function testRejectsSkusCombinedWithAll(): void
    {
        $tester = $this->tester();

        self::assertSame(
            Command::INVALID,
            $tester->execute(['sku' => self::SKU_A, '--all' => true, '--force' => true, '--qty' => '1'])
        );
    }

    public function testEscapesProductNameInOutput(): void
    {
        $this->createProduct('stock-cli-test-evil', "<error>Evil</error>\e[31mRed\x07");
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['sku' => 'stock-cli-test-evil', '--qty' => '1']));
        $display = $tester->getDisplay();
        self::assertStringContainsString('<error>Evil</error>[31mRed', $display);
        self::assertStringNotContainsString("\e", $display);
        self::assertStringNotContainsString("\x07", $display);
    }

    public function testNormalisesSkuListAndReportsMissingSkus(): void
    {
        $tester = $this->tester();

        $result = $tester->execute(['sku' => ' ' . self::SKU_A . ' , ,stock-cli-test-missing,', '--qty' => '4']);
        self::assertSame(Command::FAILURE, $result);
        self::assertSame(4.0, (float)$this->stockItem(self::SKU_A)->getQty());
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_B)->getQty());
        self::assertStringContainsString('stock-cli-test-missing', $tester->getDisplay());
    }

    public function testReturnsInvalidWhenNoProductMatches(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['sku' => 'stock-cli-test-missing', '--qty' => '1']));
    }

    public function testRejectsUnknownMsiSource(): void
    {
        $this->skipWithoutMsi();
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute([
            'sku' => self::SKU_A,
            '--qty' => '1',
            '--source' => 'stock-cli-test-unknown-source',
        ]));
        self::assertSame(10.0, (float)$this->stockItem(self::SKU_A)->getQty());
    }

    public function testUpdatesMsiSourceItem(): void
    {
        $this->skipWithoutMsi();
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([
            'sku' => self::SKU_A,
            '--qty' => '7',
            '--source' => 'default',
        ]));

        $objectManager = Bootstrap::getObjectManager();
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('sku', self::SKU_A)
            ->addFilter('source_code', 'default')
            ->create();
        $sourceItems = $objectManager->get('Magento\InventoryApi\Api\SourceItemRepositoryInterface')
            ->getList($searchCriteria)
            ->getItems();

        self::assertCount(1, $sourceItems);
        $sourceItem = reset($sourceItems);
        self::assertSame(7.0, (float)$sourceItem->getQuantity());
        self::assertSame(1, (int)$sourceItem->getStatus());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(Bootstrap::getObjectManager()->create(UpdateProductStockCommand::class));
    }

    private function createProduct(string $sku, string $name): ProductInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $product = $objectManager->get(ProductInterfaceFactory::class)->create();
        $product->setTypeId(Type::TYPE_SIMPLE);
        $product->setAttributeSetId(4);
        $product->setSku($sku);
        $product->setName($name);
        $product->setPrice(10);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $product->setWebsiteIds([1]);
        $product->setCustomAttribute('url_key', $sku);
        $product->setStockData([
            'qty' => 10,
            'is_in_stock' => 1,
            'manage_stock' => 1,
            'use_config_manage_stock' => 1,
        ]);

        return $objectManager->get(ProductRepositoryInterface::class)->save($product);
    }

    private function stockItem(string $sku): StockItemInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StockRegistryStorage::class)->clean();

        return $objectManager->get(StockRegistryInterface::class)->getStockItemBySku($sku);
    }

    private function skipWithoutMsi(): void
    {
        $moduleManager = Bootstrap::getObjectManager()->get(ModuleManager::class);
        if (!interface_exists('Magento\InventoryApi\Api\SourceItemsSaveInterface')
            || !$moduleManager->isEnabled('Magento_Inventory')) {
            self::markTestSkipped('MSI is not available');
        }
    }
}
