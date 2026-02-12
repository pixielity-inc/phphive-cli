# Common Options

All PhpHive commands inherit a set of common options from the `BaseCommand` class. These options provide consistent behavior across the entire CLI.

## Available Options

### `--workspace, -w=NAME`

Target a specific workspace for the command operation.

**Usage:**
```bash
# Run tests in specific workspace
hive quality:test --workspace=api

# Install dependencies in specific package
hive composer:install -w calculator

# Format code in specific app
hive quality:format --workspace=admin
```

**When to use:**
- When you want to limit the operation to a single workspace
- When working with multiple apps/packages in a monorepo
- To avoid running commands across all workspaces

---

### `--force, -f`

Force the operation by ignoring cache and confirmations.

**Usage:**
```bash
# Force install without cache
hive composer:install --force

# Force cleanup without confirmation
hive clean:all -f

# Force build ignoring cache
hive build --force
```

**When to use:**
- When cache is stale or corrupted
- When you need to bypass confirmation prompts
- When troubleshooting issues

**Warning:** This may increase execution time as it bypasses optimizations.

---

### `--no-cache`

Disable Turborepo cache for this run.

**Usage:**
```bash
# Run tests without cache
hive quality:test --no-cache

# Build without using cached results
hive build --no-cache

# Run task without cache
hive run build --no-cache
```

**When to use:**
- When you suspect cache issues
- When you need fresh results
- During debugging

**Difference from `--force`:**
- `--force` affects both Turbo cache and command-specific caching
- `--no-cache` specifically disables Turborepo's caching layer

---

### `--no-interaction, -n`

Run in non-interactive mode (no prompts).

**Usage:**
```bash
# Create workspace without prompts
hive make:workspace --name=my-repo --no-interaction

# Install dependencies in CI
hive composer:install -n

# Deploy without confirmations
hive deploy --no-interaction
```

**When to use:**
- In CI/CD pipelines
- In automated scripts
- When you want to use default values

**Behavior:**
- All prompts return their default values
- No user input is requested
- Commands fail if required values are missing

---

### `--all`

Apply the operation to all workspaces in the monorepo.

**Usage:**
```bash
# Install dependencies in all workspaces
hive composer:install --all

# Run tests across all workspaces
hive quality:test --all

# Format code in all workspaces
hive quality:format --all
```

**When to use:**
- When you want to affect all workspaces at once
- For monorepo-wide operations
- When preparing for deployment

**Note:** This is equivalent to running the command without `--workspace` in most cases.

---

### `--json`

Output data in JSON format instead of human-readable format.

**Usage:**
```bash
# List workspaces as JSON
hive workspace:list --json

# Get workspace info as JSON
hive workspace:info api --json

# Export test results as JSON
hive quality:test --json
```

**When to use:**
- When integrating with other tools
- For parsing output in scripts
- When building automation

**Example output:**
```json
{
  "workspaces": [
    {
      "name": "api",
      "type": "app",
      "path": "apps/api",
      "framework": "laravel"
    },
    {
      "name": "calculator",
      "type": "package",
      "path": "packages/calculator"
    }
  ]
}
```

---

### `--parallel`

Enable parallel execution across workspaces.

**Usage:**
```bash
# Run tests in parallel
hive quality:test --parallel

# Build all workspaces in parallel
hive build --parallel

# Install dependencies in parallel
hive composer:install --parallel
```

**When to use:**
- When you have multiple workspaces
- To speed up execution time
- When workspaces are independent

**Benefits:**
- Significantly faster execution
- Better CPU utilization
- Leverages Turborepo's parallel execution

**Considerations:**
- May produce interleaved output
- Requires sufficient system resources
- Some operations may not support parallelization

---

### `--dry-run`

Preview what would happen without actually executing.

**Usage:**
```bash
# Preview deployment steps
hive deploy --dry-run

# Preview refactoring changes
hive quality:refactor --dry-run

# Preview package publish
hive deploy:publish --dry-run

# Preview cleanup operations
hive clean:all --dry-run
```

**When to use:**
- Before running destructive operations
- To understand what a command will do
- When testing automation scripts
- Before deployment

**Behavior:**
- Shows what would be executed
- No actual changes are made
- Safe to run in production

---

## Standard Symfony Options

PhpHive also supports standard Symfony Console options:

### `--help, -h`

Display help information for a command.

```bash
hive make:app --help
hive quality:test -h
```

### `--quiet, -q`

Suppress all output except errors.

```bash
hive composer:install --quiet
hive build -q
```

### `--verbose, -v, -vv, -vvv`

Increase output verbosity.

```bash
# Verbose
hive quality:test -v

# Very verbose
hive build -vv

# Debug
hive deploy -vvv
```

**Levels:**
- `-v` - Verbose: Show additional information
- `-vv` - Very verbose: Show detailed information
- `-vvv` - Debug: Show all information including debug messages

### `--version, -V`

Display application version.

```bash
hive --version
hive -V
```

### `--ansi / --no-ansi`

Force or disable ANSI output.

```bash
# Force colored output
hive quality:test --ansi

# Disable colors
hive build --no-ansi
```

---

## Combining Options

Options can be combined for powerful workflows:

```bash
# Run tests in specific workspace with parallel execution and no cache
hive quality:test --workspace=api --parallel --no-cache

# Deploy with dry run and JSON output
hive deploy --dry-run --json

# Format all workspaces in parallel without interaction
hive quality:format --all --parallel --no-interaction

# Install dependencies with force and verbose output
hive composer:install --force -vv
```

---

## Option Precedence

When options conflict, the following precedence applies:

1. **Explicit options** - Options passed on command line
2. **Environment variables** - Set via `HIVE_*` variables
3. **Configuration files** - From `.phphive.json` (future feature)
4. **Default values** - Built-in defaults

---

## Examples by Use Case

### CI/CD Pipeline

```bash
# Non-interactive, with JSON output, no cache
hive quality:test --no-interaction --json --no-cache
```

### Local Development

```bash
# Verbose output for debugging
hive build --workspace=api -vv
```

### Monorepo-wide Operations

```bash
# All workspaces, parallel execution
hive quality:format --all --parallel
```

### Safe Deployment

```bash
# Dry run first, then execute
hive deploy --dry-run
hive deploy
```

### Troubleshooting

```bash
# Force, no cache, debug output
hive composer:install --force --no-cache -vvv
```

---

## Best Practices

1. **Use `--dry-run` before destructive operations**
   ```bash
   hive clean:all --dry-run  # Check first
   hive clean:all            # Then execute
   ```

2. **Use `--workspace` to limit scope**
   ```bash
   hive quality:test --workspace=api  # Faster than testing all
   ```

3. **Use `--parallel` for speed**
   ```bash
   hive quality:test --parallel  # Much faster with multiple workspaces
   ```

4. **Use `--json` for automation**
   ```bash
   hive workspace:list --json | jq '.workspaces[].name'
   ```

5. **Use `--no-interaction` in scripts**
   ```bash
   hive deploy --no-interaction  # Won't hang waiting for input
   ```

6. **Use `-vvv` when debugging**
   ```bash
   hive build -vvv  # See everything that's happening
   ```

---

**Related Documentation:**
- [Commands Reference](./README.md)
- [Getting Started](../getting-started/README.md)
- [Command Cheat Sheet](./cheat-sheet.md)
