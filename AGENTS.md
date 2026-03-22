# AGENTS.md - Sitecheck CLI

This is a Laravel Zero-based CLI application for website checking.

## Project Overview

- **Framework**: Laravel Zero (optimized Laravel for CLI applications)
- **Language**: PHP 8.2+
- **Testing**: Pest (built on PHPUnit)
- **Code Formatting**: Laravel Pint
- **Entry Point**: `sitecheck` (bin file)

## Build/Lint/Test Commands

### Running Tests
```bash
# Run all tests
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/InspireCommandTest.php

# Run tests matching a pattern
./vendor/bin/pest --filter="inspires"

# Run only Unit tests
./vendor/bin/pest tests/Unit

# Run only Feature tests
./vendor/bin/pest tests/Feature

# Run with coverage (if installed)
./vendor/bin/pest --coverage
```

### Code Formatting (Laravel Pint)
```bash
# Format all files
./vendor/bin/pint

# Format with preview (show changes without applying)
./vendor/bin/pint --test

# Format specific files
./vendor/bin/pint app/Commands/InspireCommand.php
```

### Application Commands
```bash
# Show help
./sitecheck --help

# Show version
./sitecheck --version

# List all commands
./sitecheck list
```

## Code Style Guidelines

### General
- **PHP Version**: 8.2 minimum
- **Indent**: 4 spaces (per .editorconfig)
- **Line endings**: LF
- **Charset**: UTF-8
- **Trailing whitespace**: Trimmed (except in .md files)

### File Structure
- Opening `<?php` tag on first line (no `<?=` or HTML)
- Namespace declaration immediately after
- Blank line between namespace and imports
- Imports grouped: `use` statements
- One blank line before class definition
- Closing `?>` tag omitted (PSR-2)

### Naming Conventions
- **Classes**: `PascalCase` (e.g., `InspireCommand`)
- **Methods/Functions**: `camelCase` (e.g., `handle()`, `something()`)
- **Properties**: `$camelCase` or `$snake_case`
- **Constants**: `SCREAMING_SNAKE_CASE`
- **Files**: Match class name (e.g., `InspireCommand.php`)
- **Namespaces**: `App\` prefix (e.g., `App\Commands`)

### Imports
- Use `use` statements for all external classes
- Group imports logically
- Prefer aliased imports when names conflict
- Use function imports where available: `use function Termwind\render;`

### Type Declarations
- Always use strict return types: `public function handle(): void`
- Use nullable types: `?string`
- Use union types where appropriate: `string|int`
- Prefer `mixed` sparingly with proper docblocks

### Docblocks
- Use for properties and complex methods
- Include `@param` and `@return` where helpful
- Keep concise and relevant
- Do not add unnecessary comments

### Error Handling
- Use exception types appropriately
- Let exceptions propagate in expected failure cases
- Log errors via Laravel's logger when needed
- Handle gracefully in CLI context (user-friendly messages)

## Test Conventions

### File Organization
- Unit tests: `tests/Unit/`
- Feature tests: `tests/Feature/`
- File naming: `ClassNameTest.php`

### Test Structure (Pest)
```php
<?php

it('does something expected', function () {
    $this->artisan('command:name')->assertExitCode(0);
});

test('can also use test()', function () {
    expect(true)->toBeTrue();
});
```

### Expectations
- Use Pest's `expect()` for assertions
- Chain meaningful expectations
- Use `assertExitCode()` for CLI commands
- Use `assertOutputContains()` to verify output

## Directory Structure
```
app/
  Commands/          # Artisan commands
  Providers/         # Service providers
bootstrap/
  app.php           # Application bootstrap
config/
  app.php           # App configuration
  commands.php      # Command registration
tests/
  Feature/          # Integration tests
  Unit/             # Unit tests
  Pest.php          # Test configuration
  TestCase.php      # Base test class
sitecheck           # Application entry point
```

## Common Patterns

### Command Class
```php
class MyCommand extends Command
{
    protected $signature = 'my:command {arg?}';  // {--option=default}
    protected $description = 'Does something';
    
    public function handle(): void
    {
        // Implementation
    }
    
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->daily();
    }
}
```

### Service Provider
```php
public function register(): void
{
    // Bindings
}

public function boot(): void
{
    // Boot logic
}
```

## External Resources
- Laravel Zero Docs: https://laravel-zero.com/docs/
- Pest Testing: https://pestphp.com/
- Laravel Pint: https://github.com/laravel/pint
