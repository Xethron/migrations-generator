<?php namespace Xethron\MigrationsGenerator\Syntax;

use Way\Generators\Syntax\Table;

class AddForeignKeysToTable extends Table {

	/**
	 * Add syntax for table addition
	 *
	 * @param $migrationData
	 * @param array $foreignKeys
	 * @return string
	 */
	public function add($migrationData, array $foreignKeys)
	{
		$migrationData['method'] = 'table';

		$compiled = $this->compiler->compile($this->getTemplate(), $migrationData);

		return $this->replaceFieldsWith($this->addForeignKeys($foreignKeys), $compiled);
	}

	/**
	 * Return string for adding all foreign keys
	 *
	 * @param $foreignKeys
	 * @return array
	 */
	protected function addForeignKeys($foreignKeys)
	{
		$schema = [];

		foreach($foreignKeys as $foreignKey)
		{
			$schema[] = $this->addForeignKey($foreignKey);
		}

		return $schema;
	}

	/**
	 * Return string for adding a foreign key
	 *
	 * @param $foreignKey
	 * @return string
	 */
	protected function addForeignKey($foreignKey)
	{
		$output = sprintf(
			"\$table->foreign('%s')->references('%s')->on('%s')",
			$foreignKey['field'],
			$foreignKey['references'],
			$foreignKey['on']
		);

		if (isset($foreignKey['decorators']))
		{
			$output .= $this->addDecorators($foreignKey['decorators']);
		}

		return $output . ';';
	}

	/**
	 * @param $decorators
	 * @return string
	 */
	protected function addDecorators($decorators)
	{
		$output = '';

		foreach ($decorators as $decorator) {
			$output .= sprintf("->%s", $decorator);

			// Do we need to tack on the parens?
			if (strpos($decorator, '(') === false) {
				$output .= '()';
			}
		}

		return $output;
	}

}
