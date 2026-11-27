# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 27 November 2026
### Added
- `--source` option to update the quantity of a specific MSI source (default: `default`) when Magento_Inventory is enabled
- `--all` option to update all products, asking for confirmation unless `--force` is used

### Changed
- BREAKING: the wildcard SKU `*` is removed, use `--all` instead
- Stock updaters are only instantiated when the command runs, no longer on every `bin/magento` call
- Products are loaded in pages of 500 when updating all products
- Declare dependencies on Magento_Catalog, Magento_CatalogInventory and ext-pcre, set the OSL-3.0 license and suggest magento/module-inventory-api for MSI

### Fixed
- The stock of the wrong product was updated, because the product name was passed as product ID; stock items are now resolved by SKU
- `--qty` (number >= 0) and `--manage_stock` (0 or 1) are validated, the config fallback for manage stock is disabled and the stock status follows the quantity
- The SKU list is trimmed, empty entries are dropped and SKUs that are not found are reported

### Security
- Product names are escaped and control characters are stripped in console output
