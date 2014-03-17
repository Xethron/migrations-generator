<?php namespace Xethron\MigrationsGenerator;

use Illuminate\Console\Command;
use Way\Generators\Commands\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Way\Generators\Generator;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Compilers\TemplateCompiler;
use Way\Generators\Syntax\DroppedTable;

use Xethron\MigrationsGenerator\Syntax\AddToTable;
use Xethron\MigrationsGenerator\Syntax\AddForeignKeysToTable;
use Xethron\MigrationsGenerator\Syntax\RemoveForeignKeysFromTable;

use Config;
use DB;

class MigrateGenerateCommand extends GeneratorCommand {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'migrate:generate';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate a migration from an existing table structure.';

	protected $fieldTypeMap = [
		'smallint' => 'smallInteger',
		'bigint' => 'bigInteger',
		];

	/**
	 * @param Generator $generator
	 * @param MigrationNameParser $migrationNameParser
	 * @param MigrationFieldsParser $migrationFieldsParser
	 * @param SchemaCreator $schemaCreator
	 */
	public function __construct(
		Generator $generator,
		Filesystem $file,
		TemplateCompiler $compiler
	)
	{
		$this->generator = $generator;
		$this->file = $file;
		$this->compiler = $compiler;

		parent::__construct($generator);
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$this->info("Generating Migrations");

		$tables = explode( ',', $this->argument('tables') );

		$this->datePrefix = date('Y_m_d_His');

		foreach ($tables as $table) {
			$this->migrationName = 'create_'. $table .'_table';
			$this->migrationData = ['method' => 'create', 'table' => $table];
			$this->fields = $this->getFields( $table );
			if ( $this->fields ) {
				parent::fire();
			}
		}

		$this->datePrefix = date('Y_m_d_His', strtotime('+1 second'));

		foreach ($tables as $table) {
			$this->migrationName = 'add_foreign_keys_to_'. $table .'_table';
			$this->migrationData = ['method' => 'table', 'table' => $table];
			$this->fields = $this->getForeignKeyConstraints( $table );
			if ( $this->fields ) {
				parent::fire();
			}
		}
	}

	protected function getFields( $table ) {

		$fields = [];

		$schema = DB::connection( $this->option('connection') )->getDoctrineSchemaManager( $table );

		$columns = $schema->listTableColumns( $table );

		if ( empty( $columns ) ) return false;

		foreach ( $columns as $column ) {
			$name = $column->getName();
			$type =  $column->getType()->getName();
			$length = $column->getLength();
			$default = $column->getDefault();

			if ( isset( $this->fieldTypeMap[ $type ] ) ) {
				$type = $this->fieldTypeMap[ $type ];
			}

			$fields[ $name ] = [
				'field' => $name,
				'type'  => $type,
				];

			// Different rules for different type groups
			if ( $type == 'integer') {
			// Integer
				if ( $column->getUnsigned() && $column->getAutoincrement() ) {
					$type = $fields[ $name ]['type'] = 'increments';
				} else {
					if ( $column->getUnsigned() ) {
						$fields[ $name ]['decorators'][] = 'unsigned';
					}
					if ( $column->getAutoincrement() ) {
						$fields[ $name ]['args'] = 'true';
					}
				}
			} elseif ( in_array( $type, [ 'decimal', 'float', 'double' ] ) ) {
			// Precision based numbers
				if ( $column->getPrecision() != 8 OR $column->getScale() != 2 ) {
					$fields[ $name ]['args'] = $column->getPrecision();
					if ($column->getScale() != 2) {
						$fields[ $name ]['args'] .= ', '. $column->getScale();
					}
				}
			} else {
			// Probably not a number
				if ( $length ) {
					if ( $type != 'string' OR $length != 255 )
						$fields[ $name ]['args'] = $length;
				}
			}

			if ( isset( $default ) ) {
				if ( in_array( $default, [ 'CURRENT_TIMESTAMP' ] ) ) {
					if ( $type == 'datetime' )
						$type = $fields[ $name ]['type'] = 'timestamp';
					$default = $this->decorate('DB::raw', $default);
				} elseif ( in_array( $type, [ 'string', 'text' ] ) || ! is_numeric( $default ) ) {
					$default = $this->argsToString( $default );
				}

				$fields[ $name ]['decorators'][] = $this->decorate('default', $default, false, '');
			}

			if( ! $column->getNotNull() ) {
				$fields[ $name ]['decorators'][] = 'nullable';
			}
		}

		$indexes = $schema->listTableIndexes($table);

		foreach ( $indexes as $index ) {
			$indexColumns = $index->getColumns();
			if ( count( $indexColumns ) == 1 ) {
				$columnName = $indexColumns[0];

				if ( $index->isPrimary() ) {
					if ($fields[ $columnName ]['type'] != 'increments' AND ( ! isset( $fields[ $columnName ]['args'] ) OR $fields[ $columnName ]['args'] != 'true' ) ){
						$fields[ $columnName ]['decorators'][] = 'primary';
					}
				} elseif ( $index->isUnique() ) {
					$fields[ $columnName ]['decorators'][] = $this->decorate('unique', $index->getName(), true);
				} elseif ( $index->isSimpleIndex() ) {
					$fields[ $columnName ]['decorators'][] = $this->decorate('index', $index->getName(), true);
				}
			} else {
				$field = '['. $this->argsToString( $indexColumns ) .']';
				if ( $index->isPrimary() ) {
					$fields[] = [
						'field' => $indexColumns,
						'type'  => 'primary',
					];
				} elseif ( $index->isUnique() ) {
					$fields[] = [
						'field' => $indexColumns,
						'type'  => 'unique',
						'args'  => $this->argsToString( $index->getName(), true ),
					];
				} elseif ( $index->isSimpleIndex() ) {
					$fields[] = [
						'field' => $indexColumns,
						'type'  => 'index',
						'args'  => $this->argsToString( $index->getName(), true ),
					];
				}
			}
		}

		return $fields;
	}

