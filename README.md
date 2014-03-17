# Laravel Migrations Generator

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
'Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider',
```

And you're set. To double check if its working, run `php artisan`, and look for the command `migrate:generate`

## Usage

To generate migrations from a database, you need to have your database setup in Laravel's Config.

`php artisan migrate:generate table1,table2,table3,table4,table5"`

Laravel Migrations Generator will first generate all the tables, columns and indexes, and afterwards setup all the foreign key constraints. So make sure you include all the tables listed in the foreign keys so that they are present when the foreign keys are created.

You can also specify the connection name if you are not using your default connection:

`php artisan migrate:generate table1,table2,table3,table4,table5 --connection="connection_name"`

Run `php artisan help migrate:generate` for a list of options.

## Thank You

Thanks to Jeffrey Way for his amazing Laravel-4-Generators package. This package depends greatly on his work.

## License

The Laravel Migrations Generator is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)