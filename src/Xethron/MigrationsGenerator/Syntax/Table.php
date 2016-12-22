<?php namespace Xethron\MigrationsGenerator\Syntax;

/**
 * Class Table
 * @package Xethron\MigrationsGenerator\Syntax
 */
abstract class Table extends \Way\Generators\Syntax\Table{

	/**
	 * @var string
	 */
	protected $table;

    /**
     * @param array  $fields
     * @param string $table
     * @param string $method
     * @param null   $connection
     *
     * @return string
     */
	public function run(array $fields, $table, $connection = null, $method = 'table')
	{
		$table = substr($table, strlen(\DB::getTablePrefix()));
		$this->table = $table;
        if (!is_null($connection)) $method = 'connection(\''.$connection.'\')->'.$method;
		$compiled = $this->compiler->compile($this->getTemplate(), ['table'=>$table,'method'=>$method]);
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
			// Do we need to tack on the parentheses?
			if (strpos($decorator, '(') === false) {
				$output .= '()';
			}
		}
		return $output;
	}
}
