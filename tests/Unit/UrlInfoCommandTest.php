<?php

namespace Tests\Unit;

use App\Commands\UrlInfoCommand;
use PHPUnit\Framework\TestCase;

class UrlInfoCommandTest extends TestCase
{
    public function test_normalize_url_adds_https_prefix(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'example.com');

        $this->assertEquals('https://example.com', $result);
    }

    public function test_normalize_url_preserves_existing_protocol(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'https://example.com');

        $this->assertEquals('https://example.com', $result);
    }

    public function test_normalize_url_handles_http_protocol(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'http://example.com');

        $this->assertEquals('http://example.com', $result);
    }

    public function test_extract_domain_from_url(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('extractDomain');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'https://www.example.com/path?query=1');

        $this->assertEquals('www.example.com', $result);
    }

    public function test_extract_domain_returns_null_for_invalid_url(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('extractDomain');
        $method->setAccessible(true);

        $result = $method->invoke($command, '');

        $this->assertNull($result);
    }

    public function test_robots_path_matches_exact_path(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('robotsPathMatches');
        $method->setAccessible(true);

        $result = $method->invoke($command, '/admin', '/admin');

        $this->assertTrue($result);
    }

    public function test_robots_path_matches_with_wildcard(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('robotsPathMatches');
        $method->setAccessible(true);

        $result = $method->invoke($command, '/admin/*', '/admin/users');

        $this->assertTrue($result);
    }

    public function test_format_status_returns_green_for_success(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatStatus');
        $method->setAccessible(true);

        $result = $method->invoke($command, 200);

        $this->assertStringContainsString('green', $result);
    }

    public function test_format_status_returns_yellow_for_redirect(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatStatus');
        $method->setAccessible(true);

        $result = $method->invoke($command, 301);

        $this->assertStringContainsString('yellow', $result);
    }

    public function test_format_status_returns_red_for_client_error(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatStatus');
        $method->setAccessible(true);

        $result = $method->invoke($command, 404);

        $this->assertStringContainsString('red', $result);
    }

    public function test_format_ttfb_returns_green_for_fast_response(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatTtfb');
        $method->setAccessible(true);

        $result = $method->invoke($command, 100);

        $this->assertStringContainsString('green', $result);
        $this->assertStringContainsString('100 ms', $result);
    }

    public function test_format_ttfb_returns_yellow_for_medium_response(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatTtfb');
        $method->setAccessible(true);

        $result = $method->invoke($command, 600);

        $this->assertStringContainsString('yellow', $result);
    }

    public function test_format_ttfb_returns_red_for_slow_response(): void
    {
        $command = new UrlInfoCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatTtfb');
        $method->setAccessible(true);

        $result = $method->invoke($command, 1500);

        $this->assertStringContainsString('red', $result);
    }
}
