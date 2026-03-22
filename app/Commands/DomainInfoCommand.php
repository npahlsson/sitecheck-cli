<?php

namespace App\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

/**
 * Retrieves and displays domain information including IP, hosting, registrar, and nameservers.
 */
class DomainInfoCommand extends Command
{
    /** @var string The command signature. */
    protected $signature = 'domaininfo {domain? : The domain to look up} {--expected= : Expected IP address to compare DNS propagation against}';

    /** @var string The command description. */
    protected $description = 'Get domain information: IP, hosting, registrar, and nameservers';

    /** @var array<array{name: string, doh_url: string}> Public DNS servers for propagation check */
    private const PUBLIC_DNS_SERVERS = [
        ['name' => 'Google (8.8.8.8)', 'doh_url' => 'https://dns.google/resolve'],
        ['name' => 'Cloudflare (1.1.1.1)', 'doh_url' => 'https://cloudflare-dns.com/dns-query'],
        ['name' => 'AdGuard (94.140.14.14)', 'doh_url' => 'https://dns.adguard.com/resolve'],
    ];

    /** @var string The cache directory path. */
    private string $cacheDir;

    /** @var string The cache file path for the current domain. */
    private string $cacheFile;

    /** @var array<string, mixed> The loaded cache data. */
    private array $cache = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->cacheDir = storage_path('app/domaininfo');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->output->setDecorated(true);

        $domain = $this->argument('domain');
        if (! $domain) {
            $this->call('help', ['command_name' => 'domaininfo']);

            return;
        }

        $domain = $this->normalizeDomain($domain);

        $this->cacheFile = $this->cacheDir.'/'.md5($domain).'.json';

        $this->ensureCacheDirectory();
        $this->loadCache();

        $this->info("{$domain}");
        $this->line(str_repeat('─', 50));

        if ($this->isCacheValid($domain)) {
            $data = $this->cache[$domain];
            $this->line('<fg=gray>(cache: '.date('H:i', $data['timestamp']).')</>');
        } else {
            $this->line('<fg=gray>(uppdaterar...)</>');
            $data = $this->fetchDomainInfo($domain);
            $this->saveCache($domain, $data);
        }

        $this->newLine();
        $this->displayInfo($domain, $data);

