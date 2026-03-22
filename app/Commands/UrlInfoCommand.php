<?php

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

/**
 * Shows URL status, robots.txt allowance, canonical tag, and TTFB.
 */
class UrlInfoCommand extends Command
{
    /** @var string The command signature. */
    protected $signature = 'urlinfo {url? : URL to inspect} {--staging : Invert robots result colors for staging checks}';

    /** @var string The command description. */
    protected $description = 'Show URL status, robots allowance, and TTFB';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->output->setDecorated(true);

        $urlArg = $this->argument('url');
        if (! $urlArg) {
            $this->call('help', ['command_name' => 'urlinfo']);

            return;
        }

        $url = $this->normalizeUrl($urlArg);
        $isStaging = (bool) $this->option('staging');

        $ttfbMs = null;
        $client = new Client([
            'timeout' => 20,
            'allow_redirects' => false,
            'http_errors' => false,
            'on_stats' => function (TransferStats $stats) use (&$ttfbMs): void {
                $startTransfer = $stats->getHandlerStat('starttransfer_time');
                if (is_numeric($startTransfer) && $startTransfer > 0) {
                    $ttfbMs = (int) round(((float) $startTransfer) * 1000);
                }
            },
        ]);

        try {
            $response = $client->get($url);
        } catch (GuzzleException $e) {
            $this->error('Could not fetch URL: '.$e->getMessage());

            return;
        }

        $status = $response->getStatusCode();
        $friendlyName = $response->getReasonPhrase() ?: '-';
        $htmlBody = $status === 200 ? (string) $response->getBody() : '';

        $robotsCell = '<fg=gray>N/A</>';
        $canonicalCell = '<fg=gray>N/A</>';
        if ($status === 200) {
            $robotsCell = $this->buildRobotsCell($url, $isStaging);
            $canonicalCell = $this->buildCanonicalCell($url, $htmlBody);
        }

