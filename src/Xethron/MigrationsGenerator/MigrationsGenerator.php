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
	];

	/**
	 * @var Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $schema;

	public function __construct( $database )
	{
		$this->schema = DB::connection( $database )->getDoctrineSchemaManager();
	}

	public function getTables()
	{
		return $this->schema->listTableNames();
	}

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
					$this->singleIndexes[ $columnName ] = ['type' => 'primary', 'name' => null];
					if ( $index->getName() != 'PRIMARY' ) {
						$this->singleIndexes[ $columnName ]['name'] = $index->getName();
					}
				} elseif ( $index->isUnique() ) {
					$this->singleIndexes[ $columnName ] = ['type' => 'unique', 'name' => null];
					if ( ! $this->isDefaultIndexName( $index->getName(), $table, $columnName, 'unique' ) ) {
						$this->singleIndexes[ $columnName ]['name'] = $index->getName();
					}
				} elseif ( $index->isSimpleIndex() ) {
					$this->singleIndexes[ $columnName ] = ['type' => 'index', 'name' => null];
					if ( ! $this->isDefaultIndexName( $index->getName(), $table, $columnName, 'index' ) ) {
						$this->singleIndexes[ $columnName ]['name'] = $index->getName();
					}
				}
			} else {
				$indexArray = [ 'columns' => $indexColumns, 'name' => null ];

				if ( $index->isPrimary() ) {
					$indexArray['type'] = 'primary';
					if ( $index->getName() != 'PRIMARY' ) {
						$indexArray['name'] = $index->getName();
					}
				} elseif ( $index->isUnique() ) {
					$indexArray['type'] = 'unique';
					if ( ! $this->isDefaultIndexName( $index->getName(), $table, $indexColumns, 'unique' ) ) {
						$indexArray['name'] = $index->getName();
					}
				} elseif ( $index->isSimpleIndex() ) {
					$indexArray['type'] = 'index';
					if ( ! $this->isDefaultIndexName( $index->getName(), $table, $indexColumns, 'index' ) ) {
						$indexArray['name'] = $index->getName();
					}
				}
				$this->multipleIndexes[] = (object) $indexArray;
			}
		}
	}

	private function getDefaultIndexName( $table, $columns, $type )
	{
		if ( is_array( $columns ) ) {
			$columns = implode( '_', $columns );
		}
		return $table .'_'. $columns .'_'. $type;
	}

	private function isDefaultIndexName( $name, $table, $columns, $type )
	{
		return $name == $this->getDefaultIndexName( $table, $columns, $type );
	}

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

	protected function decorate( $function, $args, $backticks = false, $quotes = '\'' )
	{
		if ( ! is_null( $args ) ) {
			$args = $this->argsToString( $args, $backticks, $quotes );
			return $function . '(' . $args . ')';
		} else {
			return $function;
		}
	}
}
