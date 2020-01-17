<?php

namespace Anomaly\Streams\Platform\Installer\Console;

use Anomaly\Streams\Platform\Entry\EntryLoader;
use Anomaly\Streams\Platform\Installer\Console\Command\ConfigureDatabase;
use Anomaly\Streams\Platform\Installer\Console\Command\ConfirmLicense;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadApplicationInstallers;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadBaseMigrations;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadBaseSeeders;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadExtensionInstallers;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadExtensionSeeders;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadModuleInstallers;
use Anomaly\Streams\Platform\Installer\Console\Command\LoadModuleSeeders;
use Anomaly\Streams\Platform\Installer\Console\Command\RunInstallers;
use Anomaly\Streams\Platform\Installer\Console\Command\SetAdminData;
use Anomaly\Streams\Platform\Installer\Console\Command\SetApplicationData;
use Anomaly\Streams\Platform\Installer\Console\Command\SetDatabaseData;
use Anomaly\Streams\Platform\Installer\Console\Command\SetDatabasePrefix;
use Anomaly\Streams\Platform\Installer\Console\Command\SetOtherData;
use Anomaly\Streams\Platform\Installer\Console\Command\SetStreamsData;
use Anomaly\Streams\Platform\Installer\Installer;
use Anomaly\Streams\Platform\Installer\InstallerCollection;
use Anomaly\Streams\Platform\Support\Collection;
use Anomaly\Streams\Platform\Support\Env;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Install
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class Install extends Command
{
    use DispatchesJobs;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Streams Platform.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data = new Collection();

        if (!$this->option('ready')) {
            $this->dispatchNow(new ConfirmLicense($this));
            $this->dispatchNow(new SetStreamsData($data));
            $this->dispatchNow(new SetDatabaseData($data, $this));
            $this->dispatchNow(new SetApplicationData($data, $this));
            $this->dispatchNow(new SetAdminData($data, $this));
            $this->dispatchNow(new SetOtherData($data, $this));

            Env::save($data->all());
        }

        Env::load();

        $this->dispatchNow(new ConfigureDatabase());
        $this->dispatchNow(new SetDatabasePrefix());

        $installers = new InstallerCollection();

        $this->dispatchNow(new LoadApplicationInstallers($installers));
        $this->dispatchNow(new LoadModuleInstallers($installers));
        $this->dispatchNow(new LoadExtensionInstallers($installers));

        $installers->push(
            new Installer(
                'streams::installer.reloading_application',
                function () {
                    $this->call('env:set', ['line' => 'INSTALLED=true']);

                    Env::load();
                    EntryLoader::load();
                }
            )
        );

        $this->dispatchNow(new LoadModuleSeeders($installers));
        $this->dispatchNow(new LoadExtensionSeeders($installers));

        $this->dispatchNow(new LoadBaseMigrations($installers));
        $this->dispatchNow(new LoadBaseSeeders($installers));

        $this->dispatchNow(new RunInstallers($installers, $this));
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['ready', null, InputOption::VALUE_NONE, 'Indicates that the installer should use an existing .env file.'],
        ];
    }
}
