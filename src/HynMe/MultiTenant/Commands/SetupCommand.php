<?php namespace HynMe\MultiTenant\Commands;

use DB, Config;
use HynMe\MultiTenant\Contracts\HostnameRepositoryContract;
use HynMe\MultiTenant\Contracts\TenantRepositoryContract;
use HynMe\MultiTenant\Contracts\WebsiteRepositoryContract;
use Illuminate\Console\Command;

class SetupCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'multi-tenant:setup';

    /**
     * @var string
     */
    protected $description = 'Final configuration step for hyn multi tenancy packages';

    /**
     * @var \HynMe\Webserver\Helpers\ServerConfigurationHelper
     */
    protected $helper;
    protected $helperClass = '\HynMe\Webserver\Helpers\ServerConfigurationHelper';

    /**
     * @var HostnameRepositoryContract
     */
    protected $hostname;
    /**
     * @var WebsiteRepositoryContract
     */
    protected $website;
    /**
     * @var TenantRepositoryContract
     */
    protected $tenant;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var int
     */
    protected $step = 1;

    /**
     * @param HostnameRepositoryContract $hostname
     * @param WebsiteRepositoryContract  $website
     * @param TenantRepositoryContract   $tenant
     */
    public function __construct(
        HostnameRepositoryContract $hostname,
        WebsiteRepositoryContract $website,
        TenantRepositoryContract $tenant)
    {
        parent::__construct();

        $this->hostname = $hostname;
        $this->website = $website;
        $this->tenant = $tenant;
    }



    /**
     * Handles the set up
     */
    public function handle()
    {
        if(class_exists($this->helperClass))
            $this->helper = new $this->helperClass;

        $this->configuration = Config::get('webserver');

        $this->comment('In the following steps you will be asked to set up your first tenant website.');

        $name = $this->ask($this->step++ . ': Please name your first tenant, this by default would be your company or your name.');
        $email = $this->ask($this->step++ . ': What is the primary email address for this tenant?');
        $hostname = $this->ask($this->step++ . ': What is the primary hostname you want to use for multi tenancy? Please note this hostname needs to point to the IP address of this server.');

        $webservice = null;

        /*
         * Setup database?
         * @todo
         */


        /*
         * Setup webserver
         */
        if($this->helper)
        {
            $this->comment('In the next steps we will ask you about webserver configuration.');
            $webserver = $this->confirm($this->step++ . ': Do you want to automatically configure the webserver during this setup?');
            if($webserver)
            {
                if($this->helper->currentUser() != 'root')
                    return $this->error('Configuration of the webserver can only be done under the root user, please run this command again prefixed with sudo or under root user.');

                $webservice = $this->choice($this->step++ . ': Which webserver do you want to configure?', array_get($this->configuration, 'webservers'));
                $webserviceConfiguration = array_get($this->configuration, $webservice);

                if($webservice && $this->confirm("Are you sure you want to continue setup with the integration for the webserver {$webservice}?"))
                {
                    $webserviceClass = array_get($webserviceConfiguration, 'class');
                }
            }
            else
                $this->info('We are skipping webserver configuration.');

            /*
             * Create the first tenant configurations
             */

            DB::beginTransaction();

            $tenant = $this->tenant->create(compact('name', 'email'));

            $website = $this->website->create(['tenant_id' => $tenant->id, 'identifier' => str_replace(['.'], '-', $hostname)]);

            $host = $this->hostname->create(['hostname' => $hostname, 'website_id' => $website->id, 'tenant_id' => $tenant->id]);

            DB::commit();

            // hook into the webservice of choice once object creation succeeded
            if(isset($webserviceClass))
                (new $webserviceClass($website))->register();


            if($tenant->exists && $website->exists && $host->exists)
                $this->info("Configuration succesful");
        }
        else
            $this->comment('The hyn-me/webserver package is not installed. Visit http://hyn.me/packages/webserver for more information.');
    }
}