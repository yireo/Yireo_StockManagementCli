# Yireo_StockManagementCli

**A simple Magento 2 module to allow managing a products stock via the CLI**

## Usage
```bash
bin/magento inventory:product:update 24-MB01 --manage_stock=1 --qty=101
bin/magento inventory:product:update 24-MB01,24-MB04 --qty=0
bin/magento inventory:product:update --all --qty=100
bin/magento inventory:product:update --all --force --manage_stock=0
```

Options:
- `--qty`: a number of 0 or higher. A quantity above 0 marks the product as in stock, 0 marks it as out of stock (unless backorders are enabled).
- `--manage_stock`: `0` or `1`. This also disables the "Use Config Settings" fallback for Manage Stock.
- `--all`: update all products instead of a list of SKUs. This asks for confirmation, unless `--force` is used. In non-interactive mode, `--force` is required.

The wildcard SKU `*` is no longer supported. Use `--all` instead.

The command returns `1` (failure) when one or more SKUs are not found or could not be updated, and `2` (invalid) when the input is invalid or no product matched.
