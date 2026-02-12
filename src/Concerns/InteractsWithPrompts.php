<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use Closure;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\pause;

use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

/**
 * Laravel Prompts Integration Trait.
 *
 * This trait provides a complete integration with Laravel Prompts, a beautiful
 * and user-friendly PHP command-line prompt library. It wraps all Laravel Prompts
 * functions as protected methods, making them easily accessible in commands.
 *
 * Laravel Prompts features:
 * - Beautiful, colorful terminal UI
 * - Interactive input with validation
 * - Single and multi-select menus
 * - Auto-completion and search
 * - Progress bars and spinners
 * - Tables and formatted output
 * - Informational messages (intro, outro, info, warning, error)
 *
 * All prompt methods support:
 * - Custom validation with closures
 * - Required field enforcement
 * - Default values
 * - Helpful hints and placeholders
 *
 * Example usage:
 * ```php
 * // Display intro/outro messages
 * $this->intro('Welcome to the installer');
 * $this->outro('Installation complete!');
 *
 * // Get user input
 * $name = $this->text('What is your name?', required: true);
 * $confirmed = $this->confirm('Continue?', default: true);
 *
 * // Select from options
 * $env = $this->select('Environment', ['dev', 'staging', 'prod']);
 * $features = $this->multiselect('Features', ['api', 'web', 'cli']);
 *
 * // Show progress
 * $this->progress('Installing', $packages, function ($package) {
 *     $this->install($package);
 * });
 *
 * // Display data
 * $this->table(['Name', 'Status'], $rows);
 * ```
 */
trait InteractsWithPrompts
{
    /**
     * Clear the terminal screen.
     *
     * Clears all previous output from the terminal, providing a clean
     * slate for new output. Useful for creating a fresh view or
     * removing clutter before displaying important information.
     */
    protected function clear(): void
    {
        clear();
    }

    /**
     * Display an intro message to start a command flow.
     *
     * Intro messages are displayed with a distinctive style to mark the
     * beginning of a command execution. They help users understand what
     * the command will do.
     *
     * @param string $message The intro message to display
     */
    protected function intro(string $message): void
    {
        intro($message);
    }

    /**
     * Display an outro message to conclude a command flow.
     *
     * Outro messages are displayed with a distinctive style to mark the
     * successful completion of a command. They provide closure and
     * confirmation to the user.
     *
     * @param string $message The outro message to display
     */
    protected function outro(string $message): void
    {
        outro($message);
    }

    /**
     * Display an informational message.
     *
     * Info messages are displayed in a neutral color (typically blue/cyan)
     * and are used for general information that doesn't require special
     * attention.
     *
     * @param string $message The informational message to display
     */
    protected function info(string $message): void
    {
        info($message);
    }

    /**
     * Display a warning message.
     *
     * Warning messages are displayed in yellow/orange and indicate
     * something that requires attention but isn't an error.
     *
     * @param string $message The warning message to display
     */
    protected function warning(string $message): void
    {
        warning($message);
    }

    /**
     * Display an error message.
     *
     * Error messages are displayed in red and indicate a problem or
     * failure that occurred during command execution.
     *
     * @param string $message The error message to display
     */
    protected function error(string $message): void
    {
        error($message);
    }

    /**
     * Display a note with optional type styling.
     *
     * Notes are boxed messages that can be styled as info, warning, error,
     * or alert. They're useful for displaying important information that
     * needs to stand out.
     *
     * The parameters can be used in two ways:
     * 1. note($message, $type) - Single message with optional type
     * 2. note($message, $title) - Message with a title (type defaults to null)
     *
     * Examples:
     * ```php
     * // Simple note
     * $this->note('This is important information');
     *
     * // Note with type
     * $this->note('Something went wrong', 'error');
     *
     * // Note with title
     * $this->note('Get your keys from: https://example.com', 'Authentication Keys');
     * ```
     *
     * @param string      $message The note message to display
     * @param string|null $type    Optional type ('info', 'warning', 'error', 'alert') or title
     */
    protected function note(string $message, ?string $type = null): void
    {
        note($message, $type);
    }

