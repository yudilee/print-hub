<?php

namespace App\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaService
{
    private ExpressionLanguage $language;
    private array $functions = [];

    public function __construct()
    {
        $this->language = new ExpressionLanguage();
        $this->registerFunctions();
    }

    protected function registerFunctions(): void
    {
        // ── Math Functions ─────────────────────────────────────

        $this->registerFunction('SUM', function (...$args) {
            return array_sum($args[0] ?? []);
        }, 'Computes the sum of values in an array',
        'SUM(array)', 'SUM(order.items.map(i => i.amount))');

        $this->registerFunction('AVG', function (...$args) {
            $items = $args[0] ?? [];
            return count($items) > 0 ? array_sum($items) / count($items) : 0;
        }, 'Computes the average of values in an array',
        'AVG(array)', 'AVG(order.items.map(i => i.price))');

        $this->registerFunction('MIN', function (...$args) {
            return !empty($args[0]) ? min($args[0]) : 0;
        }, 'Returns the minimum value from an array',
        'MIN(array)', 'MIN(prices)');

        $this->registerFunction('MAX', function (...$args) {
            return !empty($args[0]) ? max($args[0]) : 0;
        }, 'Returns the maximum value from an array',
        'MAX(array)', 'MAX(prices)');

        $this->registerFunction('ROUND', function (...$args) {
            return round($args[0] ?? 0, $args[1] ?? 0);
        }, 'Rounds a number to the specified decimal places',
        'ROUND(number, decimals)', 'ROUND(3.14159, 2)');

        $this->registerFunction('ABS', function (...$args) {
            return abs($args[0] ?? 0);
        }, 'Returns the absolute value of a number',
        'ABS(number)', 'ABS(-5)');

        $this->registerFunction('CEIL', function (...$args) {
            return ceil($args[0] ?? 0);
        }, 'Rounds a number up to the nearest integer',
        'CEIL(number)', 'CEIL(4.3)');

        $this->registerFunction('FLOOR', function (...$args) {
            return floor($args[0] ?? 0);
        }, 'Rounds a number down to the nearest integer',
        'FLOOR(number)', 'FLOOR(4.7)');

        $this->registerFunction('MOD', function (...$args) {
            return ($args[0] ?? 0) % ($args[1] ?? 1);
        }, 'Returns the remainder of a division',
        'MOD(dividend, divisor)', 'MOD(10, 3)');

        // ── String Functions ────────────────────────────────────

        $this->registerFunction('UPPER', function (...$args) {
            return strtoupper($args[0] ?? '');
        }, 'Converts text to uppercase',
        'UPPER(text)', 'UPPER("hello")');

        $this->registerFunction('LOWER', function (...$args) {
            return strtolower($args[0] ?? '');
        }, 'Converts text to lowercase',
        'LOWER(text)', 'LOWER("HELLO")');

        $this->registerFunction('TRIM', function (...$args) {
            return trim($args[0] ?? '');
        }, 'Removes leading and trailing whitespace',
        'TRIM(text)', 'TRIM("  hello  ")');

        $this->registerFunction('SUBSTRING', function (...$args) {
            return substr($args[0] ?? '', $args[1] ?? 0, $args[2] ?? null);
        }, 'Extracts a portion of text',
        'SUBSTRING(text, start, length)', 'SUBSTRING("hello", 0, 2)');

        $this->registerFunction('REPLACE', function (...$args) {
            return str_replace($args[1] ?? '', $args[2] ?? '', $args[0] ?? '');
        }, 'Replaces occurrences of a substring',
        'REPLACE(text, search, replace)', 'REPLACE("hello world", "world", "there")');

        $this->registerFunction('LENGTH', function (...$args) {
            return strlen($args[0] ?? '');
        }, 'Returns the length of text',
        'LENGTH(text)', 'LENGTH("hello")');

        $this->registerFunction('CONCAT', function (...$args) {
            return implode('', $args);
        }, 'Concatenates multiple strings together',
        'CONCAT(str1, str2, ...)', 'CONCAT("Hello", " ", "World")');

        $this->registerFunction('CONTAINS', function (...$args) {
            return str_contains($args[0] ?? '', $args[1] ?? '');
        }, 'Checks if text contains a substring',
        'CONTAINS(text, substring)', 'CONTAINS("hello world", "world")');

        // ── Date Functions ──────────────────────────────────────

        $this->registerFunction('NOW', function () {
            return date('Y-m-d H:i:s');
        }, 'Returns the current date and time',
        'NOW()', 'NOW()');

        $this->registerFunction('TODAY', function () {
            return date('Y-m-d');
        }, 'Returns the current date',
        'TODAY()', 'TODAY()');

        $this->registerFunction('DATE', function (...$args) {
            return date($args[0] ?? 'Y-m-d', strtotime($args[1] ?? 'now'));
        }, 'Formats a date string',
        'DATE(format, dateString)', 'DATE("d/m/Y", "2024-01-15")');

        $this->registerFunction('DATEADD', function (...$args) {
            $interval = $args[1] ?? 0;
            $unit = $args[0] ?? 'days';
            $dateStr = $args[2] ?? 'now';
            return date('Y-m-d', strtotime($dateStr . ' +' . $interval . ' ' . $unit));
        }, 'Adds an interval to a date',
        'DATEADD(unit, amount, date)', 'DATEADD("days", 7, "2024-01-01")');

        $this->registerFunction('DATEDIFF', function (...$args) {
            return (strtotime($args[0] ?? 'now') - strtotime($args[1] ?? 'now')) / 86400;
        }, 'Calculates the difference in days between two dates',
        'DATEDIFF(date1, date2)', 'DATEDIFF("2024-01-15", "2024-01-01")');

        $this->registerFunction('YEAR', function (...$args) {
            return (int) date('Y', strtotime($args[0] ?? 'now'));
        }, 'Extracts the year from a date',
        'YEAR(date)', 'YEAR("2024-01-15")');

        $this->registerFunction('MONTH', function (...$args) {
            return (int) date('m', strtotime($args[0] ?? 'now'));
        }, 'Extracts the month from a date (1-12)',
        'MONTH(date)', 'MONTH("2024-01-15")');

        $this->registerFunction('DAY', function (...$args) {
            return (int) date('d', strtotime($args[0] ?? 'now'));
        }, 'Extracts the day of the month (1-31)',
        'DAY(date)', 'DAY("2024-01-15")');

        // ── Logical Functions ──────────────────────────────────

        $this->registerFunction('IF', function (...$args) {
            return $args[0] ? ($args[1] ?? null) : ($args[2] ?? null);
        }, 'Conditional evaluation — returns value_if_true if condition is truthy, otherwise value_if_false',
        'IF(condition, value_if_true, value_if_false)', 'IF(total > 1000, "High", "Low")');

        // ── Type Conversion Functions ───────────────────────────

        $this->registerFunction('TOSTRING', function (...$args) {
            return (string) ($args[0] ?? '');
        }, 'Converts a value to string',
        'TOSTRING(value)', 'TOSTRING(42)');

        $this->registerFunction('TONUMBER', function (...$args) {
            return (float) ($args[0] ?? 0);
        }, 'Converts a value to a number',
        'TONUMBER(value)', 'TONUMBER("42.5")');

        $this->registerFunction('TODATE', function (...$args) {
            return date('Y-m-d', strtotime($args[0] ?? 'now'));
        }, 'Converts a string to a date',
        'TODATE(value)', 'TODATE("2024-01-15")');

        $this->registerFunction('FORMAT', function (...$args) {
            $value = $args[0] ?? '';
            if (is_numeric($value)) {
                return number_format((float) $value, $args[1] ?? 0);
            }
            return (string) $value;
        }, 'Formats a number with grouped thousands and decimal places',
        'FORMAT(value, decimals)', 'FORMAT(12345.678, 2)');
    }

