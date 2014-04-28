<?php namespace Xethron\MigrationsGenerator\Syntax;

/**
 * Class RemoveForeignKeysFromTable
 * @package Xethron\MigrationsGenerator\Syntax
 */
class RemoveForeignKeysFromTable extends Table {

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * Compile and return string for removing columns
	 *
	 * @param array $migrationData
	 * @param array $foreignKeys
	 * @return string
	 */
	public function run(array $migrationData, array $foreignKeys)
	{
		$this->table = $migrationData['table'];
		$migrationData['method'] = 'table';
		return parent::run($migrationData, $foreignKeys);
	}

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
