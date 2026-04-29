<?php

declare(strict_types=1);

namespace flight\commands;

use flight\database\PdoWrapper;

class SampleDatabaseCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('init:sample-db', 'Creates a sample SQLite database and users table.', $config);
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute()
    {
        $io = $this->app()->io();

        $PdoWrapper = new PdoWrapper('sqlite:'. __DIR__ . '/../database.sqlite');

        $io->info('Creating users table...');
        $PdoWrapper->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
        $io->ok('Tables created!', true);
    }
}
