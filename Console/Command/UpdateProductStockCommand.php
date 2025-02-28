<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Console\Command;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class UpdateProductStockCommand extends Command
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private StockRegistryInterface $stockRegistry,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('inventory:product:update');
        $this->setDescription('Update the inventory of one or more products');
        $this->addArgument('sku', InputArgument::REQUIRED, 'Product SKU');
        $this->addOption('manage_stock', null, InputOption::VALUE_OPTIONAL, 'Manage stock');
        $this->addOption('qty', null, InputOption::VALUE_OPTIONAL, 'Quantity');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $productSkus = (string)$input->getArgument('sku');
        if (empty($productSkus)) {
            $output->writeln('Please provide a SKU');

            return Command::INVALID;
        }

        $products = $this->getProducts($productSkus);
        if (empty($products)) {
            $output->writeln('No products matched');

            return Command::INVALID;
        }

        foreach ($products as $product) {
            $stockItem = $this->stockRegistry->getStockItem($product->getName());
            $updateStock = false;

            $qty = $input->getOption('qty');
            $manageStock = $input->getOption('manage_stock');

            if ($qty !== null) {
                $stockItem->setQty($qty);
                $updateStock = true;
            }

            if ($manageStock !== null && $stockItem->getManageStock() !== (bool)$manageStock) {
                $stockItem->setManageStock($manageStock);
                $updateStock = true;
            }

            if ($qty > 0 || $manageStock === 0) {
                $stockItem->setIsInStock(true);
                $updateStock = true;
            }

            if ($updateStock === false) {
                continue;
            }

            $output->writeln('Updating stock of '.$product->getName());
            $stockItem->save();
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $identifier
     * @return ProductInterface[]
     */
    private function getProducts(string $identifier): array
    {
        if ($identifier === '*') {
            $this->searchCriteriaBuilder->setPageSize(10000000);
        } else {
            $skus = explode(',', $identifier);
            $this->searchCriteriaBuilder->addFilter('sku', $skus, 'in');
        }

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->productRepository->getList($searchCriteria);
        return $searchResults->getItems();
    }
}