        $this->table(
            ['HTTP Status', 'Friendly Name', 'robots.txt', 'Canonical', 'Server response time (TTFB)'],
            [[
                $this->formatStatus($status),
                $friendlyName,
                $robotsCell,
                $canonicalCell,
                $this->formatTtfb($ttfbMs),
            ]]
        );
    }

    /**
     * Normalizes a URL by adding https:// if missing.
     *
     * @param  string  $url  The raw URL input
     * @return string The normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * Builds the robots.txt status cell content.
     *
     * @param  string  $url  The URL being checked
     * @param  bool  $isStaging  Whether staging mode is enabled
     * @return string The formatted cell content
     */
    private function buildRobotsCell(string $url, bool $isStaging): string
    {
        $parsed = parse_url($url);
        if (! isset($parsed['scheme'], $parsed['host'])) {
            return '<fg=gray>N/A</>';
        }

        $base = $parsed['scheme'].'://'.$parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':'.$parsed['port'];
        }

        $robotsUrl = $base.'/robots.txt';
        $path = $parsed['path'] ?? '/';
        if (isset($parsed['query'])) {
            $path .= '?'.$parsed['query'];
        }

        $client = new Client([
            'timeout' => 10,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);

        try {
            $robotsResponse = $client->get($robotsUrl);
        } catch (GuzzleException) {
            return '<fg=gray>N/A</>';
        }

        if ($robotsResponse->getStatusCode() !== 200) {
            return '<fg=gray>N/A</>';
        }

        $robotsBody = (string) $robotsResponse->getBody();
        $allowed = $this->isUrlAllowedByRobots($robotsBody, $path);

        if ($allowed) {
            return $isStaging ? '<fg=red>ALLOWED</>' : '<fg=green>ALLOWED</>';
        }

        return $isStaging ? '<fg=green>DISALLOWED</>' : '<fg=red>DISALLOWED</>';
    }

    /**
     * Checks if a URL path is allowed based on robots.txt rules.
     *
     * @param  string  $robotsContent  The robots.txt content
     * @param  string  $urlPath  The URL path to check
     */
    private function isUrlAllowedByRobots(string $robotsContent, string $urlPath): bool
    {
        $lines = preg_split('/\r\n|\r|\n/', $robotsContent) ?: [];
        $rules = [];
        $currentAgents = [];
        $hasRulesInGroup = false;

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = array_map('trim', explode(':', $line, 2));
            $directive = strtolower($directive);
            $value = trim($value);

            if ($directive === 'user-agent') {
                if ($hasRulesInGroup) {
                    $currentAgents = [];
                    $hasRulesInGroup = false;
                }
                $currentAgents[] = strtolower($value);

                continue;
            }

            if ($directive !== 'allow' && $directive !== 'disallow') {
                continue;
            }

            if (! in_array('*', $currentAgents, true)) {
                continue;
            }

            $hasRulesInGroup = true;
            $rules[] = [
                'type' => $directive,
                'path' => $value,
            ];
        }

        if (empty($rules)) {
            return true;
        }

        $bestMatch = null;
        foreach ($rules as $rule) {
            $rulePath = (string) $rule['path'];
            if ($rulePath === '') {
                continue;
            }

            if (! $this->robotsPathMatches($rulePath, $urlPath)) {
                continue;
            }

            $length = strlen($rulePath);
            if ($bestMatch === null || $length > $bestMatch['length'] || ($length === $bestMatch['length'] && $rule['type'] === 'allow')) {
                $bestMatch = [
                    'type' => $rule['type'],
                    'length' => $length,
                ];
            }
        }

        if ($bestMatch === null) {
            return true;
        }

        return $bestMatch['type'] === 'allow';
    }

    /**
     * Checks if a URL path matches a robots.txt rule pattern.
     *
     * @param  string  $rulePath  The pattern from robots.txt
     * @param  string  $urlPath  The URL path to match
     */
    private function robotsPathMatches(string $rulePath, string $urlPath): bool
    {
        $escaped = preg_quote($rulePath, '/');
        $pattern = str_replace('\\*', '.*', $escaped);

        if (str_ends_with($rulePath, '$')) {
            $pattern = rtrim($pattern, '\\$').'$';
        } else {
            $pattern .= '.*';
        }

        return (bool) preg_match('/^'.$pattern.'/i', $urlPath);
    }

    /**
     * Formats an HTTP status code with color.
     *
     * @param  int  $status  The HTTP status code
     * @return string The formatted status
     */
    private function formatStatus(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300 => "<fg=green>{$status}</>",
            $status >= 300 && $status < 400 => "<fg=yellow>{$status}</>",
            $status >= 400 && $status < 500 => "<fg=red>{$status}</>",
            $status >= 500 => "<fg=red;options=bold>{$status}</>",
            default => "<fg=gray>{$status}</>",
        };
    }

    /**
     * Formats TTFB (Time To First Byte) with color.
     *
     * @param  int|null  $ttfbMs  TTFB in milliseconds
     * @return string The formatted TTFB
     */
    private function formatTtfb(?int $ttfbMs): string
    {
        if ($ttfbMs === null) {
            return '<fg=gray>N/A</>';
        }

        if ($ttfbMs < 500) {
            return "<fg=green>{$ttfbMs} ms</>";
        }

        if ($ttfbMs < 1000) {
            return "<fg=yellow>{$ttfbMs} ms</>";
        }

        return "<fg=red>{$ttfbMs} ms</>";
    }

    /**
     * Builds the canonical tag check cell.
     *
     * @param  string  $url  The requested URL
     * @param  string  $htmlBody  The HTML body content
     * @return string The formatted cell content
     */
    private function buildCanonicalCell(string $url, string $htmlBody): string
    {
        $canonicalUrl = $this->extractCanonical($htmlBody);

        if ($canonicalUrl === null) {
            return '<fg=gray>NOT SET</>';
        }

        $requestedDomain = $this->extractDomain($url);
        $canonicalDomain = $this->extractDomain($canonicalUrl);

        if ($canonicalDomain === null) {
            return '<fg=gray>INVALID</>';
        }

        if (strtolower($requestedDomain) === strtolower($canonicalDomain)) {
            return '<fg=green>YES</>';
        }

        return "<fg=red>NO ({$canonicalDomain})</>";
    }

    /**
     * Extracts the canonical URL from HTML content.
     *
     * @param  string  $htmlBody  The HTML body
     * @return string|null The canonical URL or null if not found
     */
    private function extractCanonical(string $htmlBody): ?string
    {
        if (preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $htmlBody, $matches)) {
            return html_entity_decode($matches[1]);
        }

        if (preg_match('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\']/i', $htmlBody, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }

    /**
     * Extracts the domain from a URL.
     *
     * @param  string  $url  The URL
     * @return string|null The domain or null
     */
    private function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);

        return $parsed['host'] ?? null;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void {}
}
