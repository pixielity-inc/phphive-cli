# Dependency Injection Review & Recommendations

## Executive Summary

A comprehensive review of dependency injection patterns across the codebase revealed several inconsistencies and anti-patterns. This document outlines current patterns, issues, and recommended improvements.

## Current State Analysis

### ✅ Good Patterns Found

1. **Most Traits (5/6)** - Use abstract methods for service access
   - `InteractsWithMonorepo`, `InteractsWithTurborepo`, `InteractsWithDocker`, `HasDiscovery`, `InteractsWithPrompts`
   - Define `abstract protected function filesystem(): Filesystem`
   - Host class provides services
   - No container coupling

2. **PackageTypeFactory** - Constructor injection pattern
   - Receives `Composer` via constructor
   - Passes dependencies to created instances
   - Registered as singleton in container

3. **Core Services** - Proper static factories
   - `Filesystem::make()`, `Process::make()` - No dependencies
   - `Composer::make()` - Resolves own dependencies via `Process::make()`

### ❌ Anti-Patterns & Issues

1. **InteractsWithComposer Trait** - Uses `App::make()` directly
   - Location: `cli/src/Concerns/InteractsWithComposer.php:79`
   - Violates trait pattern used by other traits
   - Creates tight coupling to container

2. **AppTypes** - Service Locator pattern
   - Uses `App::make(Filesystem::class)` in helper methods
   - No constructor injection
   - Hidden dependencies
   - Inconsistent service lifecycle (singleton vs new instances)

3. **AppTypeFactory** - Static methods without DI
   - Creates instances with `new $className()` - no dependencies
   - Cannot inject services
   - Forces AppTypes to use service locator

4. **Docker::make()** - Circular dependency risk
   - Uses `App::make(Process::class)` internally
   - Should use `Process::make()` instead

5. **Config Class** - Unnecessary container coupling
   - Uses `App::make(ConfigOperation::class)` for value objects
   - Should use `new ConfigOperation()` instead

6. **Commands** - Inconsistent service access
   - Mix of `$this->filesystem()` and `App::make(PreflightChecker::class)`
   - Missing BaseCommand helpers for common services

## Priority 1: Critical Fixes

### 1. Fix InteractsWithComposer Trait

**Current (Anti-pattern):**
```php
$process = App::make(Process::class)(
    $commandArray,
    $cwd,
    timeout: null,
);
```

**Recommended:**
```php
// Add abstract method
abstract protected function process(): Process;

// Use in trait
$process = $this->process()->execute($commandArray, $cwd, null);
```

### 2. Fix Circular Dependencies in Support Classes

**Docker::make() - Current:**
```php
public static function make(): self
{
    return new self(App::make(Process::class));
}
```

**Recommended:**
```php
public static function make(): self
{
    return new self(Process::make());
}
```

**Config - Current:**
```php
public static function set(string $file, array $values): ConfigOperation
{
    return App::make(ConfigOperation::class, ['set', $file, $values]);
}
```

**Recommended:**
```php
public static function set(string $file, array $values): ConfigOperation
{
    return new ConfigOperation('set', $file, $values);
}
```

## Priority 2: AppType Refactoring

### 1. Update AbstractAppType Constructor

**Current:**
```php
// No constructor
protected function filesystem(): Filesystem
{
    if (!isset($this->filesystem)) {
        $this->filesystem = App::make(Filesystem::class);
    }
    return $this->filesystem;
}
```

**Recommended:**
```php
public function __construct(
    protected readonly Filesystem $filesystem,
    protected readonly Process $process,
    protected readonly Composer $composer,
) {}

// Remove helper methods - use properties directly
// Usage: $this->filesystem instead of $this->filesystem()
```

### 2. Update AppTypeFactory

**Current:**
```php
public static function create(string $type): AppTypeInterface
{
    $className = $appType->getClassName();
    return new $className();
}
```

**Recommended:**
```php
final readonly class AppTypeFactory
{
    public function __construct(
        private Container $container
    ) {}

    public function create(string $type): AppTypeInterface
    {
        $className = $appType->getClassName();
        
        // Resolve dependencies
        $filesystem = $this->container->make(Filesystem::class);
        $process = Process::make();
        $composer = Composer::make();
        
        return new $className($filesystem, $process, $composer);
    }
}
```

