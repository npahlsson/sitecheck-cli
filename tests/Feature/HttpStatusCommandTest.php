<?php

namespace Tests\Feature;

it('shows help information', function () {
    $this->artisan('httpstatus --help')
        ->assertExitCode(0)
        ->expectsOutputToContain('Check HTTP status code and redirects');
});

it('shows help when no URL provided', function () {
    $this->artisan('httpstatus')
        ->assertExitCode(0)
        ->expectsOutputToContain('httpstatus');
});

it('displays status table for a valid URL', function () {
    $this->artisan('httpstatus https://google.com')
        ->assertExitCode(0)
        ->expectsOutputToContain('HTTP Status');
});

it('handles 404 responses', function () {
    $this->artisan('httpstatus https://example.com/not-exist')
        ->assertExitCode(0)
        ->expectsOutputToContain('404');
});
