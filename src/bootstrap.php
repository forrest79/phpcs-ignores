<?php declare(strict_types=1);

Forrest79\PhpCsIgnores\PhpCsInjections::register();

assert(isset($this) && ($this instanceof PHP_CodeSniffer\Runner));

$ignores = (new Forrest79\PhpCsIgnores\Ignores($this->ruleset))->setInstance();

// is defined via bootstrap-outdated.php
if (isset($outdatedVirtualFile)) {
	assert(is_string($outdatedVirtualFile));
	(new Forrest79\PhpCsIgnores\OutdatedFiles($ignores, $this->config, $outdatedVirtualFile))->setInstance();
}

$this->config->recordErrors = true; // needed for correct working in CBF (we need errors details to check if it is ignored in report)
