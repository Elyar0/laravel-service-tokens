<?php

namespace Elyar\ServiceTokens\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'st:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Service Tokens resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Publishing Service Tokens Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'service-tokens-config']);

        $this->info('Service Tokens scaffolding installed successfully.');
    }
}
