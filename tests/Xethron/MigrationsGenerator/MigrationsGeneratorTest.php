<?php namespace Xethron\MigrationsGenerator;

use Mockery;
use PHPUnit\Framework\TestCase;

class MigrationsGeneratorTest extends TestCase {

  public function tearDown()
  {
    Mockery::close();
  }

  /**
  * @test
  */
  public function registers_migrations_generator()
  {
    $this->markTestSkipped('No tests implemented yet.');
  }
}
