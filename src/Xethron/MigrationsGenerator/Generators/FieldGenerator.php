<?php namespace Xethron\MigrationsGenerator\Generators;

class FieldGenerator {

	/**
	 * Convert dbal types to Laravel Migration Types
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
	 * Create array of all the fields for a table
	 * @param string $table Table Name
	 * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @return array|bool
	 */
	public function generate( $table, $schema )
	{
		$fields = [];

		$columns = $schema->listTableColumns( $table );

		if ( empty( $columns ) ) return false;

		$indexGenerator = new IndexGenerator( $table, $schema );

		foreach ( $columns as $column ) {
			$name = $column->getName();
			$type =  $column->getType()->getName();
			$length = $column->getLength();
			$default = $column->getDefault();
			$nullable = ( ! $column->getNotNull() );
			$index = $indexGenerator->getIndex($name);

			$decorators = null;
			$args = null;

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

			if ( $nullable ) $decorators[] = 'nullable';

			$fields[ $name ] = [
				'field' => $name,
				'type'  => $type,
			];
			if ( $index ) $decorators[] = $this->decorate( $index->type, $index->name, true );
			if ( $decorators ) $fields[ $name ]['decorators'] = $decorators;
			if ( $args ) $fields[ $name ]['args'] = $args;
		}

		foreach ( $indexGenerator->getMultiFieldIndexes() as $index ) {
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
} 
