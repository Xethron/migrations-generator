<?php namespace Xethron\MigrationsGenerator\Syntax;

use Way\Generators\Syntax\Table;

class RemoveForeignKeysFromTable extends Table {

	/**
	 * Compile and return string for removing columns
	 *
	 * @param $migrationData
	 * @param array $foreignKeys
	 * @return mixed
	 */
	public function remove( $migrationData, array $foreignKeys )
	{
		$this->table = $migrationData['table'];

		$migrationData['method'] = 'table';

		$compiled = $this->compiler->compile( $this->getTemplate(), $migrationData );

		return $this->replaceFieldsWith( $this->dropForeignKeys($foreignKeys), $compiled );
	}

	/**
	 * Return string for dropping all foreign keys
	 *
	 * @param array $foreignKeys
	 * @return array
	 */
	protected function dropForeignKeys( array $foreignKeys )
	{
		$schema = [];

		foreach( $foreignKeys as $foreignKey )
		{
			$schema[] = $this->dropForeignKey( $foreignKey );
		}

		return $schema;
	}

	/**
	 * Return string for dropping a foreign key
	 *
	 * @param $foreignKey
	 * @return string
	 */
	private function dropForeignKey( $foreignKey )
	{
		return sprintf( "\$table->dropForeign('%s');", strtolower( $this->table .'_'. $foreignKey['field'] .'_foreign' ) );
	}

}