# PHPCS Ignores/baseline in the PHPStan way

[![Latest Stable Version](https://poser.pugx.org/forrest79/phpcs-ignores/v)](//packagist.org/packages/forrest79/phpcs-ignores)
[![Monthly Downloads](https://poser.pugx.org/forrest79/phpcs-ignores/d/monthly)](//packagist.org/packages/forrest79/phpcs-ignores)
[![License](https://poser.pugx.org/forrest79/phpcs-ignores/license)](//packagist.org/packages/forrest79/phpcs-ignores)
[![Build](https://github.com/forrest79/PHPCS-Ignores/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/forrest79/PHPCS-Ignores/actions/workflows/build.yml)

## Introduction

This package provides an ability to ignore concrete errors for PHPCS. You can do this in PHPCS via settings in XML or with the annotation in PHP code.
With this package you can define errors list in an external file. The inspiration is the [PHPStan baseline](https://phpstan.org/user-guide/baseline).
If you're familiar with this (and you should be :-)) it will be easy for you to define and use your own ignores/baseline. The main difference is the
message must exactly the same as PHPCS provides (in PHPStan message is a regexp) and you must always use all properties for one ignore (`message`, `sniff`,
`path` and `count` - you can't define global ignoring). The best advantage of this approach is that during file refactoring, you don't have to update your
definition files.

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```
composer require --dev forrest79/phpcs-ignores
```

## Using

Ignores list is in the [neon](https://doc.nette.org/en/neon/format) format and looks like this:

```yaml
ignoreErrors:
    -
        sniff: SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
        message: 'Method \Forrest79\PhpCsIgnores\Tests\File1::method1() does not have native type hint for its parameter $report but it should be possible to add it based on @param annotation "bool".'
        count: 1
        path: File1.php

    -
        sniff: SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon
        message: There must be no whitespace between closing parenthesis and return type colon.
        count: 1
        path: File2.php
```

The first line `ignoreErrors:` defines a structure/array. Item in the array is one error. You must provide concrete `sniff, `message`, `path` and `count`.

`sniff` and `message` is provided by PHPCS report. `file` is an absolute or relative path from the neon file directory to the checked file and `count`
is how many times is this error presented in the file.

The neon file is searched in the directory with your XML settings (and every included XML) or in the current directory and the name must begin with the XML settings name
(or `phpcs`/`.phpcs`), prefix should be whatever you want and the extension must be `.neon`. You can use more than one `neon` file and the settings
are merged (me personally in legacy projects using `phpcs-baseline.neon` for errors I want to fix in the future and `phpcs-ignores.neon` for valid ignores).

Example:

- PHPCS settings is in the `phpcs.xml` - neon can be `phpcsignores.neon` or `phpcs-baseline.neon` or `phpcs-whateveryouwant.neon`
- PHPCS settings is in the `mysettings.xml` - neon can be `mysettings.neon` or `mysettings-baseline.neon` or `mysettings-whateveryouwant.neon`

### How to use your baseline/ignores in PHPCS

It's very simple (when you're not using your own bootstrap file - if you're using one, you must manually include our bootstrap file in your PHP bootstrap file),
just use your PHPCS settings/command-line parameters and add our bootstrap file. In the command line like this:

```bash
vendor/bin/phpcs --bootstrap=vendor/forrest79/phpcs-ignores/src/bootstrap.php -sp src tests
```

Just use the correct paths. This should work with all other PHPCS settings like `cache` and `parellel`.

By default, you will be noticed about all outdated ignores in processed files, so you can simply remove these definitions from the neon files. But if you want
to be informed also about the whole files, that wasn't matched during analyses, you must use the second bootstrap `bootstrap-outdated.php` and you must be sure,
you're running analysis on your whole repository. If you're using for example PHPCS only on changed files (via GIT), you can't use this detecting because you will
get many false positive messages. 

### Known limitations

1. Because messages are not regexps but concrete strings, this won't work for the sniffs that includes absolute file path in the message in case you want to run PHPCS on other environments
2. PHPCBF (The Fixer) - if some error is presented in a file many times and some `count` of it is ignores, fixed will be always the last errors. The first `count` errors will be ignored and the rest will be fixed. So it could be fixed other error than you want. Nothing can be done with this right now - you must manually update these files after fix.

### Generating baseline

You can simply generate baseline `neon` file from existing errors. Just use our `BaselineReport`:

```bash
vendor/bin/phpcs --report=\\Forrest79\\PhpCsIgnores\\BaselineReport -s src tests
```

Generated neon file is printed to the stdout, so probably you want to send this data right to some file:

```bash
vendor/bin/phpcs --report=\\Forrest79\\PhpCsIgnores\\BaselineReport -s src tests > phpcs-baseline.neon
```

> You will probably need update path in generated list (there could be both absolute/relative paths depends on what are your sources and in what directory
> you run PHPCS) - it should be simply done in your favorite editor with Find and replace function. 

## How does it work?

Ok, PHPCS is a really old (but good!) piece of software and couldn't be easy extended so there must be some hacking and some magic and some praying that this
will still work in the next version. I went through many death ends, some of them you can see in the first commits. I hope that package will survive
all future v3 versions and hopefully, in v4 there will some internal ability for this. 

I don't want to physically change files in `vendor` (like my inspiration package https://github.com/123inkt/php-codesniffer-baseline) so I decided to use changing
PHP files on the fly like method `bypassFinal` do in my favorite PHP tester [Nette Tester](https://tester.nette.org/). So I found where the errors are
processed in the original PHPCS - it's in this object `PHP_CodeSniffer\Files\LocalFile`. I need to extend this object and add my own logic. I found code, where
this object is instanced and via `PhpCsInjections.php` I'm updating this file - so my object `File` is created instead of the original `PHP_CodeSniffer\Files\LocalFile`.

This is the main magic. Injections work only for files that aren't yet loaded via PHP, so I want to enable it as soon as possible. For this I'm using
bootstrap files. PHPCS includes this directly into main `PHP_CodeSniffer\Runner` at the beginning of the life cycle. This will register injections, load neon settings
and set `config->recordErrors = TRUE` - we need always enabled `recordErrors` (it could be disabled via settings, and it's disabled by fixer), without this, error messages
are not generated and we can't check if there are ignored.

The last thing is to check errors in processed file, set ignored messages and add info about outdated once. For PHPCS this is done after `process()` method is done. This is
because we want the cache to be fully write and this is done in the original `process()` method. So we check existing errors, remove ignored once, update counts and check what
errors are outdated and add these as warnings.

For PHPCBF we need to check error right in the `addMessage()` method, because for ignored errors we need instantly return `FALSE` to not fix this ignores error.
Fixing is run in a many loops and in the every loop we need to ignore the same errors so after each proccess are ignored error for fixer reset.   

Checking outdated files is done by `OutdatedFiles` object, that is activated via `bootstrap-outdated.php`. There is nothing special to write about,
just some code to properly work in a parallel mode (using temporary files in `/tmp`).

**If you are an expert PHPCS user, please feel free to add an issue or PR with proposals and improvements. Thanks!**
