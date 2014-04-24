<?php namespace Xethron\MigrationsGenerator;

use Mockery;
use PHPUnit_Framework_TestCase;

class MigrationsGeneratorServiceProviderTest extends PHPUnit_Framework_TestCase {
  
  public function tearDown()
  {
    Mockery::close();
  }

  /**
  * @test
  */
  public function registers_migrations_generator()
  {
    $app_mock = $this->get_app_mock();

    $app_mock
      ->shouldReceive('bind')
      ->atLeast()->once()
      ->with(
        'migration.generate',
        Mockery::any()
      );

    $app_mock
      ->shouldReceive('share')
      ->atLeast()->once()
      ->with(
        Mockery::on(function($callback) {
          $mock = $this->get_app_mock();

          $mock
            ->shouldReceive('make')
            ->atLeast()->once()
            ->with('Way\Generators\Generator')
            ->andReturn(
              $this->get_generator_mock()
            );

          $mock
            ->shouldReceive('make')
            ->atLeast()->once()
            ->with('Way\Generators\Filesystem\Filesystem')
            ->andReturn(
              $this->get_filesystem_mock()
            );

          $mock
            ->shouldReceive('make')
            ->atLeast()->once()
            ->with('Way\Generators\Compilers\TemplateCompiler')
            ->andReturn(
              $this->get_template_compiler_mock()
            );

          $mock
            ->shouldReceive('make')
            ->atLeast()->once()
            ->with('migration.repository')
            ->andReturn(
              $this->get_migration_repository_mock()
            );

          $repository_mock = $this->get_repository_mock();

          $repository_mock
            ->shouldReceive('get')
            ->atLeast()->once();

          $mock
            ->shouldReceive('make')
            ->atLeast()->once()
            ->with('config')
            ->andReturn(
              $repository_mock
            );

          $this->assertInstanceOf(
            'Xethron\MigrationsGenerator\MigrateGenerateCommand',
            $callback($mock)
          );

          return true;
        })
      );

    $service_provider_mock = $this->get_service_provider_mock($app_mock);

    $service_provider_mock
      ->shouldReceive('commands')
      ->atLeast()->once();

    $service_provider_mock->register();
  }

  /**
  * @test
  */
  public function registers_the_package()
  {
    $mock = $this->get_service_provider_mock();

    $mock
      ->shouldReceive('package')
      ->atLeast()->once()
      ->with('xethron/migration-from-table');

    $mock->boot();
  }

  /**
  * @test
  */
  public function provides_nothing()
  {
    $mock = $this->get_service_provider_mock();

    $this->assertEquals(
      [],
      $mock->provides()
    );
  }

  protected function get_app_mock()
  {
    return Mockery::mock('stdClass');
  }

  protected function get_service_provider_mock($app_mock = null)
  {
    if ($app_mock === null) {
      $app_mock = $this->get_app_mock();
    }

    return Mockery::mock('Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider', [
        $app_mock
      ])
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }

  protected function get_generator_mock()
  {
    return Mockery::mock('Way\Generators\Generator')
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }

  protected function get_filesystem_mock()
  {
    return Mockery::mock('Way\Generators\Filesystem\Filesystem')
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }

  protected function get_template_compiler_mock()
  {
    return Mockery::mock('Way\Generators\Compilers\TemplateCompiler')
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }

  protected function get_migration_repository_mock()
  {
    return Mockery::mock('Illuminate\Database\Migrations\MigrationRepositoryInterface')
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }

  protected function get_repository_mock()
  {
    return Mockery::mock('Illuminate\Config\Repository')
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();
  }
}
