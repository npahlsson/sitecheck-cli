<?php

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

/**
 * Checks HTTP status codes and follows redirects for a given URL.
 */
class HttpStatusCommand extends Command
{
    /** @var int Maximum number of redirects to follow before stopping. */
    private const MAX_REDIRECTS = 10;

    /** @var string The command signature. */
    protected $signature = 'httpstatus {url? : The URL to check}';

    /** @var string The command description. */
    protected $description = 'Check HTTP status code and redirects for a URL';

    /** @var Client The Guzzle HTTP client instance. */
    private Client $client;

    /** @var array<array{depth: int, url: string, status: int|string, reason: ?string, x_redirect_by: ?string, location: ?string}> */
    private array $hops = [];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->output->setDecorated(true);

        $url = $this->argument('url');
        if (! $url) {
            $this->call('help', ['command_name' => 'httpstatus']);

            return;
        }

        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'allow_redirects' => false,
            'verify' => true,
        ]);

        try {
            $this->followRedirects($url);
            $this->displaySummary();
        } catch (GuzzleException $e) {
            $this->handleError($e);
        }
    }

    /**
     * Follows redirects recursively until a non-redirect response or max depth.
     *
     * @param  string  $url  The URL to fetch
     * @param  int  $depth  Current redirect depth
     * @return object The final response
     *
     * @throws GuzzleException
     */
    private function followRedirects(string $url, int $depth = 0): object
    {
        try {
            $response = $this->client->get($url);
            $status = $response->getStatusCode();
            $xRedirectBy = $this->getHeader($response, 'X-Redirect-By');

            $this->hops[] = [
                'depth' => $depth,
                'url' => $url,
                'status' => $status,
                'reason' => $response->getReasonPhrase(),
                'x_redirect_by' => $xRedirectBy,
                'location' => null,
            ];

            if ($this->isRedirect($status) && $depth < self::MAX_REDIRECTS) {
                $location = $this->getHeader($response, 'Location');
                if ($location) {
                    $this->hops[count($this->hops) - 1]['location'] = $location;
                    $nextUrl = $this->resolveRedirectUrl($url, $location);

                    return $this->followRedirects($nextUrl, $depth + 1);
                }
            }

            if ($depth >= self::MAX_REDIRECTS) {
                $this->error('Stopped after '.self::MAX_REDIRECTS.' redirects (possible redirect loop).');
            }

            return $response;
        } catch (GuzzleException $e) {
            $this->hops[] = [
                'depth' => $depth,
                'url' => $url,
                'status' => 'Error',
                'reason' => null,
                'x_redirect_by' => null,
                'location' => null,
                'error' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Displays the HTTP status summary as a table.
     */
    private function displaySummary(): void
    {
        $rows = [];

        foreach ($this->hops as $hop) {
            $rows[] = [
                $this->getStatusBadge($hop['status']),
                $hop['reason'] ?? '-',
                $hop['url'],
                $hop['location'] ?? '-',
                $hop['x_redirect_by'] ?? '-',
            ];
        }

        $this->table(
            ['HTTP Status', 'Friendly Name', 'URL', 'Redirect to', 'X-Redirect-By'],
            $rows
        );
    }

    /**
     * Returns an ANSI-colored status badge for the given HTTP status code.
     *
     * @param  int|string  $status  The HTTP status code
     * @return string The colored status badge
     */
    private function getStatusBadge(int|string $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300 => "<info>{$status}</info>",
            $status >= 300 && $status < 400 => "<comment>{$status}</comment>",
            $status >= 400 && $status < 500 => "<error>{$status}</error>",
            $status >= 500 => "<error>{$status}</error>",
            default => "{$status}",
        };
    }

    /**
     * Checks if the given status code is a redirect (3xx).
     *
     * @param  int  $status  The HTTP status code
     */
    private function isRedirect(int $status): bool
    {
        return $status >= 300 && $status < 400;
    }

    /**
     * Resolves a relative or absolute redirect URL.
     *
     * @param  string  $currentUrl  The current URL being fetched
     * @param  string  $location  The Location header value
     * @return string The fully resolved URL
     */
    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            $parsed = parse_url($currentUrl);

            return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').$location;
        }

        $parsed = parse_url($currentUrl);
        $base = $parsed['scheme'].'://'.$parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':'.$parsed['port'];
        }
        $path = $parsed['path'] ?? '/';
        $dir = dirname($path);
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }

        return rtrim($base, '/').'/'.ltrim($dir.'/'.$location, '/');
    }

    /**
     * Gets a header value from the response.
     *
     * @param  object|null  $response  The HTTP response
     * @param  string  $name  The header name
     */
    private function getHeader(?object $response, string $name): ?string
    {
        if ($response === null) {
            return null;
        }
        $header = $response->getHeaderLine($name);

        return $header ?: null;
    }

    /**
     * Handles HTTP errors by displaying an error message.
     *
     * @param  GuzzleException  $e  The exception
     */
    private function handleError(GuzzleException $e): void
    {
        $this->newLine();
        $this->error('Error: '.$e->getMessage());
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void {}
}
