<?php

declare(strict_types=1);

namespace Phlag\Commands\Database;

use Database\Seeders\DatabaseSeeder;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class SeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed {--fresh : Recreate the schema before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo data for the Phlag schema';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if ($this->option('fresh')) {
                $this->call('migrate:fresh', [
                    '--force' => true,
                ]);
            }

            $this->call('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => true,
            ]);

            $this->info('Database seeding completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Database seeding failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
