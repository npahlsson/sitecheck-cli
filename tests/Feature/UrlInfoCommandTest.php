<?php

namespace Tests\Feature;

it('shows help information', function () {
    $this->artisan('urlinfo --help')
        ->assertExitCode(0)
        ->expectsOutputToContain('Show URL status, robots allowance');
});

it('shows help when no URL provided', function () {
    $this->artisan('urlinfo')
        ->assertExitCode(0)
        ->expectsOutputToContain('urlinfo');
});

it('displays URL information table', function () {
    $this->artisan('urlinfo https://google.com')
        ->assertExitCode(0)
        ->expectsOutputToContain('HTTP Status');
});

it('handles 404 responses', function () {
    $this->artisan('urlinfo https://example.com/not-exist')
        ->assertExitCode(0)
        ->expectsOutputToContain('404');
});

it('accepts staging option', function () {
    $this->artisan('urlinfo https://google.com --staging')
        ->assertExitCode(0)
        ->expectsOutputToContain('HTTP Status');
});

it('normalizes URL without protocol', function () {
    $this->artisan('urlinfo google.com')
        ->assertExitCode(0)
        ->expectsOutputToContain('HTTP Status');
});
