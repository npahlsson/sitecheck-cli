<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class InspireCommand extends Command
{
    protected $signature = 'inspire';

    protected $description = 'Display an inspiring quote';

    public function handle(): void
    {
        $quotes = [
            'Simplicity is the ultimate sophistication.',
            'The best way to predict the future is to create it.',
            'Code is like humor. When you have to explain it, it\'s bad.',
            'First, solve the problem. Then, write the code.',
            'Experience is the name everyone gives to their mistakes.',
        ];

        $quote = $quotes[array_rand($quotes)];

        $this->info($quote);
    }

    public function schedule(Schedule $schedule): void {}
}
