# PHP 8.4 Modernization Tool

Automated tool for modernizing PHP codebases to achieve full PHP 8.4 compatibility by eliminating deprecated patterns and adding comprehensive type hints.

## Features

- **Implicitly Nullable Parameter Detection**: Identifies and fixes parameters with `= null` but no type hint
- **Type Inference**: Intelligently infers types from PHPDoc, usage analysis, and inheritance
- **Automated Migration**: Adds parameter, return, and property type hints
- **Safety First**: Creates backups, validates changes, and flags uncertain cases for review
- **Comprehensive Reporting**: Detailed reports of all changes and issues

## Installation

```bash
cd tools/modernization
composer install
chmod +x bin/modernize
```

## Usage

### Scan for Issues

```bash
./bin/modernize scan [path]
```

### Migrate Code

```bash
# Dry run (preview changes)
./bin/modernize migrate --dry-run [path]

# Apply changes
./bin/modernize migrate [path]
```

### Validate Changes

```bash
./bin/modernize validate [path]
```

## Configuration

Edit `config/modernization.php` to customize:

- Paths to include/exclude
- Confidence thresholds
- Backup settings
- Validation options

## Requirements

- PHP 8.1 or higher
- Composer
- PHPStan (for validation)

## Documentation

See `.kiro/specs/php-84-modernization/` for complete requirements and design documentation.
