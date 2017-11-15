<?php namespace Xethron\MigrationsGenerator;

use File;
use Illuminate\Support\Collection;
use Way\Generators\Commands\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Way\Generators\Generator;
use Way\Generators\Filesystem\Filesystem;
use Way\Generators\Compilers\TemplateCompiler;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;

use Xethron\MigrationsGenerator\Generators\SchemaGenerator;
use Xethron\MigrationsGenerator\Syntax\AddToTable;
use Xethron\MigrationsGenerator\Syntax\DroppedTable;
use Xethron\MigrationsGenerator\Syntax\AddForeignKeysToTable;
use Xethron\MigrationsGenerator\Syntax\RemoveForeignKeysFromTable;

use Illuminate\Contracts\Config\Repository as Config;

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
     * @var string|null
     */
    protected $connection = null;

    /**
     * For create migrations so they all do not have the same date
     * @var int
     */
    protected $secondCount = 1;

    /**
     * Array of ForeignKeys to create in a new Migration
     * Namely: Foreign Keys
     * @var array
     */
    protected $foreignKeys = array();

    /** @var bool // separate or combine the foreign keys to the table create */
    protected $separateForeignKeysCreation = true;

    /** @var bool // empty the existing migrations */
    protected $clearAllExistingMigrations = false;

    /** @var bool // should we check for table dependencies via foreign keys */
    protected $checkForTableDependencies = false;

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
	 * Execute the console command. Added for Laravel 5.5
	 *
	 * @return void
	 */
	public function handle()
	{
		$this->fire();
	}

    /**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->info( 'Using connection: '. $this->option( 'connection' ) ."\n" );
        if ($this->option('connection') !== $this->config->get('database.default')) {
            $this->connection = $this->option('connection');
        }
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
            $this->checkForTableDependencies = $this->askYn('Do you want to load tables by table dependencies?');
            $this->separateForeignKeysCreation = $this->askYn('Do you want to separate the foreign keys creation into their own migrations?');
            $this->clearAllExistingMigrations = $this->askYn('Do you want to clear all existing migrations?');
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

        $this->clearAllMigrations();
        $this->info( $this->separateForeignKeysCreation ? "Setting up Tables and Index Migrations" : "Setting up Tables, Index, and Foreign Key Migrations");
        $this->datePrefix = date( 'Y_m_d_His' );
        $this->generateTablesAndIndices( $tables );

        if ($this->separateForeignKeysCreation) {
            $this->info( "Setting up Foreign Key Migrations" );
            $this->generateForeignKeys( $tables );
        }

        $this->info( "\nFinished!\n" );
	}

    /**
     * Make sure the migration date string is always different and sequential
     * @return false|string
     */
    protected function getDatePrefix()
    {
        $this->secondCount++;
        return date( 'Y_m_d_His', strtotime( "+{$this->secondCount} second" ) );
    }

    /**
     * Check if we should clear all existing migrations, and if so, remove them
     */
    protected function clearAllMigrations()
    {
        if ($this->clearAllExistingMigrations) {
            $directory = base_path() . '/database/migrations/';
            File::cleanDirectory($directory);
        }
    }

	/**
	 * Ask for user input: Yes/No
	 * @param  string $question Question to ask
	 * @return boolean          Answer from user
	 */
	protected function askYn( $question ) {
		$answer = $this->ask( $question .' [Y/n] ');
		while ( ! in_array( strtolower( $answer ), [ 'y', 'n', 'yes', 'no' ] ) ) {
			$answer = $this->ask('Please choose either yes or no. ');
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
	 * Generate tables and index migrations.
	 *
	 * @param  array $tables List of tables to create migrations for
	 * @return void
	 */
	protected function generateTablesAndIndices( array $tables )
	{
        if ($this->checkForTableDependencies) {
            $this->generateTablesAndIndicesLookingForTableDependencies($tables);
        } else {
            $this->method = 'create';

            foreach ( $tables as $table ) {
                $this->table = $table;
                $this->migrationName = 'create_'. $this->table .'_table';
                $this->fields = $this->schemaGenerator->getFields( $this->table );

                $this->generate();
            }
        }
	}

    /**
     * Generate tables and index migrations while checking for table dependencies via foreign keys
     *
     * @param array $tables
     */
    protected function generateTablesAndIndicesLookingForTableDependencies( array $tables )
    {
        $this->method = 'create';

        $tables = collect($tables);
        $tablesWithFields = new Collection();

        foreach ($tables as $table) {
            $this->getDependencyTable($table, $tablesWithFields);
        }

        foreach ($tablesWithFields as $table => $fields) {
            $this->table = $table;
            $this->migrationName = 'create_'. $this->table .'_table';
            $this->fields = $fields;

            if (!$this->separateForeignKeysCreation) {
                $this->foreignKeys = $this->schemaGenerator->getForeignKeyConstraints($this->table);
            }

            $this->generate();
        }
    }

    /**
     * @param $table
     * @param Collection $tableWithFields
     */
    protected function getDependencyTable($table, Collection &$tableWithFields)
    {
        if (!$tableWithFields->has($table)) {
            $fields = $this->schemaGenerator->getFields($table) ?: [];

            foreach ($fields as $field => $info) {
                if (substr($field, -3) === '_id') {
                    $tableName = str_plural(substr($field, 0, -3));
                    $this->getDependencyTable($tableName, $tableWithFields);
                }
            }

            $tableWithFields->put($table, $fields);
        }
    }

	/**
	 * Generate foreign key migrations.
	 *
	 * @param  array $tables List of tables to create migrations for
	 * @return void
	 */
	protected function generateForeignKeys( array $tables )
	{
		$this->method = 'table';

		foreach ( $tables as $table ) {
			$this->table = $table;
			$this->migrationName = 'add_foreign_keys_to_'. $this->table .'_table';
			$this->fields = $this->schemaGenerator->getForeignKeyConstraints( $this->table );

			$this->generate();
		}
	}

	/**
	 * Generate Migration for the current table.
	 *
	 * @return void
	 */
	protected function generate()
	{
		if ( $this->fields ) {
			parent::fire();

			if ( $this->log ) {
				$file = $this->getDatePrefix() . '_' . $this->migrationName;
				$this->repository->log($file, $this->batch);
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
		$migrationName = str_replace('/', '_', $this->migrationName);
		$fileName = $this->getDatePrefix() . '_' . $migrationName . '.php';

		return "{$path}/{$fileName}";
	}

	/**
	 * Fetch the template data
	 *
	 * @return array
	 */
	protected function getTemplateData()
	{
        if (!$this->separateForeignKeysCreation) {
            return $this->combineTableAndForeignKeys();
        }

        return $this->separateTableAndForeignKeys();
	}

    /**
     * Separate the foreign keys from create migrations
     *
     * @return array
     */
    protected function separateTableAndForeignKeys()
    {
        $up = '';
        $down = '';

        if ( $this->method == 'create' ) {
            $up = (new AddToTable($this->file, $this->compiler))->run($this->fields, $this->table, $this->connection, 'create');
            $down = (new DroppedTable)->drop($this->table, $this->connection);
        }

        if ( $this->method == 'table' ) {
            $up = (new AddForeignKeysToTable($this->file, $this->compiler))->run($this->fields, $this->table, $this->connection);
            $down = (new RemoveForeignKeysFromTable($this->file, $this->compiler))->run($this->fields, $this->table, $this->connection);
        }

        return [
            'CLASS' => ucwords(camel_case($this->migrationName)),
            'UP'    => $up,
            'DOWN'  => $down
        ];
    }

    /**
     * Combine the table migrations and foreign key creations
     *
     * @return array
     */
    protected function combineTableAndForeignKeys()
    {
        $up = '';
        $down = '';

        if ( $this->method == 'create' ) {
            $up .= (new AddToTable($this->file, $this->compiler))->run($this->fields, $this->table, $this->connection, 'create');
            $up .= $this->foreignKeys ? PHP_EOL . PHP_EOL . "\t\t" . (new AddForeignKeysToTable($this->file, $this->compiler))->run($this->foreignKeys, $this->table, $this->connection) : '';
            $down .= $this->foreignKeys ? (new RemoveForeignKeysFromTable($this->file, $this->compiler))->run($this->foreignKeys, $this->table, $this->connection) . PHP_EOL . PHP_EOL . "\t\t" : '';
            $down .= (new DroppedTable)->drop($this->table, $this->connection);
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
	 * @param array $tables
	 *
	 * @return array
	 */
	protected function removeExcludedTables( array $tables )
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
