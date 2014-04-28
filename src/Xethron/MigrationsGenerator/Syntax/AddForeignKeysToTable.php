<?php namespace Xethron\MigrationsGenerator\Syntax;

/**
 * Class AddForeignKeysToTable
 * @package Xethron\MigrationsGenerator\Syntax
 */
class AddForeignKeysToTable extends Table {

	/**
	 * Add syntax for table addition
	 *
	 * @param array $migrationData
	 * @param array $foreignKeys
	 * @return string
	 */
	public function run(array $migrationData, array $foreignKeys)
	{
		$migrationData['method'] = 'table';
		return parent::run($migrationData, $foreignKeys);
	}

	/**
	 * Return string for adding a foreign key
	 *
	 * @param array $foreignKey
	 * @return string
	 */
	protected function getItem(array $foreignKey)
	{
		$output = sprintf(
			"\$table->foreign('%s')->references('%s')->on('%s')",
			$foreignKey['field'],
			$foreignKey['references'],
			$foreignKey['on']
		);
		if (isset($foreignKey['decorators'])) {
			$output .= $this->addDecorators($foreignKey['decorators']);
		}
		return $output . ';';
	}

}
