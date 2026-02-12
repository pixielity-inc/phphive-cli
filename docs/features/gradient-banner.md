# Gradient Banner

PhpHive CLI features a beautiful ASCII art banner with honey-themed gradient colors that displays when you run commands.

## Overview

The gradient banner is one of PhpHive's signature features, providing a visually stunning introduction to every command execution. It uses ANSI 256-color codes to create smooth color gradients inspired by honey and amber tones.

## Features

- **6 Honey-Themed Gradients** - Randomly selected on each run
- **256-Color ANSI Support** - Smooth, beautiful gradients
- **Smart Display Logic** - Only shows when appropriate
- **Performance Optimized** - Displayed once per process
- **Update Notifications** - Checks for new versions automatically

## Color Themes

PhpHive includes 6 carefully crafted honey-themed color gradients:

### 1. Honey (Default)
Warm honey gradient inspired by natural honey colors.
- Colors: `214, 208, 202, 178, 172, 136`
- Tone: Warm golden amber

### 2. Amber
Rich amber honey tones with deeper warmth.
- Colors: `220, 214, 208, 202, 178, 172`
- Tone: Deep amber gold

### 3. Golden
Bright golden honey with lighter tones.
- Colors: `226, 220, 214, 208, 202, 178`
- Tone: Bright golden yellow

### 4. Sunset
Sunset honey with orange and red undertones.
- Colors: `214, 208, 202, 196, 160, 124`
- Tone: Warm sunset orange

### 5. Caramel
Caramelized honey with brown tones.
- Colors: `180, 174, 168, 162, 136, 130`
- Tone: Rich caramel brown

### 6. Wildflower
Wildflower honey with varied tones.
- Colors: `221, 215, 209, 203, 179, 173`
- Tone: Light floral honey

## Banner Display

The banner displays:

```
██████╗ ██╗  ██╗██████╗ ██╗  ██╗██╗██╗   ██╗███████╗
██╔══██╗██║  ██║██╔══██╗██║  ██║██║██║   ██║██╔════╝
██████╔╝███████║██████╔╝███████║██║██║   ██║█████╗  
██╔═══╝ ██╔══██║██╔═══╝ ██╔══██║██║╚██╗ ██╔╝██╔══╝  
██║     ██║  ██║██║     ██║  ██║██║ ╚████╔╝ ███████╗
╚═╝     ╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝  ╚══════╝

PHP Monorepo Management powered by Turborepo v1.0.11
```

Each line of the ASCII art is rendered in a different color from the selected gradient, creating a smooth color transition from top to bottom.

## When the Banner Displays

The banner is shown:

✅ **When running actual commands:**
```bash
hive make:workspace
hive quality:test
hive composer:install
```

❌ **Not shown for:**
```bash
hive --help
hive --version
hive list
```

## Smart Display Logic

The banner includes intelligent display logic:

1. **Once Per Process** - Only shown once, even if multiple commands run
2. **CLI Mode Only** - Not displayed when running via web server
3. **Command-Specific** - Skipped for help, list, and version commands
4. **Terminal Clearing** - Clears terminal before display for clean presentation

## Update Notifications

Before displaying the banner, PhpHive checks for updates:

```
┌ Update available! 1.0.10 → 1.0.11
│ Run: composer global update phphive/cli
└
```

Update checks:
- Run once per day (cached for 24 hours)
- Non-blocking (2-second timeout)
- Cached in `~/.cache/phphive/update-check.json`
- Respect `XDG_CACHE_HOME` environment variable

## Technical Implementation

### Color Codes

PhpHive uses ANSI 256-color escape codes:

```php
echo "\e[38;5;{$color}m{$line}\e[0m" . PHP_EOL;
```

Where `{$color}` is a number from 0-255 representing the color palette.

### Gradient Selection

A random gradient is selected on each run:

```php
$themeName = array_rand($gradients);
$gradient = $gradients[$themeName];
```

This provides variety while maintaining the honey theme.

### Banner Structure

The banner consists of:
1. **6 lines of ASCII art** - Each with a different gradient color
2. **Tagline** - "PHP Monorepo Management powered by Turborepo"
3. **Version number** - Current PhpHive version

## Customization

### Disable Banner (Future Feature)

In future versions, you'll be able to disable the banner:

```bash
# Via environment variable
export HIVE_NO_BANNER=1
hive quality:test

# Via configuration file
# .phphive.json
{
  "display": {
    "banner": false
  }
}
```

### Custom Colors (Future Feature)

Future versions may support custom color schemes:

```json
{
  "display": {
    "banner": {
      "colors": [214, 208, 202, 178, 172, 136]
    }
  }
}
```

## Terminal Compatibility

The gradient banner works best with:

- **Modern terminals** - iTerm2, Hyper, Windows Terminal, Alacritty
- **256-color support** - Most modern terminals
- **UTF-8 encoding** - For proper character display

### Fallback Behavior

If your terminal doesn't support 256 colors:
- Colors may appear differently
- Gradients may not be smooth
- Basic ANSI colors will be used as fallback

### Testing Terminal Support

Check if your terminal supports 256 colors:

```bash
# Test 256-color support
for i in {0..255}; do
  printf "\e[38;5;${i}mColor ${i}\e[0m\n"
done
```

## Why Honey Theme?

The honey theme was chosen because:

1. **🐝 Hive Connection** - PhpHive's name and logo are bee-themed
2. **🍯 Warm & Welcoming** - Honey colors are warm and inviting
3. **✨ Professional** - Gold tones convey quality and professionalism
4. **🎨 Distinctive** - Unique color scheme stands out from other CLIs
5. **🌈 Variety** - 6 themes provide visual variety without being distracting

## Examples

### Honey Theme
```
[Warm golden gradient from light to dark]
PHP Monorepo Management powered by Turborepo v1.0.11
```

### Sunset Theme
```
[Orange to red gradient with warm tones]
PHP Monorepo Management powered by Turborepo v1.0.11
```

### Caramel Theme
```
[Brown caramel gradient with rich tones]
PHP Monorepo Management powered by Turborepo v1.0.11
```

## Performance

The banner is highly optimized:

- **Instant Display** - No noticeable delay
- **Cached Check** - Update checks cached for 24 hours
- **Non-Blocking** - Update check has 2-second timeout
- **Single Display** - Static flag prevents duplicate rendering

## Accessibility

For users who prefer minimal output:

```bash
# Use quiet mode to suppress banner
hive quality:test --quiet

# Or redirect output
hive quality:test > /dev/null
```

## Related Features

- **Update Checker** - Automatic version checking
- **Interactive Prompts** - Laravel Prompts integration
- **Command Suggestions** - Fuzzy matching for typos
- **Error Handling** - Beautiful error messages

---

**See Also:**
- [Getting Started](../getting-started/README.md)
- [Commands Reference](../commands/README.md)
- [Interactive Prompts](./interactive-prompts.md)
