<?php namespace Xethron\MigrationsGenerator;

use DB;

class MigrationsGenerator {

	/**
	 * Convert dbal types to Laravel Migration Types
	 *
	 * @var array
	 */
	protected $fieldTypeMap = [
		'tinyint'  => 'tinyInteger',
		'smallint' => 'smallInteger',
		'bigint'   => 'bigInteger',
		'datetime' => 'dateTime',
		'blob'     => 'binary',
	];

	/**
	 * @var Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $schema;

	/**
	 * @var array
	 */
	protected $singleIndexes;

	/**
	 * @var array
	 */
	protected $multipleIndexes;

	/**
	 * @param string $database
	 */
	public function __construct( $database )
	{
		$connection = DB::connection( $database )->getDoctrineConnection();
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
		$this->schema = $connection->getSchemaManager();
	}

	/**
	 * @return mixed
	 */
	public function getTables()
	{
		return $this->schema->listTableNames();
	}

	/**
	 * Create array of all the fields for a table
	 * @param string $table Table Name
	 * @return array|bool
	 */
	public function getFields( $table )
	{
		$fields = [];

		$columns = $this->schema->listTableColumns( $table );

		if ( empty( $columns ) ) return false;

		$this->setIndexes( $table );

		foreach ( $columns as $column ) {
			$name = $column->getName();
			$type =  $column->getType()->getName();
			$length = $column->getLength();
			$default = $column->getDefault();
			$nullable = ( ! $column->getNotNull() );

			$decorators = null;
			$args = null;
			$index = null;

			if ( isset( $this->singleIndexes[ $name ] ) ) {
				$index = (object) $this->singleIndexes[ $name ];
			}

			if ( isset( $this->fieldTypeMap[ $type ] ) ) {
				$type = $this->fieldTypeMap[ $type ];
			}

			// Different rules for different type groups
			if ( in_array( $type, [ 'tinyInteger', 'smallInteger', 'integer', 'bigInteger' ] ) ) {
				// Integer
				if ( $type == 'integer' and $column->getUnsigned() and $column->getAutoincrement() ) {
					$type = 'increments';
					$index = null;
				} else {
					if ( $column->getUnsigned() ) {
						$decorators[] = 'unsigned';
					}
					if ( $column->getAutoincrement() ) {
						$args = 'true';
						$index = null;
					}
				}
			} elseif ( in_array( $type, [ 'decimal', 'float', 'double' ] ) ) {
				// Precision based numbers
				if ( $column->getPrecision() != 8 or $column->getScale() != 2 ) {
					$args = $column->getPrecision();
					if ( $column->getScale() != 2 ) {
						$args .= ', '. $column->getScale();
					}
				}
			} elseif ( $type == 'dateTime' ) {
				if ( $name == 'deleted_at' and $nullable ) {
					$nullable = false;
					$type = 'softDeletes';
					$name = '';
				} elseif ( $name == 'created_at' and isset( $fields['updated_at'] ) ) {
					$fields['updated_at'] = [ 'field' => '', 'type' => 'timestamps' ];
					continue;
				} elseif ( $name == 'updated_at' and isset( $fields['created_at'] ) ) {
					$fields['created_at'] = [ 'field' => '', 'type' => 'timestamps' ];
					continue;
				}
			} else {
				// Probably not a number
				if ( $length ) {
					if ( $type != 'string' or $length != 255 )
						$args = $length;
				}
			}

			if ( isset( $default ) ) {
				if ( in_array( $default, [ 'CURRENT_TIMESTAMP' ] ) ) {
					if ( $type == 'dateTime' )
						$type = 'timestamp';
					$default = $this->decorate( 'DB::raw', $default );
				} elseif ( in_array( $type, [ 'string', 'text' ] ) or ! is_numeric( $default ) ) {
					$default = $this->argsToString( $default );
				}

				$decorators[] = $this->decorate( 'default', $default, false, '' );
			}

			if ( $nullable ) {
				$decorators[] = 'nullable';
			}

			$fields[ $name ] = [
				'field' => $name,
				'type'  => $type,
			];
			if ( $index ) $decorators[] = $this->decorate( $index->type, $index->name, true );
			if ( $decorators ) $fields[ $name ]['decorators'] = $decorators;
			if ( $args ) $fields[ $name ]['args'] = $args;
		}

		foreach ( $this->multipleIndexes as $index ) {
			$field = [
				'field' => $index->columns,
				'type'  => $index->type,
			];
			if ( $index->name ) {
				$field['args'] = $this->argsToString( $index->name, true );
			}
			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * @param string $table
	 */
	protected function setIndexes( $table )
	{
		$this->singleIndexes = array();
		$this->multipleIndexes = array();

		$indexes = $this->schema->listTableIndexes( $table );

		foreach ( $indexes as $index ) {
			$indexColumns = $index->getColumns();
			if ( count( $indexColumns ) == 1 ) {
				$columnName = $indexColumns[0];

				if ( $index->isPrimary() ) {
					$this->singleIndexes[ $columnName ] =
						$this->getIndex('primary', $index->getName(), $table, $indexColumns);
				} elseif ( $index->isUnique() ) {
					$this->singleIndexes[ $columnName ] =
						$this->getIndex('unique', $index->getName(), $table, $indexColumns);
				} elseif ( $index->isSimpleIndex() ) {
					$this->singleIndexes[ $columnName ] =
						$this->getIndex('index', $index->getName(), $table, $indexColumns);
				}
			} else {
				if ( $index->isPrimary() ) {
					$indexArray = $this->getIndex('primary', $index->getName(), $table, $indexColumns);
				} elseif ( $index->isUnique() ) {
					$indexArray = $this->getIndex('unique', $index->getName(), $table, $indexColumns);
				} elseif ( $index->isSimpleIndex() ) {
					$indexArray = $this->getIndex('index', $index->getName(), $table, $indexColumns);
				}
				$indexArray['columns'] = $indexColumns;
				$this->multipleIndexes[] = (object) $indexArray;
			}
		}
	}

	/**
	 * @param string $table Table Name
	 * @param string|array $columns Column Names
	 * @param string $type Index Type
	 * @return string
	 */
	private function getDefaultIndexName( $table, $columns, $type )
	{
		if ($type=='primary') {
			return 'PRIMARY';
		}
		if ( is_array( $columns ) ) {
			$columns = implode( '_', $columns );
		}
		return $table .'_'. $columns .'_'. $type;
	}

	/**
	 * @param string       $name    Current Name
	 * @param string       $table   Table Name
	 * @param string|array $columns Column Names
	 * @param string       $type    Index Type
	 * @return bool
	 */
	private function isDefaultIndexName( $name, $table, $columns, $type )
	{
		return $name == $this->getDefaultIndexName( $table, $columns, $type );
	}

	/**
	 * Get array of foreign keys
	 * @param string $table Table Name
	 * @return array|bool
	 */
	public function getForeignKeyConstraints( $table )
	{

		$fields = [];

		$foreignKeys = $this->schema->listTableForeignKeys( $table );

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

	/**
	 * @param string|array $args
	 * @param bool         $backticks
	 * @param string       $quotes
	 * @return string
	 */
	protected function argsToString( $args, $backticks = false, $quotes = '\'' )
	{
		$open = $close = $quotes;

		if ( $backticks ) {
			$open = $open .'`';
			$close = '`'. $close;
		}

		if ( is_array( $args ) ) {
			$seperator = $close .', '. $open;
			$args = implode( $seperator, $args );
		}

		return $open . $args . $close;
	}

	/**
	 * Get Decorator
	 * @param string       $function
	 * @param string|array $args
	 * @param bool         $backticks
	 * @param string       $quotes
	 * @return string
	 */
	protected function decorate( $function, $args, $backticks = false, $quotes = '\'' )
	{
		if ( ! is_null( $args ) ) {
			$args = $this->argsToString( $args, $backticks, $quotes );
			return $function . '(' . $args . ')';
		} else {
			return $function;
		}
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param string $table
	 * @param string|array $indexColumns
	 * @return array
	 */
	protected function getIndex($type, $name, $table, $indexColumns)
	{
		$index = ['type' => $type, 'name' => null];
		if (!$this->isDefaultIndexName($name, $table, $indexColumns, $type)) {
			$index['name'] = $name;
		}
		return $index;
	}
}
