<?php namespace Stillat\Database\Tenant\Migrations;

use Illuminate\Filesystem\Filesystem;
use Stillat\Database\Tenant\TenantManager;
use Illuminate\Database\Migrations\Migrator;
use Stillat\Database\Tenant\Migrations\TenantMigrationResolver;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;

class TenantMigrator extends Migrator {

	/**
	 * The TenantManager instance.
	 *
	 * @var \Stillat\Database\Tenant\TenantManager
	 */
	protected $manager;

	/**
	 * The tenant migration resolver instance.
	 *
	 * @var \Stillat\Database\Tenant\Migrations\TenantMigrationResolver
	 */
	protected $tenantMigrationResolver;

	protected $usePath = false;

	protected $path = '';

	/**
	 * The currently available tenants.
	 * 
	 * @var array
	 */
	protected $tenants = null;

	/**
	 * Returns a new TenantMigrator instance.
	 * 
	 * @param MigrationRepositoryInterface $repository
	 * @param Resolver                     $resolver
	 * @param Filesystem                   $files
	 * @param TenantManager                $manager
	 */
	public function __construct(MigrationRepositoryInterface $repository,
		Resolver $resolver,
		Filesystem $files,
		TenantManager $manager)
	{
		$this->manager = $manager;

		// There is nothing special about the TenantMigrationResolver class, so let's just new up one.
		$this->tenantMigrationResolver = new TenantMigrationResolver;

		parent::__construct($repository, $resolver, $files);
	}


	public function includeMigrations($path)
	{
		$migrationsFileList = array();

		$configuredMigrations = $this->manager->getTenantMigrations();

		$availableMigrations = $this->getMigrationFiles($path);

		foreach ($availableMigrations as $migration)
		{
			$migrationName = $this->tenantMigrationResolver->resolveMigrationName($migration);

			if ($this->manager->getMigrationBehavior() == 'only')
			{
				if (in_array($migrationName, $configuredMigrations))
				{
					$migrationsFileList[] = $migration;
				}
			}
			else
			{
				if (in_array($migrationName, $configuredMigrations) == false)
				{
					$migrationsFileList[] = $migration;
				}
			}
		}

		$this->requireFiles($path, $migrationsFileList);

		return $migrationsFileList;
	}

	public function usePath($path)
	{
		$this->usePath = true;
		$this->path = $path;
	}

	/**
	 * Retrieves a list of all available tenants.
	 * 
	 * @return array
	 */
	protected function getTenants()
	{
		if ($this->tenants == null)
		{
			$this->tenants = $this->manager->getRepository()->getTenants();
		}

		return $this->tenants;
	}

	/**
	 * Run the outstanding migrations at a given path.
	 *
	 * @param  string  $path
	 * @param  bool    $pretend
	 * @return void
	 */
	public function run($path, $pretend = false)
	{
		$tenants = $this->getTenants();

		$migrationsFileList = $this->includeMigrations($path);


		$this->note('Assembling tenant migrations list...');
		$this->note('');

		foreach ($migrationsFileList as $migration)
		{
			$this->note('<info>Queued:</info> '.$migration);
		}


		$this->note('');

		// Go through each tenant and then create and then bootstrap
		// their connections.
		foreach ($tenants as $tenant)
		{
			$this->manager->bootstrapConnectionByTenantName($tenant->tenant_name);
			$this->setConnection($tenant->tenant_name);
			$this->repository->setSource($tenant->tenant_name);
			$this->note('<info>Bootstrapped connection for:</info> '.$tenant->tenant_name);

			// Once we grab all of the migration files for the path, we will compare them
			// against the migrations that have already been run for this package then
			// run all of the oustanding migrations against the database connection.
			$ran = $this->repository->getRan();

			$migrations = array_diff($migrationsFileList, $ran);


			$this->note('<info>Running migrations on:</info> '.$tenant->tenant_name);
			$this->runMigrationList($migrations, $pretend);

		}
	}

	/**
	 * Rollback the last migration operation.
	 *
	 * @param  bool  $pretend
	 * @return int
	 */
	public function rollback($pretend = false)
	{
		$tenants = $this->getTenants();
		$migrationsFileList = array();

		if ($this->usePath)
		{
			$this->note('<info>Rollback command initiated with path "'.$this->path.'"</info>');
			$migrationsFileList = $this->includeMigrations($this->path);
		}

		$everyMigration = 0;

		foreach($tenants as $tenant)
		{
			$this->manager->bootstrapConnectionByTenantName($tenant->tenant_name);
			$this->note('<info>Bootstrapped connection for:</info> '.$tenant->tenant_name);

			$this->setConnection($tenant->tenant_name);
			$this->repository->setSource($tenant->tenant_name);

			$migrations = $this->repository->getLast();

			if (count($migrations) == 0)
			{
				// Move on to the next tenant.
				$this->note('<info>Nothing to rollback on "'.$tenant->tenant_name.'".</info>');
				continue;
			}

			foreach($migrations as $migration)
			{
				$this->note('<info>Rolling back "'.$migration->migration.'".</info>');
				$this->runDown((object) $migration, $pretend);
			}

			$everyMigration += count($migrations);

		}

		return $everyMigration;
	}

}