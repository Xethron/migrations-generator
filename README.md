# Laravel Migrations Generator

[![Build Status](https://travis-ci.org/Xethron/migrations-generator.png)](https://travis-ci.org/Xethron/migrations-generator)
[![Latest Stable Version](https://poser.pugx.org/xethron/migrations-generator/v/stable.png)](https://packagist.org/packages/xethron/migrations-generator)
[![Total Downloads](https://poser.pugx.org/xethron/migrations-generator/downloads.png)](https://packagist.org/packages/xethron/migrations-generator)
[![License](https://poser.pugx.org/xethron/migrations-generator/license.png)](https://packagist.org/packages/xethron/migrations-generator)

Generate Laravel Migrations from an existing database, including indexes and foreign keys!

## Install

Edit your composer.json file to require `xethron/migrations-generator` and run `composer update`
```json
"require-dev": {
    "xethron/migrations-generator": "dev-master"
}
```

Next, add the following service provider:

```
'Way\Generators\GeneratorsServiceProvider',
'Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider',
```

And you're set. To double check if its working, run `php artisan`, and look for the command `migrate:generate`

## Usage

To generate migrations from a database, you need to have your database setup in Laravel's Config.

Run `php artisan migrate:generate` to create migrations for all the tables, or you can specify the tables you wish to generate using `php artisan migrate:generate table1,table2,table3,table4,table5`. You can also ignore tables with `--ignore="table3,table4,table5"`

Laravel Migrations Generator will first generate all the tables, columns and indexes, and afterwards setup all the foreign key constraints. So make sure you include all the tables listed in the foreign keys so that they are present when the foreign keys are created.

You can also specify the connection name if you are not using your default connection with `--connection="connection_name"`

Run `php artisan help migrate:generate` for a list of options.

## Changelog

Changelog for Laravel Migrations Generator

### 24 March: v1.1.0
* Ability to add entries into the Migrations Table, so that they won't be run as they already exist.
* Convert Blobs to Binary fields
* Minor Code Changes

## Thank You

Thanks to Jeffrey Way for his amazing Laravel-4-Generators package. This package depends greatly on his work.

## License

The Laravel Migrations Generator is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
