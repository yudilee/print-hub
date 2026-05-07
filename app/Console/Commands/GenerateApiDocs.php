<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * Generates API documentation from route annotations.
 *
 * Scans routes/api.php for registered routes, extracts controller method
 * docblocks, and generates a Markdown file at docs/api.md grouped by prefix.
 */
class GenerateApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print-hub:generate-api-docs
                            {--path= : Output path for the generated docs (default: docs/api.md)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API documentation from route annotations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputPath = $this->option('path') ?: base_path('docs/api.md');

        $this->info('Scanning API routes...');

        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Only include routes from routes/api.php (they typically start with api/ or a version prefix)
            if (!str_starts_with($uri, 'api/') && !str_starts_with($uri, 'v1/') && !str_starts_with($uri, 'print-hub/') && !str_starts_with($uri, 'approvals/')) {
                continue;
            }

            $methods = $route->methods();
            // Filter out HEAD method (duplicate of GET)
            $methods = array_filter($methods, fn ($m) => $m !== 'HEAD');
            // Filter out generic OPTIONS
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS');

            if (empty($methods)) {
                continue;
            }

            $action = $route->getAction();
            $controllerAction = $action['controller'] ?? $action['uses'] ?? null;

            $description = '';
            $controllerClass = '';
            $controllerMethod = '';

            if ($controllerAction && is_string($controllerAction) && str_contains($controllerAction, '@')) {
                [$controllerClass, $controllerMethod] = explode('@', $controllerAction);
                $description = $this->extractMethodDescription($controllerClass, $controllerMethod);
            } elseif ($controllerAction instanceof \Closure) {
                $description = 'Closure-based route';
            }

            // Determine prefix group
            $prefix = $this->determinePrefix($uri);

            $apiRoutes[] = [
                'methods'  => implode('|', $methods),
                'uri'      => '/' . $uri,
                'action'   => $controllerAction ?: 'Closure',
                'description' => $description,
                'prefix'   => $prefix,
            ];
        }

        // Sort routes by prefix then URI
        usort($apiRoutes, function ($a, $b) {
            return [$a['prefix'], $a['uri']] <=> [$b['prefix'], $b['uri']];
        });

        // Group by prefix
        $grouped = [];
        foreach ($apiRoutes as $route) {
            $grouped[$route['prefix']][] = $route;
        }

        $markdown = $this->buildMarkdown($grouped);

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $markdown);

        $routeCount = count($apiRoutes);
        $this->info("Generated API documentation for {$routeCount} routes.");
        $this->info("Output: {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * Determine the API prefix group from the URI.
     */
    private function determinePrefix(string $uri): string
    {
        if (str_starts_with($uri, 'api/print-hub/') || str_starts_with($uri, 'print-hub/')) {
            return 'Print Hub Agent API';
        }
        if (str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'v1/')) {
            return 'v1 — Client App API';
        }
        if (str_starts_with($uri, 'approvals/')) {
            return 'Approvals API';
        }
        if (str_starts_with($uri, 'api/')) {
            return 'General API';
        }

        return 'Other';
    }

    /**
     * Extract the description from a controller method's docblock.
     */
    private function extractMethodDescription(string $controllerClass, string $method): string
    {
        if (!class_exists($controllerClass)) {
            return '';
        }

        try {
            $reflection = new ReflectionMethod($controllerClass, $method);
            $docblock = $reflection->getDocComment();

            if (!$docblock) {
                return '';
            }

            // Extract the first meaningful sentence from the docblock
            $lines = explode("\n", $docblock);
            $description = '';

            foreach ($lines as $line) {
                $line = trim($line);
                $line = preg_replace('/^\/\*\*|^\*\/|^\*\s?/', '', $line);

                // Skip annotation lines
                if (str_starts_with(trim($line), '@') || empty(trim($line))) {
                    continue;
                }

                $description .= ' ' . trim($line);
            }

            $description = trim($description);

            // Take only the first sentence
            if (preg_match('/^([^.!?]*[.!?])/', $description, $matches)) {
                $description = $matches[1];
            }

            return $description;
        } catch (\ReflectionException $e) {
            return '';
        }
    }

    /**
     * Build the Markdown documentation content.
     */
    private function buildMarkdown(array $grouped): string
    {
        $md = "# Print Hub API Documentation\n\n";
        $md .= "> Auto-generated on " . now()->format('Y-m-d H:i:s') . "\n\n";
        $md .= "---\n\n";

        $totalRoutes = 0;

        foreach ($grouped as $prefix => $routes) {
            $totalRoutes += count($routes);
            $md .= "## {$prefix}\n\n";
            $md .= "| Method | URI | Controller | Description |\n";
            $md .= "|--------|-----|------------|-------------|\n";

            foreach ($routes as $route) {
                $action = $route['action'];
                // Shorten the action to just the class@method
                if (str_contains($action, '@')) {
                    $parts = explode('@', $action);
                    $classParts = explode('\\', $parts[0]);
                    $shortClass = end($classParts);
                    $action = $shortClass . '@' . $parts[1];
                }

                $methodBadge = $this->methodBadge($route['methods']);
                $description = $route['description'] ?: '*No description*';

                $md .= "| {$methodBadge} | `{$route['uri']}` | `{$action}` | {$description} |\n";
            }

            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "*Total: {$totalRoutes} API routes documented.*\n";

        return $md;
    }

    /**
     * Return a styled badge for the HTTP method.
     */
    private function methodBadge(string $methods): string
    {
        $parts = explode('|', $methods);

        $badges = array_map(function ($method) {
            $colors = [
                'GET'    => 'green',
                'POST'   => 'blue',
                'PUT'    => 'orange',
                'PATCH'  => 'orange',
                'DELETE' => 'red',
            ];
            $color = $colors[strtoupper($method)] ?? 'gray';

            return "<span style=\"color: {$color}; font-weight: bold;\">{$method}</span>";
        }, $parts);

        return implode(' ', $badges);
    }
}
