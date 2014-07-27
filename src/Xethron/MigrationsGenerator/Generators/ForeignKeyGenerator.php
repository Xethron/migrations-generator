<?php namespace Xethron\MigrationsGenerator\Generators;

class ForeignKeyGenerator {

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * Get array of foreign keys
	 *
	 * @param string                                      $table Table Name
	 * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @param                                             $ignoreForeignKeyNames
	 *
	 * @return array
	 */
	public function generate($table, $schema, $ignoreForeignKeyNames)
	{
		$this->table = $table;
		$fields = [];

		$foreignKeys = $schema->listTableForeignKeys($table);

		if ( empty( $foreignKeys ) ) return array();

		foreach ( $foreignKeys as $foreignKey ) {
			$fields[] = [
				'name' => $this->getName($foreignKey, $ignoreForeignKeyNames),
				'field' => $foreignKey->getLocalColumns()[0],
				'references' => $foreignKey->getForeignColumns()[0],
				'on' => $foreignKey->getForeignTableName(),
				'onUpdate' => $foreignKey->hasOption('onUpdate') ? $foreignKey->getOption('onUpdate') : 'RESTRICT',
				'onDelete' => $foreignKey->hasOption('onDelete') ? $foreignKey->getOption('onDelete') : 'RESTRICT',
			];
		}
		return $fields;
	}

	/**
	 * @param      $foreignKey
	 * @param bool $ignoreForeignKeyNames
	 *
	 * @return null
	 */
	private function getName($foreignKey, $ignoreForeignKeyNames) {
		if ($ignoreForeignKeyNames or $this->isDefaultName($foreignKey)) {
			return null;
		}
		return $foreignKey->getName();
	}

	/**
	 * @param $foreignKey
	 *
	 * @return bool
	 */
	private function isDefaultName($foreignKey) {
		return $foreignKey->getName() === $this->createIndexName($foreignKey->getLocalColumns()[0]);
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
