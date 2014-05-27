<?php namespace Xethron\MigrationsGenerator\Generators;

class ForeignKeyGenerator {

	/**
	 * Get array of foreign keys
	 * @param string $table Table Name
	 * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @return array
	 */
	public function generate( $table, $schema )
	{
		$fields = [];

		$foreignKeys = $schema->listTableForeignKeys( $table );

		if ( empty( $foreignKeys ) ) return array();

		foreach ( $foreignKeys as $foreignKey ) {
			$fields[] = [
				'field' => $foreignKey->getLocalColumns()[0],
				'references' => $foreignKey->getForeignColumns()[0],
				'on' => $foreignKey->getForeignTableName(),
				'onUpdate' => $foreignKey->hasOption('onUpdate') ? $foreignKey->getOption('onUpdate') : 'RESTRICT',
				'onDelete' => $foreignKey->hasOption('onDelete') ? $foreignKey->getOption('onDelete') : 'RESTRICT',
			];
		}
		return $fields;
	}
}
