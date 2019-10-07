<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Console\SAM;

use Illuminate\Console\Command;
use STS\Bref\Bridge\Events\SamConfigurationRequested;

class ConfigSam extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'bref:config-sam';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure the SAM Template.';

    public function handle(): int
    {
        event(new SamConfigurationRequested);

        return 0;
    }
}
