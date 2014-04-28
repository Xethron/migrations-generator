<?php namespace Xethron\MigrationsGenerator\Syntax;

/**
 * Class Table
 * @package Xethron\MigrationsGenerator\Syntax
 */
abstract class Table extends \Way\Generators\Syntax\Table{

	/**
	 * @param array $migrationData
	 * @param array $fields
	 * @return string
	 */
	public function run(array $migrationData, array $fields)
	{
		if ( ! isset($migrationData['method'])) {
			$migrationData['method'] = 'table';
		}
		$compiled = $this->compiler->compile($this->getTemplate(), $migrationData);
		return $this->replaceFieldsWith($this->getItems($fields), $compiled);
	}

	/**
	 * Return string for adding all foreign keys
	 *
	 * @param array $items
	 * @return array
	 */
	protected function getItems(array $items)
	{
		$result = array();
		foreach($items as $item) {
			$result[] = $this->getItem($item);
		}
		return $result;
	}

	/**
	 * @param array $item
	 * @return string
	 */
	abstract protected function getItem(array $item);

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
