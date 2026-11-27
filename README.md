# Yireo_StockManagementCli

<!-- badges.specs.start -->
<div class="github-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/module-stock-management-cli)

</div>
<!-- badges.specs.end -->


**A simple Magento 2 module to allow managing a products stock via the CLI**

## Usage
```bash
bin/magento inventory:product:update 24-MB01 --manage_stock=1 --qty=101
bin/magento inventory:product:update 24-MB01,24-MB04 --qty=0
bin/magento inventory:product:update 24-MB01 --qty=5 --source=warehouse_eu
bin/magento inventory:product:update --all --qty=100
bin/magento inventory:product:update --all --force --manage_stock=0
```

Options:
- `--qty`: a number of 0 or higher. A quantity above 0 marks the product as in stock, 0 marks it as out of stock (unless backorders are enabled).
- `--manage_stock`: `0` or `1`. This also disables the "Use Config Settings" fallback for Manage Stock.
- `--source`: the MSI source to set the quantity for (default: `default`). When MSI (`Magento_Inventory`) is enabled, `--qty` updates the MSI source item. Without MSI, `--qty` updates the legacy stock item and `--source` is not allowed. `--manage_stock` always updates the legacy stock item.
- `--all`: update all products instead of a list of SKUs. This asks for confirmation, unless `--force` is used. In non-interactive mode, `--force` is required.

The wildcard SKU `*` is no longer supported. Use `--all` instead.

The command returns `1` (failure) when one or more SKUs are not found or could not be updated, and `2` (invalid) when the input is invalid or no product matched.

## Current status

<!-- badges.test.start -->
<div class="github-pipeline-badges">

![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_StockManagementCli/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_StockManagementCli/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_StockManagementCli/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_StockManagementCli/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_StockManagementCli/compile.yml?label=compile)

</div>
<!-- badges.test.end -->
