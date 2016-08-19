# Laravel Migrations Generator

[![Build Status](https://travis-ci.org/Xethron/migrations-generator.svg)](https://travis-ci.org/Xethron/migrations-generator)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Xethron/migrations-generator/badges/quality-score.png?s=41d919c6d044749cb8575bb936efbddc4cebc0d8)](https://scrutinizer-ci.com/g/Xethron/migrations-generator/)
[![Latest Stable Version](https://poser.pugx.org/xethron/migrations-generator/v/stable.png)](https://packagist.org/packages/xethron/migrations-generator)
[![Total Downloads](https://poser.pugx.org/xethron/migrations-generator/downloads.png)](https://packagist.org/packages/xethron/migrations-generator)
[![License](https://poser.pugx.org/xethron/migrations-generator/license.png)](https://packagist.org/packages/xethron/migrations-generator)

Generate Laravel Migrations from an existing database, including indexes and foreign keys!

## Laravel 5 installation

The recommended way to install this is through composer:

Get a token from github if you don't already have one.

https://github.com/settings/tokens/new?scopes=repo&description=Composer+on+This-PC+YYYY-MM-DD+HHMM

```bash
composer require --dev --no-update "xethron/migrations-generator:dev-l5"
composer require --dev --no-update "way/generators:dev-feature/laravel-five-stable"
composer config repositories.repo-name git "git@github.com:jamisonvalenta/Laravel-4-Generators.git"
composer config github-oauth.github.com <add github token>
composer update
```

Edit `config/app.php` and add this to providers section:

```php
Way\Generators\GeneratorsServiceProvider::class,
Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider::class,
```

Notes:
* Thanks to @jamisonvalenta, you can now generate Migrations in Laravel 5!
* `feature/laravel-five-stable` was forked from `way/generators` `3.0.3` and was made Laravel `5.0` ready. Jeffrey Way has discontinued support for Laravel 5, so the other `artisan generate:` commands may not have been made `5.0` compatible.  Investigate the `artisan make:` commands for substitutes, contribute to Laravel to extend generation support, or fix it and submit a PR to `jamisonvalenta/feature/laravel-five-stable`.

## Laravel 4 installation

Edit your composer.json file to require `xethron/migrations-generator` and run `composer update`
```json
"require-dev": {
    "xethron/migrations-generator": "dev-master"
}
```

Next, add the following service providers:

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

Check out Chung Tran's blog post for a quick step by step introduction: [Generate Migrations from an existing database in Laravel 4](http://codingtip.blogspot.com/2014/04/laravel-4-generate-migration-existed-dabase-laravel-4.html)

## Changelog

Changelog for Laravel Migrations Generator

### 25 July: v1.2.2
* Support for Laravel 4.2
* Support for named foreign keys
* Fix error with --ignore option

### 29 May: v1.2.1
* Fixed problem with char fields showing up as varchar
* Allow decimal, float, and double to be unsigned
* Allow cascading on foreign key update/delete

### 16 May: v1.2.0
* Now fully supports for enum fields
* Add support for bit fields as Boolean (Laravel Migration Limitation)

### 10 May: v1.1.1
* Fix crash when migrating tables that use enum
* Added Tests
* Major refactoring of the code

### 24 March: v1.1.0
* Ability to add entries into the Migrations Table, so that they won't be run as they already exist.
* Convert Blobs to Binary fields
* Minor Code Changes

## Thank You

Thanks to Jeffrey Way for his amazing Laravel-4-Generators package. This package depends greatly on his work.

## Contributors

Bernhard Breytenbach ([@BBreyten](https://twitter.com/BBreyten))

## License

The Laravel Migrations Generator is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
