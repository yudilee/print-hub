<?php

namespace App\Services;

class FormulaEditorService
{
    /**
     * Built-in functions available in the formula editor.
     */
    public static function getBuiltInFunctions(): array
    {
        return [
            // Math
            ['name' => 'ABS', 'params' => 'number', 'desc' => 'Absolute value'],
            ['name' => 'ROUND', 'params' => 'number, decimals', 'desc' => 'Round to N decimals'],
            ['name' => 'CEIL', 'params' => 'number', 'desc' => 'Round up to nearest integer'],
            ['name' => 'FLOOR', 'params' => 'number', 'desc' => 'Round down to nearest integer'],
            ['name' => 'MIN', 'params' => 'a, b', 'desc' => 'Smallest of two values'],
            ['name' => 'MAX', 'params' => 'a, b', 'desc' => 'Largest of two values'],
            ['name' => 'SUM', 'params' => 'a, b, ...', 'desc' => 'Sum of values'],
            ['name' => 'AVG', 'params' => 'a, b, ...', 'desc' => 'Average of values'],
            ['name' => 'POW', 'params' => 'base, exp', 'desc' => 'Raise to power'],

            // String
            ['name' => 'UPPER', 'params' => 'text', 'desc' => 'Convert to uppercase'],
            ['name' => 'LOWER', 'params' => 'text', 'desc' => 'Convert to lowercase'],
            ['name' => 'TRIM', 'params' => 'text', 'desc' => 'Remove leading/trailing spaces'],
            ['name' => 'LEN', 'params' => 'text', 'desc' => 'Length of string'],
            ['name' => 'LEFT', 'params' => 'text, count', 'desc' => 'First N characters'],
            ['name' => 'RIGHT', 'params' => 'text, count', 'desc' => 'Last N characters'],
            ['name' => 'MID', 'params' => 'text, start, count', 'desc' => 'Substring from position'],
            ['name' => 'CONCAT', 'params' => 'a, b, ...', 'desc' => 'Concatenate strings'],
            ['name' => 'REPLACE', 'params' => 'text, search, replace', 'desc' => 'Replace text'],
            ['name' => 'IF', 'params' => 'condition, true_val, false_val', 'desc' => 'Conditional value'],

            // Date
            ['name' => 'NOW', 'params' => '', 'desc' => 'Current date/time'],
            ['name' => 'TODAY', 'params' => '', 'desc' => 'Current date'],
            ['name' => 'DATE', 'params' => 'year, month, day', 'desc' => 'Create date'],
            ['name' => 'YEAR', 'params' => 'date', 'desc' => 'Extract year'],
            ['name' => 'MONTH', 'params' => 'date', 'desc' => 'Extract month'],
            ['name' => 'DAY', 'params' => 'date', 'desc' => 'Extract day'],
            ['name' => 'DATEADD', 'params' => 'date, interval, number', 'desc' => 'Add to date'],
            ['name' => 'DATEDIFF', 'params' => 'date1, date2', 'desc' => 'Days between dates'],

            // Conversion
            ['name' => 'STR', 'params' => 'number', 'desc' => 'Convert to string'],
            ['name' => 'VAL', 'params' => 'text', 'desc' => 'Convert to number'],
            ['name' => 'CURRENCY', 'params' => 'number', 'desc' => 'Format as currency'],
            ['name' => 'TERBILANG', 'params' => 'number', 'desc' => 'Number to words (Indonesian)'],
        ];
    }

    /**
     * Validate a formula expression and return errors/autocomplete suggestions.
     */
    public static function validate(string $expression): array
    {
        $errors = [];
        $suggestions = [];

        // Check for unmatched braces
        $open = substr_count($expression, '(');
        $close = substr_count($expression, ')');
        if ($open !== $close) {
            $errors[] = 'Unmatched parentheses: ' . $open . ' opening vs ' . $close . ' closing';
        }

        // Check for unmatched braces in field references
        preg_match_all('/\{([^}]*)\}/', $expression, $fieldMatches);
        foreach ($fieldMatches[1] as $field) {
            if (empty(trim($field))) {
                $errors[] = 'Empty field reference: {}';
            }
            // Could add validation that field exists in schema
        }

        // Check function names
        $functions = self::getBuiltInFunctions();
        $funcNames = array_column($functions, 'name');

        preg_match_all('/([A-Z_]+)\s*\(/i', $expression, $funcMatches);
        foreach ($funcMatches[1] as $func) {
            $funcUpper = strtoupper($func);
            if (!in_array($funcUpper, $funcNames) && !in_array($func, ['AND', 'OR', 'NOT'])) {
                $suggestions[] = 'Unknown function: ' . $func . '. Did you mean: ' . self::fuzzyFind($funcUpper, $funcNames);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'suggestions' => $suggestions,
        ];
    }

    private static function fuzzyFind(string $input, array $candidates): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($candidates as $candidate) {
            $score = similar_text(strtoupper($input), strtoupper($candidate));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
        return $best ?: $input;
    }

    /**
     * Generate a running total configuration from UI parameters.
     */
    public static function buildRunningTotal(array $config): array
    {
        return [
            'type' => 'running_total',
            'field' => $config['field'] ?? '',
            'operation' => $config['operation'] ?? 'sum', // sum, count, avg, min, max
            'evaluate' => $config['evaluate'] ?? 'always', // always, on_change, on_change_of
            'evaluate_field' => $config['evaluate_field'] ?? '',
            'reset' => $config['reset'] ?? 'never', // never, on_change, at_number, on_change_of
            'reset_field' => $config['reset_field'] ?? '',
            'reset_number' => intval($config['reset_number'] ?? 0),
        ];
    }
}
