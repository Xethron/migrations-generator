<?php namespace Xethron\MigrationsGenerator;

use Way\Generators\Commands\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Way\Generators\Generator;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Compilers\TemplateCompiler;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;

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

	/**
	 * @var \Way\Generators\ModelGenerator
	 */
	protected $generator;

	/**
	 * @var Way\Generators\Filesystem\Filesystem
	 */
	protected $file;

	/**
	 * @var Way\Generators\Compilers\TemplateCompiler
	 */
	protected $compiler;

	/**
	 * @var Xethron\MigrationsGenerator\MigrationsGenerator
	 */
	protected $migrationsGenerator;

	/**
	 * Array of Fields to create in a new Migration
	 * Namely: Columns, Indexes and Foreign Keys
	 *
	 * @var array
	 */
	protected $fields = array();

	/**
	 * List of Migrations that has been done
	 *
	 * @var array
	 */
	protected $migrations = array();

	/**
	 * @param \Way\Generators\ModelGenerator  $generator
	 * @param \Way\Generators\Filesystem\Filesystem  $file
	 * @param \Way\Generators\Compilers\TemplateCompiler  $compiler
	 * @param \Illuminate\Database\Migrations\MigrationRepositoryInterface  $repository
	 */
	public function __construct(
		Generator $generator,
		Filesystem $file,
		TemplateCompiler $compiler,
		MigrationRepositoryInterface $repository
	)
	{
		$this->generator = $generator;
		$this->file = $file;
		$this->compiler = $compiler;
		$this->repository = $repository;

		parent::__construct( $generator );
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$this->info( 'Using connection: '. $this->option( 'connection' ) ."\n" );

		$this->migrationsGenerator = new MigrationsGenerator( $this->option( 'connection' ) );

		$this->log = $this->askYn( 'Do you want to log these migrations in the migrations table?' );

		if ( $this->log ) {
			$this->repository->setSource( $this->option( 'connection' ) );

			if ( ! $this->repository->repositoryExists() ) {
				$options = array('--database' => $this->option( 'connection' ) );

				$this->call('migrate:install', $options);
			}

			$batch = $this->repository->getNextBatchNumber();

			$this->batch = $this->askNumeric( 'Next Batch Number is: '. $batch .'. We recommend using Batch Number 0 so that it becomes the "first" migration', 0 );
		}

		if ( $this->argument( 'tables' ) ) {
			$tables = explode( ',', $this->argument( 'tables' ) );
		} elseif ( $this->option('tables') ) {
			$tables = explode( ',', $this->option( 'tables' ) );
		} else {
			$tables = $this->migrationsGenerator->getTables();
		}

		$ignore = [ 'migrations' ] + $this->option( 'ignore' );
		$tables = array_diff( $tables, $ignore );

		$this->info( 'Generating migrations for: '. implode( ', ', $tables ) );

		$this->datePrefix = date( 'Y_m_d_His' );

		$this->generate( 'create', $tables );

		$this->info( "\nSetting up Foreign Keys\n" );

		$this->datePrefix = date( 'Y_m_d_His', strtotime( '+1 second' ) );

		$this->generate( 'foreign_keys', $tables );

		$this->info( "\nFinished!\n" );
	}

	protected function askYn( $question ) {
		$answer = $this->ask( $question .' [Y/n] ');
		while ( ! in_array( strtolower( $answer ), [ 'y', 'n', 'yes', 'no' ] ) ) {
			$answer = $this->ask('Please choose ether yes or no. ');
		}
		return in_array( strtolower( $answer ), [ 'y', 'yes' ] );
	}

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
			$this->migrationData = ['method' => $method, 'table' => $table];
			$this->fields = $this->migrationsGenerator->{$function}( $table );
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
	 * @return mixed
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
		$migrationName = $this->migrationName;

		// This will tell us the table name and action that we'll be performing
		$migrationData = $this->migrationData;

		if ( $this->migrationData['method'] == 'create' ) {
			$up = ( new AddToTable( $this->file, $this->compiler ) )->add( $migrationData, $this->fields );
			$down = ( new DroppedTable )->drop( $migrationData['table'] );
		} else {
			$up = ( new AddForeignKeysToTable( $this->file, $this->compiler ) )->add( $migrationData, $this->fields );
			$down = ( new RemoveForeignKeysFromTable( $this->file, $this->compiler ) )->remove( $migrationData, $this->fields );
		}

		return [
			'CLASS' => ucwords( camel_case( $migrationName ) ),
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
			['connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.', Config::get( 'database.default' )],
			['tables', null, InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
			['ignore', null, InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to ignore, separated by a comma: users,posts,comments', array() ],
			['path', null, InputOption::VALUE_OPTIONAL, 'Where should the file be created?'],
			['templatePath', null, InputOption::VALUE_OPTIONAL, 'The location of the template for this generator'],
		];
	}

}
