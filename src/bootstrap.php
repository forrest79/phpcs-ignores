<?php declare(strict_types=1);

assert($this instanceof PHP_CodeSniffer\Runner);

$ignores =  new Forrest79\PhpCsIgnores\Ignores($this->ruleset);

// can be defined via bootstrap-outdated.php
$outdatedVirtualFile ??= NULL;

$this->reporter = new Forrest79\PhpCsIgnores\Reporter($ignores, $this->config, $outdatedVirtualFile);

$this->config->recordErrors = true; // needed for correct working in CBF (we need errors details to check if it is ignored in report)

if ($outdatedVirtualFile !== NULL) {
	$files = $this->config->files;
	$files[] = $outdatedVirtualFile; // this must be last file to check - it's virtual file that perform check what ignored errors was not matched
	$this->config->files = $files;
}
