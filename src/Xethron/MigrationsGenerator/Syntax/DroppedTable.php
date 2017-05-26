<?php

namespace Xethron\MigrationsGenerator\Syntax;

class DroppedTable
{
    /**
     * Get string for dropping a table
     *
     * @param      $tableName
     * @param null $connection
     *
     * @return string
     */
    public function drop($tableName, $connection = null)
    {
        $tableName = substr($tableName, strlen(\DB::connection($connection)->getTablePrefix()));
        if (!is_null($connection)) $connection = 'connection(\''.$connection.'\')->';
        return "Schema::{$connection}drop('$tableName');";
    }
}
