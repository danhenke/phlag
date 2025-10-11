<?php

namespace Phlag\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Hello extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hello';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Say hello';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Hello from Phlag!');

        return self::SUCCESS;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