	protected function getForeignKeyConstraints( $table ) {

		$fields = [];

		$schema = DB::connection( $this->option('connection') )->getDoctrineSchemaManager( $table );

		$foreignKeys = $schema->listTableForeignKeys( $table );

		if ( empty( $foreignKeys ) ) return false;

		foreach ( $foreignKeys as $foreignKey ) {
			$fields[] = [
				'field' => $foreignKey->getLocalColumns()[0],
				'references' => $foreignKey->getForeignColumns()[0],
				'on' => $foreignKey->getForeignTableName(),
			];
		}

		return $fields;
	}

	protected function argsToString( $args, $backticks = false, $quotes = '\'' ) {
		$open = $close = $quotes;

		if ( $backticks ) {
			$open = $open .'`';
			$close = '`'. $close;
		}

		if ( is_array( $args ) ) {
			$seperator = $close .', '. $open;
			$args = implode($seperator, $args);
		}

		return $open . $args . $close;
	}

	protected function decorate( $function, $args, $backticks = false, $quotes = '\'' ) {
		$args = $this->argsToString( $args, $backticks, $quotes );
		return $function . '(' . $args . ')';
	}

	/**
	 * The path where the file will be created
	 *
	 * @return mixed
	 */
	protected function getFileGenerationPath()
	{
		$path = $this->getPathByOptionOrConfig('path', 'migration_target_path');
		$fileName = $this->getDatePrefix() . '_' . $this->migrationName . '.php';

		return "{$path}/{$fileName}";
	}

	/**
	 * Get the date prefix for the migration.
	 *
	 * @return string
	 */
	protected function getDatePrefix()
	{
		return $this->datePrefix;
	}

	/**
	 * Fetch the template data
	 *
	 * @return array
	 */
	protected function getTemplateData()
	{
		$migrationName = $this->migrationName;

		// This will tell us the table name and action that we'll be performing
		$migrationData = $this->migrationData;

		// We also need to parse the migration fields, if provided
		$fields = $this->fields;

		if ( $this->migrationData['method'] == 'create' ) {
			$up = (new AddToTable($this->file, $this->compiler))->add($migrationData, $fields);
			$down = (new DroppedTable)->drop($migrationData['table']);
		} else {
			$up = (new AddForeignKeysToTable($this->file, $this->compiler))->add($migrationData, $fields);
			$down = (new RemoveForeignKeysFromTable($this->file, $this->compiler))->remove($migrationData, $fields);;
		}

		return [
			'CLASS' => ucwords(camel_case($migrationName)),
			'UP'    => $up,
			'DOWN'  => $down
		];
	}

	/**
	 * Get path to template for generator
	 *
	 * @return mixed
	 */
	protected function getTemplatePath()
	{
		return $this->getPathByOptionOrConfig('templatePath', 'migration_template_path');
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['tables', InputArgument::REQUIRED, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.', Config::get('database.default')],
			['path', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['templatePath', null, InputOption::VALUE_OPTIONAL, 'The location of the template for this generator'],
		];
	}

}
