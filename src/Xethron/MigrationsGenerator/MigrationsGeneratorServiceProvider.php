<?php namespace Xethron\MigrationsGenerator;

use Illuminate\Support\ServiceProvider;

class MigrationsGeneratorServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['migration.generate'] = $this->app->share( function( $app )
		{
			return $this->app->make( 'Xethron\MigrationsGenerator\MigrateGenerateCommand' );
		});

		$this->commands( 'migration.generate' );
	}

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package( 'xethron/migration-from-table' );
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
