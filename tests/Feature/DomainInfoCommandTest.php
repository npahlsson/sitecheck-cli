<?php

namespace Tests\Feature;

it('shows help information', function () {
    $this->artisan('domaininfo --help')
        ->assertExitCode(0)
        ->expectsOutputToContain('Get domain information');
});

it('shows help when no domain provided', function () {
    $this->artisan('domaininfo')
        ->assertExitCode(0)
        ->expectsOutputToContain('domaininfo');
});

it('displays domain information table', function () {
    $this->artisan('domaininfo google.com')
        ->assertExitCode(0)
        ->expectsOutputToContain('google.com');
});

it('handles invalid domains gracefully', function () {
    $this->artisan('domaininfo nonexistent.invalid')
        ->assertExitCode(0);
});

it('displays DNS propagation when expected IP is provided', function () {
    $this->artisan('domaininfo google.com --expected 1.2.3.4')
        ->assertExitCode(0)
        ->expectsOutputToContain('DNS Propagation');
});
