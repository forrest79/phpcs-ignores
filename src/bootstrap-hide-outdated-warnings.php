<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

assert(isset($ignores) && ($ignores instanceof Forrest79\PhpCsIgnores\Ignores));

$ignores->hideOutdatedWarnings();
