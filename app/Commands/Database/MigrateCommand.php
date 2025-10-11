<?php

declare(strict_types=1);

namespace Phlag\Commands\Database;

use LaravelZero\Framework\Commands\Command;
use Throwable;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate {--fresh : Drop all tables before running migrations} {--seed : Execute seeds after migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Phlag database migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->runMigrations();

            if ($this->option('seed')) {
                $this->call('app:seed');
            }

            $this->info('Database migrations completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Database migrations failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function runMigrations(): void
    {
        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        $this->call($command, [
            '--force' => true,
        ]);
    }
}
