<?php namespace Xethron\MigrationsGenerator\Generators;

use DB;

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
	 * @var string
	 */
	protected $database;

	/**
	 * Create array of all the fields for a table
	 *
	 * @param string                                      $table Table Name
	 * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @param string                                      $database
	 * @param bool                                        $ignoreIndexNames
	 *
	 * @return array|bool
	 */
	public function generate($table, $schema, $database, $ignoreIndexNames)
	{
		$this->database = $database;
		$columns = $schema->listTableColumns( $table );
		if ( empty( $columns ) ) return false;

		$indexGenerator = new IndexGenerator($table, $schema, $ignoreIndexNames);
		$fields = $this->setEnum($this->getFields($columns, $indexGenerator), $table);
		$indexes = $this->getMultiFieldIndexes($indexGenerator);
		return array_merge($fields, $indexes);
	}

	/**
	 * Return all enum columns for a given table
	 * @param string $table
	 * @return array
	 */
	protected function getEnum($table)
	{
		try {
			$result = DB::table('information_schema.columns')
				->where('table_schema', $this->database)
				->where('table_name', $table)
				->where('data_type', 'enum')
				->get(['column_name','column_type']);
			if ($result)
				return $result;
			else
				return [];
		} catch (\Exception $e){
			return [];
		}
	}

	/**
	 * @param array $fields
	 * @param string $table
	 * @return array
	 */
	protected function setEnum(array $fields, $table)
	{
		foreach ($this->getEnum($table) as $column) {
			$fields[$column->column_name]['type'] = 'enum';
			$fields[$column->column_name]['args'] = str_replace('enum(', 'array(', $column->column_type);
		}
		return $fields;
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Column[] $columns
	 * @param IndexGenerator $indexGenerator
	 * @return array
	 */
	protected function getFields($columns, IndexGenerator $indexGenerator)
	{
		$fields = array();
		foreach ($columns as $column) {
			$name = $column->getName();
			$type = $column->getType()->getName();
			$length = $column->getLength();
			$default = $column->getDefault();
			$nullable = (!$column->getNotNull());
			$index = $indexGenerator->getIndex($name);

			$decorators = null;
			$args = null;

			if (isset($this->fieldTypeMap[$type])) {
				$type = $this->fieldTypeMap[$type];
			}

			// Different rules for different type groups
			if (in_array($type, ['tinyInteger', 'smallInteger', 'integer', 'bigInteger'])) {
				// Integer
				if ($type == 'integer' and $column->getUnsigned() and $column->getAutoincrement()) {
					$type = 'increments';
					$index = null;
				} else {
					if ($column->getUnsigned()) {
						$decorators[] = 'unsigned';
					}
					if ($column->getAutoincrement()) {
						$args = 'true';
						$index = null;
					}
				}
			} elseif ($type == 'dateTime') {
				if ($name == 'deleted_at' and $nullable) {
					$nullable = false;
					$type = 'softDeletes';
					$name = '';
				} elseif ($name == 'created_at' and isset($fields['updated_at'])) {
					$fields['updated_at'] = ['field' => '', 'type' => 'timestamps'];
					continue;
				} elseif ($name == 'updated_at' and isset($fields['created_at'])) {
					$fields['created_at'] = ['field' => '', 'type' => 'timestamps'];
					continue;
				}
			} elseif (in_array($type, ['decimal', 'float', 'double'])) {
				// Precision based numbers
				$args = $this->getPrecision($column->getPrecision(), $column->getScale());
				if ($column->getUnsigned()) {
					$decorators[] = 'unsigned';
				}
			} else {
				// Probably not a number (string/char)
				if ($type === 'string' && $column->getFixed()) {
					$type = 'char';
				}
				$args = $this->getLength($length);
			}

			if ($nullable) $decorators[] = 'nullable';
			if ($default !== null) $decorators[] = $this->getDefault($default, $type);
			if ($index) $decorators[] = $this->decorate($index->type, $index->name);

			$field = ['field' => $name, 'type' => $type];
			if ($decorators) $field['decorators'] = $decorators;
			if ($args) $field['args'] = $args;
			$fields[$name] = $field;
		}
		return $fields;
	}

	/**
	 * @param int $length
	 * @return int|void
	 */
	protected function getLength($length)
	{
		if ($length and $length !== 255) {
			return $length;
		}
	}

	/**
	 * @param string $default
	 * @param string $type
	 * @return string
	 */
	protected function getDefault($default, &$type)
	{
		if (in_array($default, ['CURRENT_TIMESTAMP'])) {
			if ($type == 'dateTime')
				$type = 'timestamp';
			$default = $this->decorate('DB::raw', $default);
		} elseif (in_array($type, ['string', 'text']) or !is_numeric($default)) {
			$default = $this->argsToString($default);
		}
		return $this->decorate('default', $default, '');
	}

	/**
	 * @param int $precision
	 * @param int $scale
	 * @return string|void
	 */
	protected function getPrecision($precision, $scale)
	{
		if ($precision != 8 or $scale != 2) {
			$result = $precision;
			if ($scale != 2) {
				$result .= ', ' . $scale;
			}
			return $result;
		}
	}

	/**
	 * @param string|array $args
	 * @param string       $quotes
	 * @return string
	 */
	protected function argsToString($args, $quotes = '\'')
	{
		if ( is_array( $args ) ) {
			$seperator = $quotes .', '. $quotes;
			$args = implode( $seperator, $args );
		}

		return $quotes . $args . $quotes;
	}

	/**
	 * Get Decorator
	 * @param string       $function
	 * @param string|array $args
	 * @param string       $quotes
	 * @return string
	 */
	protected function decorate($function, $args, $quotes = '\'')
	{
		if ( ! is_null( $args ) ) {
			$args = $this->argsToString($args, $quotes);
			return $function . '(' . $args . ')';
		} else {
			return $function;
		}
	}

	/**
	 * @param IndexGenerator $indexGenerator
	 * @return array
	 */
	protected function getMultiFieldIndexes(IndexGenerator $indexGenerator)
	{
		$indexes = array();
		foreach ($indexGenerator->getMultiFieldIndexes() as $index) {
			$indexArray = [
				'field' => $index->columns,
				'type' => $index->type,
			];
			if ($index->name) {
				$indexArray['args'] = $this->argsToString($index->name);
			}
			$indexes[] = $indexArray;
		}
		return $indexes;
	}
}
