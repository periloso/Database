<?php namespace Stillat\Database\Console\Tenant;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Stillat\Database\Tenant\DatabaseTenantRepository;

class NameCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'tenant:name';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Return the anticipated tenant name';

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$tenantManager = $this->laravel['stillat.database.tenant'];
		$this->info($tenantManager->getTierNameWithPrefix($this->argument('name')));
	}

	protected function getArguments()
	{
		return array(
			array('name', InputArgument::REQUIRED, 'The account ID in question')
		);
	}

}