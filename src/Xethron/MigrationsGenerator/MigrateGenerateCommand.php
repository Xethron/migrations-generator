<?php namespace Xethron\MigrationsGenerator;

use Way\Generators\Commands\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Way\Generators\Generator;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Compilers\TemplateCompiler;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;

use Way\Generators\Syntax\DroppedTable;

use Xethron\MigrationsGenerator\Generators\SchemaGenerator;
use Xethron\MigrationsGenerator\Syntax\AddToTable;
use Xethron\MigrationsGenerator\Syntax\AddForeignKeysToTable;
use Xethron\MigrationsGenerator\Syntax\RemoveForeignKeysFromTable;

use Illuminate\Config\Repository as Config;

class MigrateGenerateCommand extends GeneratorCommand {

	/**
	 * The console command name.
	 * @var string
	 */
	protected $name = 'migrate:generate';

	/**
	 * The console command description.
	 * @var string
	 */
	protected $description = 'Generate a migration from an existing table structure.';

	/**
	 * @var \Way\Generators\Filesystem\Filesystem
	 */
	protected $file;

	/**
	 * @var \Way\Generators\Compilers\TemplateCompiler
	 */
	protected $compiler;

	/**
	 * @var \Illuminate\Database\Migrations\MigrationRepositoryInterface  $repository
	 */
	protected $repository;

	/**
	 * @var \Illuminate\Config\Repository  $config
	 */
	protected $config;

	/**
	 * @var \Xethron\MigrationsGenerator\Generators\SchemaGenerator
	 */
	protected $schemaGenerator;

	/**
	 * Array of Fields to create in a new Migration
	 * Namely: Columns, Indexes and Foreign Keys
	 * @var array
	 */
	protected $fields = array();

	/**
	 * List of Migrations that has been done
	 * @var array
	 */
	protected $migrations = array();

	/**
	 * @var bool
	 */
	protected $log = false;

	/**
	 * @var int
	 */
	protected $batch;

	/**
	 * Filename date prefix (Y_m_d_His)
	 * @var string
	 */
	protected $datePrefix;

	/**
	 * @var string
	 */
	protected $migrationName;

	/**
	 * @var string
	 */
	protected $method;

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @param \Way\Generators\Generator  $generator
	 * @param \Way\Generators\Filesystem\Filesystem  $file
	 * @param \Way\Generators\Compilers\TemplateCompiler  $compiler
	 * @param \Illuminate\Database\Migrations\MigrationRepositoryInterface  $repository
	 * @param \Illuminate\Config\Repository  $config
	 */
	public function __construct(
		Generator $generator,
		Filesystem $file,
		TemplateCompiler $compiler,
		MigrationRepositoryInterface $repository,
		Config $config
	)
	{
		$this->file = $file;
		$this->compiler = $compiler;
		$this->repository = $repository;
		$this->config = $config;

		parent::__construct( $generator );
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->info( 'Using connection: '. $this->option( 'connection' ) ."\n" );
		$this->schemaGenerator = new SchemaGenerator(
			$this->option('connection'),
			$this->option('defaultIndexNames'),
			$this->option('defaultFKNames')
		);

		if ( $this->argument( 'tables' ) ) {
			$tables = explode( ',', $this->argument( 'tables' ) );
		} elseif ( $this->option('tables') ) {
			$tables = explode( ',', $this->option( 'tables' ) );
		} else {
			$tables = $this->schemaGenerator->getTables();
		}

		$tables = $this->removeExcludedTables($tables);
		$this->info( 'Generating migrations for: '. implode( ', ', $tables ) );

		if (!$this->option( 'no-interaction' )) {
			$this->log = $this->askYn('Do you want to log these migrations in the migrations table?');
		}

		if ( $this->log ) {
			$this->repository->setSource( $this->option( 'connection' ) );
			if ( ! $this->repository->repositoryExists() ) {
				$options = array('--database' => $this->option( 'connection' ) );
				$this->call('migrate:install', $options);
			}
			$batch = $this->repository->getNextBatchNumber();
			$this->batch = $this->askNumeric( 'Next Batch Number is: '. $batch .'. We recommend using Batch Number 0 so that it becomes the "first" migration', 0 );
		}

		$this->info( "Setting up Tables and Index Migrations" );
		$this->datePrefix = date( 'Y_m_d_His' );
		$this->generate( 'create', $tables );
		$this->info( "\nSetting up Foreign Key Migrations\n" );
		$this->datePrefix = date( 'Y_m_d_His', strtotime( '+1 second' ) );
		$this->generate( 'foreign_keys', $tables );
		$this->info( "\nFinished!\n" );
	}

