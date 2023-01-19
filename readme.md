# PhPgSql class reflection extension for PHPStan

[![Latest Stable Version](https://poser.pugx.org/forrest79/phpgsql-phpstan/v)](//packagist.org/packages/forrest79/phpgsql-phpstan)
[![Monthly Downloads](https://poser.pugx.org/forrest79/phpgsql-phpstan/d/monthly)](//packagist.org/packages/forrest79/phpgsql-phpstan)
[![License](https://poser.pugx.org/forrest79/phpgsql-phpstan/license)](//packagist.org/packages/forrest79/phpgsql-phpstan)
[![Build](https://github.com/forrest79/PhPgSql-PHPStan/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/forrest79/PhPgSql-PHPStan/actions/workflows/build.yml)

* [PHPStan](https://github.com/phpstan/phpstan)
* [PhPgSql](https://github.com/forrest79/PhPgSql)

## Introduction

This extension defines dynamic methods and other PHPStan setting for `Forrest79\PhPgSql`.

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```
composer require --dev forrest79/phpgsql-phpstan
```

## Using

Include `extension.neon` in your project's PHPStan config:

```yaml
includes:
    - vendor/forrest79/phpgsql-phpstan/extension.neon
```

If you're using your own `Forrest79\PhPgSql\Db\Row` or `Forrest79\PhPgSql\Fluen\Query`, you can set it likes this:

```yaml
parameters:
	forrest79:
		phpgsql:
			dbRowClass: MyOwn\PhPgSql\Db\RowXyz
			fluentQueryClass: MyOwn\PhPgSql\Fluent\QueryXyz
```

Or you can set just one extension:

- for `PhPgSql\Db\Result` (for fetching the correct `Row` object from fetch methods - unfortunately `Row` iteration must be typed right in your code for now):

```yaml
services:
	Forrest79PhPgSqlPHPStanReflectionDbResultDynamicMethodReturnTypeExtension:
		arguments:
			dbRowClass: MyOwn\PhPgSql\Db\RowXyz
```
- for `PhPgSql\Fluent\QueryExecute` (for fetching the correct `Row` object from fetch methods - unfortunately `Row` iteration must be typed right in your code for now):

```yaml
services:
	Forrest79PhPgSqlPHPStanReflectionFluentQueryExecuteDynamicMethodReturnTypeExtension:
		arguments:
			dbRowClass: MyOwn\PhPgSql\Db\RowXyz
```

- for `PhPgSql\Fluent\Complex` (to return right `Query` in `query()` method):

```yaml
services:
	Forrest79PhPgSqlPHPStanReflectionFluentComplexDynamicMethodReturnTypeExtension:
		arguments:
			fluentQueryClass: MyOwn\PhPgSql\Fluent\QueryXyz
```

You can also use simple `Row` type narrowing function `is_dbrow($row[, $expectedProperties])`. It returns `bool` but it's recommended to use this always only with the `assert()` function. This package will be probably missing in your production vendor so `is_dbrow` function will be missing too and PHP script will crash on this. With correct production settings for the `assert()` function is calling `is_dbrow()` omitted and your production code will be correct.

In PHP this function only checks if `$row` is instance of `Forrest79\PhPgSql\Db\Row` and if you specify `$expectedProperties` (keys are columns names and values are PHP types as string) it will check, if row has defined exactly expected columns (in PHP there is no types check, it's only for PHPStan).

The real magic is hidden in PHPStan extension. This function will narrow `$row` type as `Forrest79\PhPgSql\Db\Row` or your custom row object. And if you specify also properties, this will be correctly typed for PHPStan - originally all properties are `mixed`.

```php
foreach ($someQuery as $row) {
  // here is $row as Forrest79\PhPgSql\Db\Row for PHPStan
  assert(is_dbrow($row));
  // here is as your custom row object
}

$row = $someQuery->select(['columnInt', 'columnString', 'columnFloat', 'columnDatetime'])->...->fetch();
$row->columnInt; // mixed for PHPStan
$row->columnString; // mixed for PHPStan
$row->columnFloat; // mixed for PHPStan
$row->columnDatetime; // mixed for PHPStan

assert(is_dbrow($row, ['columnInt' => '?int', 'columnString' => 'string', 'columnFloat' => 'float', 'columnDatetime' => \DateTime::class]));
$row->columnInt; // int or NULL for PHPStan
$row->columnString; // string for PHPStan
$row->columnFloat; // float for PHPStan
$row->columnDatetime; // \DateTime for PHPStan
```

To set only this extension use:

```yaml
services:
	Forrest79PhPgSqlPHPStanAnalyserIsDbRowFunctionTypeSpecifyingExtension:
		arguments:
			dbRowClass: MyOwn\PhPgSql\Db\RowXyz
```

Or you can use simple `DbRow` annotation.

```php
foreach ($someQuery as $row) {
  // here is $row as Forrest79\PhPgSql\Db\Row for PHPStan
  /** @var DbRow $row */
  // here is as your custom row object
}
```

Use `DbRow` pseudotype whenever you want, params, returns, vars...

To set only this extension use:

```yaml
services:
	Forrest79PhPgSqlPHPStanPhpDocDbRowTypeNodeResolverExtension:
		arguments:
			dbRowClass: MyOwn\PhPgSql\Db\RowXyz
```