### 3. Update Application Service Registration

```php
// Change from static to instance-based
$this->container->singleton(
    AppTypeFactory::class,
    fn (Container $c): AppTypeFactory => new AppTypeFactory($c)
);
```

## Priority 3: Command Improvements

### 1. Add Missing BaseCommand Helpers

```php
// In BaseCommand.php
protected function preflightChecker(): PreflightChecker
{
    return $this->container->make(PreflightChecker::class);
}

protected function packageTypeFactory(): PackageTypeFactory
{
    return $this->container->make(PackageTypeFactory::class);
}

protected function nameSuggestionService(): NameSuggestionService
{
    return $this->container->make(NameSuggestionService::class);
}
```

### 2. Replace App::make() in Commands

**Before:**
```php
$preflightChecker = App::make(PreflightChecker::class);
$packageTypeFactory = App::make(PackageTypeFactory::class);
```

**After:**
```php
$preflightChecker = $this->preflightChecker();
$packageTypeFactory = $this->packageTypeFactory();
```

## Best Practices Summary

### For Traits (Concerns)

✅ **DO:**
- Define abstract protected methods for service access
- Let host class provide services
- Keep traits decoupled from container

❌ **DON'T:**
- Use `App::make()` in traits
- Create service instances directly
- Couple traits to container

### For Services

✅ **DO:**
- Use constructor injection for dependencies
- Provide static factory methods
- Resolve dependencies via other make() methods (not App::make())

❌ **DON'T:**
- Use `App::make()` inside make() methods (circular dependency risk)
- Mix singleton and new instance patterns
- Hide dependencies

### For Factories

✅ **DO:**
- Inject Container into factory constructor
- Use `$container->make()` to resolve instances
- Declare dependencies in created class constructors

❌ **DON'T:**
- Use static factory methods when DI is needed
- Hardcode dependency instantiation
- Create instances with `new` when they have dependencies

### For Commands

✅ **DO:**
- Use BaseCommand helper methods for services
- Add helpers for commonly used services
- Keep service access consistent

❌ **DON'T:**
- Mix `$this->service()` and `App::make()` patterns
- Use `App::make()` when helper exists
- Create services directly with `new`

## Migration Checklist

### Phase 1: Fix Critical Issues
- [ ] Fix InteractsWithComposer to use abstract method pattern
- [ ] Fix Docker::make() to use Process::make()
- [ ] Fix Config to use new ConfigOperation()

### Phase 2: Refactor AppTypes
- [ ] Add constructor to AbstractAppType
- [ ] Update AppTypeFactory to instance-based with Container
- [ ] Update Application service registration
- [ ] Update all AppType usages from `$this->filesystem()` to `$this->filesystem`
- [ ] Remove service helper methods from AbstractAppType
- [ ] Update traits to use properties instead of methods

### Phase 3: Improve Commands
- [ ] Add missing helpers to BaseCommand
- [ ] Replace App::make() with helpers in all commands
- [ ] Document service access guidelines

### Phase 4: Testing
- [ ] Update tests to use constructor injection
- [ ] Remove App::make() mocking from tests
- [ ] Add integration tests for DI container

## Benefits of Recommended Changes

1. **Explicit Dependencies** - Constructor reveals what services are needed
2. **Testability** - Easy to mock dependencies in tests
3. **Consistency** - Uniform patterns across codebase
4. **Loose Coupling** - Classes don't depend on global App facade
5. **Type Safety** - IDE and static analysis tools can track dependencies
6. **Performance** - Services created once, reused throughout lifecycle
7. **Maintainability** - Easier to understand and modify code

## Estimated Effort

- **Phase 1 (Critical)**: 2-3 hours
- **Phase 2 (AppTypes)**: 4-6 hours
- **Phase 3 (Commands)**: 2-3 hours
- **Phase 4 (Testing)**: 3-4 hours
- **Total**: 11-16 hours

## References

- Laravel Container Documentation: https://laravel.com/docs/container
- Dependency Injection Principles: https://en.wikipedia.org/wiki/Dependency_injection
- Service Locator Anti-pattern: https://blog.ploeh.dk/2010/02/03/ServiceLocatorisanAnti-Pattern/
