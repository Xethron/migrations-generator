<?php namespace Xethron\MigrationsGenerator\Syntax;

use Way\Generators\Syntax\Table;

class AddToTable extends Table {

	/**
	 * Add syntax for table addition
	 *
	 * @param $migrationData
	 * @param array $fields
	 * @return mixed
	 */
	public function add($migrationData, array $fields)
	{
		if ( ! isset($migrationData['method']))
		{
			$migrationData['method'] = 'table';
		}

		$compiled = $this->compiler->compile($this->getTemplate(), $migrationData);

		return $this->replaceFieldsWith($this->addColumns($fields), $compiled);
	}

	/**
	 * Return string for adding all columns
	 *
	 * @param $fields
	 * @return array
	 */
	protected function addColumns($fields)
	{
		$schema = [];

		foreach($fields as $field)
		{
			$schema[] = $this->addColumn($field);
		}

		return $schema;
	}

	/**
	 * Return string for adding a column
	 *
	 * @param $field
	 * @return string
	 */
	private function addColumn($field)
	{
		$property = $field['field'];

		// If the field is an array,
		// make it an array in the Migration
		if (is_array($property)) {
			$property = "['". implode("','", $property) ."']";
		} else {
			$property = $property ? "'$property'" : null;
		}

		$type = $field['type'];

		$output = sprintf(
			"\$table->%s(%s)",
			$type,
			$property
		);

		// If we have args, then it needs
		// to be formatted a bit differently
		if (isset($field['args']))
		{
			$output = sprintf(
				"\$table->%s(%s, %s)",
				$type,
				$property,
				$field['args']
			);
		}

		if (isset($field['decorators']))
		{
			$output .= $this->addDecorators( $field['decorators'] );
		}

		return $output . ';';
	}

	/**
	 * @param $decorators
	 * @return string
	 */
	protected function addDecorators( $decorators )
	{
		$output = '';

		foreach ( $decorators as $decorator ) {
			$output .= sprintf( "->%s", $decorator );

			// Do we need to tack on the parens?
			if ( strpos( $decorator, '(' ) === false ) {
				$output .= '()';
			}
		}

		return $output;
	}

}