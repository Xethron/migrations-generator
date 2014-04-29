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
		return sprintf( "\$table->dropForeign('%s');", strtolower( $this->table .'_'. $foreignKey['field'] .'_foreign' ) );
	}
}
