<?php namespace Xethron\MigrationsGenerator\Generators;

use Illuminate\Support\Facades\DB;

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
	 * @var bool
	 */
	private $ignoreIndexNames;
	/**
	 * @var bool
	 */
	private $ignoreForeignKeyNames;

	/**
	 * @param string $database
     * @param bool   $unusedTimestamps
	 * @param bool   $ignoreIndexNames
	 * @param bool   $ignoreForeignKeyNames
	 */
	public function __construct($database, $unusedTimestamps, $ignoreIndexNames, $ignoreForeignKeyNames)
	{
		$connection = DB::connection($database)->getDoctrineConnection();
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('json', 'text');
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('jsonb', 'text');
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('bit', 'boolean');

		$this->database = $connection->getDatabase();

		$this->schema = $connection->getSchemaManager();
		$this->fieldGenerator = new FieldGenerator($unusedTimestamps);
		$this->foreignKeyGenerator = new ForeignKeyGenerator();

		$this->ignoreIndexNames = $ignoreIndexNames;
		$this->ignoreForeignKeyNames = $ignoreForeignKeyNames;
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
		return $this->fieldGenerator->generate($table, $this->schema, $this->database, $this->ignoreIndexNames);
	}

	public function getForeignKeyConstraints($table)
	{
		return $this->foreignKeyGenerator->generate($table, $this->schema, $this->ignoreForeignKeyNames);
	}

}