	/**
	 * Ask for user input: Yes/No
	 * @param  string $question Question to ask
	 * @return boolean          Answer from user
	 */
	protected function askYn( $question ) {
		$answer = $this->ask( $question .' [Y/n] ');
		while ( ! in_array( strtolower( $answer ), [ 'y', 'n', 'yes', 'no' ] ) ) {
			$answer = $this->ask('Please choose ether yes or no. ');
		}
		return in_array( strtolower( $answer ), [ 'y', 'yes' ] );
	}

	/**
	 * Ask user for a Numeric Value, or blank for default
	 * @param  string    $question Question to ask
	 * @param  int|float $default  Default Value (optional)
	 * @return int|float           Answer
	 */
	protected function askNumeric( $question, $default = null ) {
		$ask = 'Your answer needs to be a numeric value';

		if ( ! is_null( $default ) ) {
			$question .= ' [Default: '. $default .'] ';
			$ask .= ' or blank for default';
		}

		$answer = $this->ask( $question );

		while ( ! is_numeric( $answer ) and ! ( $answer == '' and ! is_null( $default ) ) ) {
			$answer = $this->ask( $ask .'. ');
		}
		if ( $answer == '' ) {
			$answer = $default;
		}
		return $answer;
	}

	/**
	 * Generate Migrations
	 *
	 * @param  string $method Create Tables or Foreign Keys ['create', 'foreign_keys']
	 * @param  array  $tables List of tables to create migrations for
	 * @throws MethodNotFoundException
	 * @return void
	 */
	protected function generate( $method, $tables )
	{
		if ( $method == 'create' ) {
			$function = 'getFields';
			$prefix = 'create';
		} elseif ( $method = 'foreign_keys' ) {
			$function = 'getForeignKeyConstraints';
			$prefix = 'add_foreign_keys_to';
			$method = 'table';
		} else {
			throw new MethodNotFoundException( $method );
		}

		foreach ( $tables as $table ) {
			$this->migrationName = $prefix .'_'. $table .'_table';
			$this->method = $method;
			$this->table = $table;
			$this->fields = $this->schemaGenerator->{$function}( $table );
			if ( $this->fields ) {
				parent::fire();
				if ( $this->log ) {
					$file = $this->datePrefix . '_' . $this->migrationName;
					$this->repository->log($file, $this->batch);
				}
			}
		}
	}

	/**
	 * The path where the file will be created
	 *
	 * @return string
	 */
	protected function getFileGenerationPath()
	{
		$path = $this->getPathByOptionOrConfig( 'path', 'migration_target_path' );
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
		if ( $this->method == 'create' ) {
			$up = (new AddToTable($this->file, $this->compiler))->run($this->fields, $this->table, 'create');
			$down = (new DroppedTable)->drop($this->table);
		} else {
			$up = (new AddForeignKeysToTable($this->file, $this->compiler))->run($this->fields,$this->table);
			$down = (new RemoveForeignKeysFromTable($this->file, $this->compiler))->run($this->fields,$this->table);
		}

		return [
			'CLASS' => ucwords(camel_case($this->migrationName)),
			'UP'    => $up,
			'DOWN'  => $down
		];
	}

	/**
	 * Get path to template for generator
	 *
	 * @return string
	 */
	protected function getTemplatePath()
	{
		return $this->getPathByOptionOrConfig( 'templatePath', 'migration_template_path' );
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['tables', InputArgument::OPTIONAL, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
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
			['connection', 'c', InputOption::VALUE_OPTIONAL, 'The database connection to use.', $this->config->get( 'database.default' )],
			['tables', 't', InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
			['ignore', 'i', InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to ignore, separated by a comma: users,posts,comments' ],
			['path', 'p', InputOption::VALUE_OPTIONAL, 'Where should the file be created?'],
			['templatePath', 'tp', InputOption::VALUE_OPTIONAL, 'The location of the template for this generator'],
			['defaultIndexNames', null, InputOption::VALUE_NONE, 'Don\'t use db index names for migrations'],
			['defaultFKNames', null, InputOption::VALUE_NONE, 'Don\'t use db foreign key names for migrations'],
		];
	}

	/**
	 * Remove all the tables to exclude from the array of tables
	 *
	 * @param $tables
	 *
	 * @return array
	 */
	protected function removeExcludedTables($tables)
	{
		$excludes = $this->getExcludedTables();
		$tables = array_diff($tables, $excludes);

		return $tables;
	}

	/**
	 * Get a list of tables to exclude
	 *
	 * @return array
	 */
	protected function getExcludedTables()
	{
		$excludes = ['migrations'];
		$ignore = $this->option('ignore');
		if ( ! empty($ignore)) {
			return array_merge($excludes, explode(',', $ignore));
		}

		return $excludes;
	}

}