        $expectedIp = $this->option('expected');
        if ($expectedIp) {
            $this->newLine();
            $this->displayDnsPropagation($domain, $expectedIp);
        }
    }

    /**
     * Normalizes a domain string by removing protocol and path.
     *
     * @param  string  $domain  The raw domain input
     * @return string The normalized domain
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (str_starts_with($domain, 'http://')) {
            $domain = substr($domain, 7);
        } elseif (str_starts_with($domain, 'https://')) {
            $domain = substr($domain, 8);
        }

        $domain = rtrim($domain, '/');
        $domain = preg_replace('/\/.*$/', '', $domain);

        return $domain;
    }

    /**
     * Ensures the cache directory exists.
     */
    private function ensureCacheDirectory(): void
    {
        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Loads the cache file into memory.
     */
    private function loadCache(): void
    {
        if (file_exists($this->cacheFile)) {
            $this->cache = json_decode((string) file_get_contents($this->cacheFile), true) ?: [];
        } else {
            $this->cache = [];
        }
    }

    /**
     * Checks if the cache for a domain is still valid (less than 5 minutes old).
     *
     * @param  string  $domain  The domain to check
     */
    private function isCacheValid(string $domain): bool
    {
        if (! isset($this->cache[$domain])) {
            return false;
        }

        $cached = $this->cache[$domain];

        return isset($cached['timestamp']) && (time() - $cached['timestamp']) < 300;
    }

    /**
     * Fetches all domain information from various sources.
     *
     * @param  string  $domain  The domain to look up
     * @return array<string, mixed>
     */
    private function fetchDomainInfo(string $domain): array
    {
        $ip = $this->getPrimaryIP($domain);
        $reverseDns = $ip ? $this->getReverseDns($ip) : null;
        $hosting = $this->getHosting($ip);
        $whois = $this->getWhoisInfo($domain);
        $nameservers = $this->getNameservers($domain);

        return [
            'ip' => $ip,
            'reverse_dns' => $reverseDns,
            'hosting' => $hosting,
            'registrar' => $whois['registrar'] ?? null,
            'registrant' => $whois['registrant'] ?? null,
            'nameservers' => $nameservers,
            'timestamp' => time(),
        ];
    }

    /**
     * Gets the reverse DNS hostname for an IP address.
     *
     * @param  string  $ip  The IP address
     */
    private function getReverseDns(string $ip): ?string
    {
        $hostname = @gethostbyaddr($ip);

        return $hostname !== $ip ? $hostname : null;
    }

    /**
     * Gets the primary A record IP for a domain.
     *
     * @param  string  $domain  The domain name
     */
    private function getPrimaryIP(string $domain): ?string
    {
        $records = @dns_get_record($domain, DNS_A);

        if (! empty($records)) {
            return $records[0]['ip'] ?? null;
        }

        $ip = @gethostbyname($domain);

        return $ip !== $domain ? $ip : null;
    }

    /**
     * Gets hosting/provider information for an IP address.
     *
     * @param  string|null  $ip  The IP address
     * @return array<string, string|null>|null
     */
    private function getHosting(?string $ip): ?array
    {
        if (! $ip) {
            return null;
        }

        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get("http://ip-api.com/json/{$ip}");
            $data = json_decode((string) $response->getBody(), true);

            if (($data['status'] ?? '') === 'success') {
                return [
                    'provider' => $data['org'] ?? $data['isp'] ?? null,
                    'country' => $data['country'] ?? null,
                    'city' => $data['city'] ?? null,
                    'region' => $data['regionName'] ?? null,
                ];
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * Gets WHOIS information for a domain.
     *
     * @param  string  $domain  The domain name
     * @return array<string, string|null>
     */
    private function getWhoisInfo(string $domain): array
    {
        try {
            $whois = trim((string) shell_exec("whois {$domain} 2>/dev/null"));

            if (empty($whois)) {
                return [];
            }

            $registrar = null;
            $expiry = null;

            if (preg_match('/registrar:\s*(.+)/i', $whois, $m)) {
                $registrar = trim($m[1]);
            } elseif (preg_match('/Registrar:\s*(.+)/i', $whois, $m)) {
                $registrar = trim($m[1]);
            }

            if (preg_match('/expires:\s*(.+)/i', $whois, $m)) {
                $expiry = trim($m[1]);
            } elseif (preg_match('/Exp\.?\s*(.+)/i', $whois, $m)) {
                $expiry = trim($m[1]);
            } elseif (preg_match('/Expiration Date:\s*(.+)/i', $whois, $m)) {
                $expiry = trim($m[1]);
            }

            return [
                'registrar' => $registrar,
                'expiry' => $expiry,
            ];
        } catch (\Exception $e) {
        }

        return [];
    }

    /**
     * Gets the nameservers for a domain.
     *
     * @param  string  $domain  The domain name
     * @return array<array{hostname: string, ip: string}>
     */
    private function getNameservers(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_NS);

        if (empty($records)) {
            return [];
        }

        $nameservers = [];
        foreach ($records as $record) {
            $hostname = $record['target'] ?? null;
            if ($hostname) {
                $ips = @dns_get_record($hostname, DNS_A);
                $ip = $ips[0]['ip'] ?? 'N/A';
                $nameservers[] = [
                    'hostname' => $hostname,
                    'ip' => $ip,
                ];
            }
        }

        return $nameservers;
    }

    /**
     * Saves domain data to the cache file.
     *
     * @param  string  $domain  The domain
     * @param  array<string, mixed>  $data  The data to cache
     */
    private function saveCache(string $domain, array $data): void
    {
        $this->cache[$domain] = $data;

        file_put_contents(
            $this->cacheFile,
            json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Displays the domain information as a table.
     *
     * @param  string  $domain  The domain name
     * @param  array<string, mixed>  $data  The domain data
     */
    private function displayInfo(string $domain, array $data): void
    {
        $nsList = [];
        if (! empty($data['nameservers'])) {
            foreach ($data['nameservers'] as $ns) {
                $nsList[] = "{$ns['hostname']} ({$ns['ip']})";
            }
        }

        $ip = $data['ip'] ?? '-';
        if (! empty($data['reverse_dns'])) {
            $ip .= " ({$data['reverse_dns']})";
        }

        $this->table(
            ['IP', 'Hosting', 'Registrar', 'Namnservrar'],
            [[
                $ip,
                $this->formatHosting($data['hosting'] ?? null),
                $data['registrar'] ?? '-',
                implode("\n", $nsList) ?: '-',
            ]]
        );
    }

    /**
     * Displays DNS propagation results from public DNS servers.
     *
     * @param  string  $domain  The domain to check
     * @param  string  $expectedIp  The expected IP address
     */
    private function displayDnsPropagation(string $domain, string $expectedIp): void
    {
        $this->info('DNS Propagation');
        $this->line(str_repeat('─', 50));

        $rows = [];
        foreach (self::PUBLIC_DNS_SERVERS as $dns) {
            $result = $this->queryDnsOverHttps($dns['doh_url'], $domain);

            if ($result === null) {
                $status = '<fg=red>ERROR</>';
                $match = '<fg=red>✗</>';
            } elseif ($result === $expectedIp) {
                $status = "<fg=green>{$result}</>";
                $match = '<fg=green>✓</>';
            } else {
                $status = "<fg=yellow>{$result}</>";
                $match = '<fg=yellow>✗</>';
            }

            $rows[] = [
                $dns['name'],
                $status,
                $match,
            ];
        }

        $this->table(['DNS Server', 'A Record', 'Match'], $rows);
    }

    /**
     * Queries a DNS-over-HTTPS server for A record.
     *
     * @param  string  $dohUrl  The DoH endpoint URL
     * @param  string  $domain  The domain to look up
     */
    private function queryDnsOverHttps(string $dohUrl, string $domain): ?string
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get($dohUrl, [
                'headers' => ['Accept' => 'application/dns-json'],
                'query' => ['name' => $domain, 'type' => 'A'],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (isset($data['Answer']) && is_array($data['Answer'])) {
                foreach ($data['Answer'] as $answer) {
                    if (($answer['type'] ?? 0) === 1) {
                        return (string) $answer['data'];
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formats hosting information as a human-readable string.
     *
     * @param  array<string, string|null>|null  $hosting  The hosting data
     */
    private function formatHosting(?array $hosting): string
    {
        if (! $hosting) {
            return 'Kunde inte hämta';
        }

        $parts = [];

        if (! empty($hosting['provider'])) {
            $provider = preg_replace('/^(AS\d+\s+)/', '', $hosting['provider']);
            $parts[] = $provider;
        }

        if (! empty($hosting['city']) && ! empty($hosting['country'])) {
            $parts[] = "{$hosting['city']}, {$hosting['country']}";
        } elseif (! empty($hosting['country'])) {
            $parts[] = $hosting['country'];
        }

        return implode(' · ', $parts) ?: 'Kunde inte hämta';
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void {}
}