    /**
     * Prompt the user for text input.
     *
     * Displays an interactive text input prompt with support for validation,
     * default values, placeholders, and hints. The prompt will loop until
     * valid input is provided.
     *
     * @param  string      $label       The prompt label
     * @param  string      $placeholder Placeholder text shown when empty
     * @param  string      $default     Default value if user presses enter
     * @param  bool|string $required    Whether input is required (or custom error message)
     * @param  mixed       $validate    Validation closure or null
     * @param  string      $hint        Helpful hint displayed below the input
     * @return string      The user's input
     */
    protected function text(
        string $label,
        string $placeholder = '',
        string $default = '',
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
    ): string {
        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user for multi-line text input.
     *
     * Similar to text() but allows multiple lines of input. Useful for
     * descriptions, commit messages, or any content that spans multiple lines.
     *
     * @param  string      $label       The prompt label
     * @param  string      $placeholder Placeholder text shown when empty
     * @param  string      $default     Default value if user presses enter
     * @param  bool|string $required    Whether input is required (or custom error message)
     * @param  mixed       $validate    Validation closure or null
     * @param  string      $hint        Helpful hint displayed below the input
     * @param  int         $rows        Number of rows to display (default: 5)
     * @return string      The user's multi-line input
     */
    protected function textarea(
        string $label,
        string $placeholder = '',
        string $default = '',
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
        int $rows = 5,
    ): string {
        return textarea(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required,
            validate: $validate,
            hint: $hint,
            rows: $rows,
        );
    }

    /**
     * Prompt the user for password input.
     *
     * Displays a password input prompt where characters are masked as they're
     * typed. Supports validation and required field enforcement.
     *
     * @param  string      $label       The prompt label
     * @param  string      $placeholder Placeholder text shown when empty
     * @param  bool|string $required    Whether input is required (or custom error message)
     * @param  mixed       $validate    Validation closure or null
     * @param  string      $hint        Helpful hint displayed below the input
     * @return string      The user's password input
     */
    protected function password(
        string $label,
        string $placeholder = '',
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
    ): string {
        return password(
            label: $label,
            placeholder: $placeholder,
            required: $required,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user for yes/no confirmation.
     *
     * Displays a confirmation prompt with customizable yes/no labels.
     * Returns true for yes, false for no.
     *
     * @param  string      $label    The confirmation question
     * @param  bool        $default  Default value (true for yes, false for no)
     * @param  string      $yes      Label for yes option (default: 'Yes')
     * @param  string      $no       Label for no option (default: 'No')
     * @param  bool|string $required Whether selection is required
     * @param  mixed       $validate Validation closure or null
     * @param  string      $hint     Helpful hint displayed below the prompt
     * @return bool        True if user confirmed, false otherwise
     */
    protected function confirm(
        string $label,
        bool $default = true,
        string $yes = 'Yes',
        string $no = 'No',
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
    ): bool {
        return confirm(
            label: $label,
            default: $default,
            yes: $yes,
            no: $no,
            required: $required,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user to select a single option from a list.
     *
     * Displays an interactive menu where the user can navigate with arrow
     * keys and select an option with enter. Returns the selected option's
     * key or value.
     *
     * @param  array<int|string, string> $options  Array of options (key => label)
     * @param  string                    $label    The prompt label
     * @param  int|string|null           $default  Default selected option key
     * @param  int                       $scroll   Number of visible options (default: 5)
     * @param  mixed                     $validate Validation closure or null
     * @param  string                    $hint     Helpful hint displayed below the menu
     * @return int|string                The selected option's key
     */
    protected function select(
        string $label,
        array $options,
        int|string|null $default = null,
        int $scroll = 5,
        mixed $validate = null,
        string $hint = '',
    ): int|string {
        return select(
            label: $label,
            options: $options,
            default: $default,
            scroll: $scroll,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user to select multiple options from a list.
     *
     * Displays an interactive menu where the user can toggle multiple
     * options with space and confirm with enter. Returns an array of
     * selected option keys.
     *
     * @param  array<int|string, string> $options  Array of options (key => label)
     * @param  string                    $label    The prompt label
     * @param  array<int|string>         $default  Default selected option keys
     * @param  int                       $scroll   Number of visible options (default: 5)
     * @param  bool|string               $required Whether at least one selection is required
     * @param  mixed                     $validate Validation closure or null
     * @param  string                    $hint     Helpful hint displayed below the menu
     * @return array<int|string>         Array of selected option keys
     */
    protected function multiselect(
        string $label,
        array $options,
        array $default = [],
        int $scroll = 5,
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
    ): array {
        return multiselect(
            label: $label,
            options: $options,
            default: $default,
            scroll: $scroll,
            required: $required,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user for text input with auto-completion suggestions.
     *
     * Similar to text() but provides auto-completion as the user types.
     * Suggestions can be a static array or a closure that returns suggestions
     * based on the current input.
     *
     * @param  array<string>|Closure(string): array<string> $options     Array of suggestions or closure
     * @param  string                                       $label       The prompt label
     * @param  string                                       $placeholder Placeholder text
     * @param  string                                       $default     Default value
     * @param  int                                          $scroll      Number of visible suggestions
     * @param  bool|string                                  $required    Whether input is required
     * @param  mixed                                        $validate    Validation closure or null
     * @param  string                                       $hint        Helpful hint
     * @return string                                       The user's input
     */
    protected function suggest(
        string $label,
        array|Closure $options,
        string $placeholder = '',
        string $default = '',
        int $scroll = 5,
        bool|string $required = false,
        mixed $validate = null,
        string $hint = '',
    ): string {
        return suggest(
            label: $label,
            options: $options,
            placeholder: $placeholder,
            default: $default,
            scroll: $scroll,
            required: $required,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Prompt the user to search and select from dynamically loaded options.
     *
     * Displays a search input that calls a closure to fetch matching options
     * as the user types. Useful for large datasets or API-based searches.
     *
     * @param  Closure(string): array<int|string, string> $options     Closure that returns options based on search query
     * @param  string                                     $label       The prompt label
     * @param  string                                     $placeholder Placeholder text
     * @param  int                                        $scroll      Number of visible options
     * @param  mixed                                      $validate    Validation closure or null
     * @param  string                                     $hint        Helpful hint
     * @return int|string                                 The selected option's key
     */
    protected function search(
        string $label,
        Closure $options,
        string $placeholder = '',
        int $scroll = 5,
        mixed $validate = null,
        string $hint = '',
    ): int|string {
        return search(
            label: $label,
            options: $options,
            placeholder: $placeholder,
            scroll: $scroll,
            validate: $validate,
            hint: $hint,
        );
    }

    /**
     * Pause execution and wait for the user to press enter.
     *
     * Useful for giving users time to read output before continuing,
     * or to create breakpoints in command execution.
     *
     * @param string $message The message to display (default: 'Press enter to continue...')
     */
    protected function pause(string $message = 'Press enter to continue...'): void
    {
        pause($message);
    }

    /**
     * Display data in a formatted table.
     *
     * Renders a table with headers and rows. Automatically adjusts column
     * widths to fit content. Useful for displaying structured data.
     *
     * @param array<string>                  $headers Array of column headers
     * @param array<int, array<int, string>> $rows    Array of rows (each row is an array of cell values)
     */
    protected function table(array $headers, array $rows): void
    {
        table($headers, $rows);
    }

    /**
     * Display a progress bar while iterating over items.
     *
     * Shows a visual progress bar that updates as items are processed.
     * Can accept an iterable (array, generator) or a count. If a callback
     * is provided, it's called for each item.
     *
     * @param  int|iterable<mixed>                $steps    Items to iterate or total count
     * @param  string                             $label    The progress bar label
     * @param  Closure(mixed, Progress):void|null $callback Optional callback for each item
     * @param  string                             $hint     Helpful hint
     * @return array<mixed>                       Returns processed items if no callback provided
     */
    protected function progress(
        string $label,
        iterable|int $steps,
        ?Closure $callback = null,
        string $hint = '',
    ): array {
        $result = progress(
            label: $label,
            steps: $steps,
            callback: $callback,
            hint: $hint,
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Display a spinner while executing a callback.
     *
     * Shows an animated spinner during long-running operations. The spinner
     * automatically stops when the callback completes and returns the
     * callback's return value.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn $callback The operation to execute
     * @param  string             $message  The spinner message
     * @return TReturn            The callback's return value
     */
    protected function spin(Closure $callback, string $message = ''): mixed
    {
        return spin($callback, $message);
    }
}