    /**
     * Evaluate an expression with the given data context.
     */
    public function evaluate(string $expression, array $data = []): mixed
    {
        try {
            return $this->language->evaluate($expression, $data);
        } catch (\Exception $e) {
            throw new \Exception("Formula error: " . $e->getMessage());
        }
    }

    /**
     * Validate an expression and return validation result.
     */
    public function validate(string $expression): array
    {
        try {
            $this->language->parse($expression, []);
            return ['valid' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all registered functions with metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFunctions(): array
    {
        return array_values($this->functions);
    }

    /**
     * Get functions grouped by category.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getFunctionsGrouped(): array
    {
        $grouped = [];
        foreach ($this->functions as $fn) {
            $cat = $fn['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $fn;
        }
        return $grouped;
    }

    /**
     * Register a new function with ExpressionLanguage and store metadata.
     */
    private function registerFunction(
        string $name,
        callable $evaluator,
        string $description = '',
        string $syntax = '',
        string $example = ''
    ): void {
        $compiler = function () use ($name) {
            return sprintf('$this->functions["%s"]["evaluator"](...%s)', $name, 'func_get_args()');
        };

        // Register with ExpressionLanguage
        $this->language->register($name, $compiler, function ($arguments, ...$args) use ($evaluator) {
            return $evaluator(...$args);
        });

        // Store metadata
        $this->functions[$name] = [
            'name' => $name,
            'category' => $this->getCategory($name),
            'description' => $description,
            'syntax' => $syntax,
            'example' => $example,
        ];
    }

    private function getCategory(string $name): string
    {
        $mathNames = ['SUM', 'AVG', 'MIN', 'MAX', 'ROUND', 'ABS', 'CEIL', 'FLOOR', 'MOD'];
        $stringNames = ['UPPER', 'LOWER', 'TRIM', 'SUBSTRING', 'REPLACE', 'LENGTH', 'CONCAT', 'CONTAINS'];
        $dateNames = ['NOW', 'TODAY', 'DATE', 'DATEADD', 'DATEDIFF', 'YEAR', 'MONTH', 'DAY'];
        $logicalNames = ['IF'];
        $conversionNames = ['TOSTRING', 'TONUMBER', 'TODATE', 'FORMAT'];

        if (in_array($name, $mathNames)) return 'Math';
        if (in_array($name, $stringNames)) return 'String';
        if (in_array($name, $dateNames)) return 'Date';
        if (in_array($name, $logicalNames)) return 'Logical';
        if (in_array($name, $conversionNames)) return 'Conversion';

        return 'Other';
    }
}
