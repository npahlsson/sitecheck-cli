<?php

namespace Tests\Unit;

use App\Commands\HttpStatusCommand;
use PHPUnit\Framework\TestCase;

class HttpStatusCommandTest extends TestCase
{
    public function test_is_redirect_returns_true_for_301(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('isRedirect');
        $method->setAccessible(true);

        $result = $method->invoke($command, 301);

        $this->assertTrue($result);
    }

    public function test_is_redirect_returns_true_for_302(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('isRedirect');
        $method->setAccessible(true);

        $result = $method->invoke($command, 302);

        $this->assertTrue($result);
    }

    public function test_is_redirect_returns_false_for_200(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('isRedirect');
        $method->setAccessible(true);

        $result = $method->invoke($command, 200);

        $this->assertFalse($result);
    }

    public function test_is_redirect_returns_false_for_404(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('isRedirect');
        $method->setAccessible(true);

        $result = $method->invoke($command, 404);

        $this->assertFalse($result);
    }

    public function test_resolve_redirect_url_preserves_absolute_url(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('resolveRedirectUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'https://example.com/page', 'https://other.com/new');

        $this->assertEquals('https://other.com/new', $result);
    }

    public function test_resolve_redirect_url_handles_root_relative_path(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('resolveRedirectUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'https://example.com/old', '/new');

        $this->assertEquals('https://example.com/new', $result);
    }

    public function test_resolve_redirect_url_handles_relative_path(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('resolveRedirectUrl');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'https://example.com/dir/page', 'other');

        $this->assertEquals('https://example.com/dir/other', $result);
    }

    public function test_get_header_returns_null_for_null_response(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getHeader');
        $method->setAccessible(true);

        $result = $method->invoke($command, null, 'Content-Type');

        $this->assertNull($result);
    }

    public function test_get_status_badge_returns_info_for_success(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($command, 200);

        $this->assertStringContainsString('info', $result);
        $this->assertStringContainsString('200', $result);
    }

    public function test_get_status_badge_returns_comment_for_redirect(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($command, 301);

        $this->assertStringContainsString('comment', $result);
    }

    public function test_get_status_badge_returns_error_for_client_error(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($command, 404);

        $this->assertStringContainsString('error', $result);
    }

    public function test_get_status_badge_returns_error_for_server_error(): void
    {
        $command = new HttpStatusCommand;
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($command, 500);

        $this->assertStringContainsString('error', $result);
    }
}
