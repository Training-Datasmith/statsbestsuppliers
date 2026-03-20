# Architecture: statsbestsuppliers

## Purpose

A PrestaShop statistics module that ranks suppliers by the volume and value of products sold, helping merchants evaluate supplier performance.

## Directory Structure

```
statsbestsuppliers.php   - Module class (ModuleGrid subclass); all business logic
upgrade/                 - Migration scripts
tests/                   - PHPUnit test stubs and PHPStan bootstrap
translations/            - Locale string overrides
```

## Key Design Decisions

- **ModuleGrid inheritance**: Leverages PrestaShop's grid with built-in sort, page, and CSV export.
- **Single-file module**: All logic in the module class file.

## Extension Points

- Override `getData()` to change which supplier metrics are surfaced.

## Dependency Flow

```
statsbestsuppliers (ModuleGrid)
  └─> hookDisplayAdminStatsModules() — renders the supplier ranking widget
  └─> getData()                      — supplier ranking SQL
        └─> Db::getInstance()
```
