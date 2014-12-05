<?php namespace Xethron\MigrationsGenerator\Syntax;

/**
 * Class RemoveForeignKeysFromTable
 * @package Xethron\MigrationsGenerator\Syntax
 */
class RemoveForeignKeysFromTable extends Table {

	/**
	 * Return string for dropping a foreign key
	 *
	 * @param array $foreignKey
	 * @return string
	 */
	protected function getItem(array $foreignKey)
	{
		$name = empty($foreignKey['name']) ? $this->createIndexName($foreignKey['field']) : $foreignKey['name'];
		return sprintf("\$table->dropForeign('%s');", $name);
	}

	/**
	 * Create a default index name for the table.
	 *
	 * @param  string  $column
	 * @return string
	 */
	protected function createIndexName($column)
	{
		$index = strtolower($this->table.'_'.$column.'_foreign');

		return str_replace(array('-', '.'), '_', $index);
	}
}
