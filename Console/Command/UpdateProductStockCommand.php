<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Console\Command;

use Exception;
use Generator;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Yireo\StockManagementCli\Stock\LegacyStockUpdater;
use Yireo\StockManagementCli\Util\OutputSanitizer;

class UpdateProductStockCommand extends Command
{
    private const PAGE_SIZE = 500;
    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";

    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly LegacyStockUpdater $legacyStockUpdater,
        private readonly OutputSanitizer $outputSanitizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('inventory:product:update');
        $this->setDescription('Update the inventory of one or more products');
        $this->addArgument('sku', InputArgument::OPTIONAL, 'Comma-separated list of product SKUs');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Update all products');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip the confirmation when using --all');
        $this->addOption('manage_stock', null, InputOption::VALUE_REQUIRED, 'Manage stock (0 or 1)');
        $this->addOption('qty', null, InputOption::VALUE_REQUIRED, 'Quantity (number of 0 or higher)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $all = (bool)$input->getOption('all');
            $skus = $this->getSkus($input, $all);
            $qty = $this->getQty($input);
            $manageStock = $this->getManageStock($input);
            if ($qty === null && $manageStock === null) {
                throw new InvalidArgumentException('Nothing to update, use --qty and/or --manage_stock');
            }
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . $this->outputSanitizer->sanitize($exception->getMessage()) . '</error>');

            return Command::INVALID;
        }

        if ($all && !$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $output->writeln('<error>Use --force to update all products in non-interactive mode</error>');

                return Command::INVALID;
            }

            $question = new ConfirmationQuestion('Update the stock of ALL products? [y/N] ', false);
            if (!(new QuestionHelper())->ask($input, $output, $question)) {
                $output->writeln('Aborted');

                return Command::FAILURE;
            }
        }

        $foundSkus = [];
        $failed = false;
        foreach ($this->getProducts($skus) as [$sku, $name]) {
            $foundSkus[] = strtolower($sku);
            $label = $this->outputSanitizer->sanitize($sku . ' (' . $name . ')');

            try {
                if (!$this->legacyStockUpdater->update($sku, $qty, $manageStock)) {
                    $output->writeln('<comment>Skipping ' . $label . ': no stock item found</comment>');
                    $failed = true;
                    continue;
                }
            } catch (Exception $exception) {
                $output->writeln('<error>Failed to update stock of ' . $label . ': '
                    . $this->outputSanitizer->sanitize($exception->getMessage()) . '</error>');
                $failed = true;
                continue;
            }

            $output->writeln('Updated stock of ' . $label);
        }

        if (empty($foundSkus)) {
            $output->writeln('<error>No products matched</error>');

            return Command::INVALID;
        }

        $missingSkus = array_filter($skus, fn (string $sku) => !in_array(strtolower($sku), $foundSkus, true));
        foreach ($missingSkus as $missingSku) {
            $output->writeln('<comment>SKU not found: ' . $this->outputSanitizer->sanitize($missingSku) . '</comment>');
            $failed = true;
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getSkus(InputInterface $input, bool $all): array
    {
        $argument = trim((string)$input->getArgument('sku'), self::TRIM_CHARACTERS);
        if ($argument === '*') {
            throw new InvalidArgumentException('The wildcard "*" is no longer supported, use --all instead');
        }

        $skus = array_map(fn (string $sku) => trim($sku, self::TRIM_CHARACTERS), explode(',', $argument));
        $skus = array_values(array_unique(array_filter($skus, fn (string $sku) => $sku !== '')));

        if ($all && !empty($skus)) {
            throw new InvalidArgumentException('Use either a list of SKUs or --all, not both');
        }

        if (!$all && empty($skus)) {
            throw new InvalidArgumentException('Please provide one or more SKUs, or use --all');
        }

        return $skus;
    }

    private function getQty(InputInterface $input): ?float
    {
        $qty = $input->getOption('qty');
        if ($qty === null) {
            return null;
        }

        $qty = trim((string)$qty, self::TRIM_CHARACTERS);
        if (!is_numeric($qty) || !is_finite((float)$qty) || (float)$qty < 0) {
            throw new InvalidArgumentException('The option --qty must be a number of 0 or higher');
        }

        return (float)$qty;
    }

    private function getManageStock(InputInterface $input): ?bool
    {
        $manageStock = $input->getOption('manage_stock');
        if ($manageStock === null) {
            return null;
        }

        $manageStock = trim((string)$manageStock, self::TRIM_CHARACTERS);
        if (!in_array($manageStock, ['0', '1'], true)) {
            throw new InvalidArgumentException('The option --manage_stock must be 0 or 1');
        }

        return $manageStock === '1';
    }

    /**
     * @param string[] $skus
     * @return Generator<int, array{string, string}>
     */
    private function getProducts(array $skus): Generator
    {
        $lastId = 0;
        do {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('name');
            $collection->addFieldToFilter('entity_id', ['gt' => $lastId]);
            if (!empty($skus)) {
                $collection->addFieldToFilter('sku', ['in' => $skus]);
            }

            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize(self::PAGE_SIZE);

            $count = 0;
            foreach ($collection->getItems() as $product) {
                $count++;
                $lastId = (int)$product->getId();
                yield [(string)$product->getSku(), (string)$product->getName()];
            }
        } while ($count === self::PAGE_SIZE);
    }
}
