<?php namespace Xethron\MigrationsGenerator\Generators;

use DB;

class SchemaGenerator {

	/**
	 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $schema;

	/**
	 * @var FieldGenerator
	 */
	protected $fieldGenerator;

	/**
	 * @var ForeignKeyGenerator
	 */
	protected $foreignKeyGenerator;

	/**
	 * @var string
	 */
	protected $database;

	/**
	 * @param string $database
	 */
	public function __construct( $database )
	{
		$connection = DB::connection( $database )->getDoctrineConnection();
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('bit', 'boolean');

		$this->database = $connection->getDatabase();

		$this->schema = $connection->getSchemaManager();
		$this->fieldGenerator = new FieldGenerator();
		$this->foreignKeyGenerator = new ForeignKeyGenerator();
	}

	/**
	 * @return mixed
	 */
	public function getTables()
	{
		return $this->schema->listTableNames();
	}

	public function getFields($table)
	{
		return $this->fieldGenerator->generate($table, $this->schema, $this->database);
	}

	public function getForeignKeyConstraints($table)
	{
		return $this->foreignKeyGenerator->generate($table, $this->schema);
	}

}
