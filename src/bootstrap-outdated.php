<?php declare(strict_types=1);

$outdatedVirtualFile = sys_get_temp_dir() . '/phpcs-ignores-outdated.php';
if (!file_exists($outdatedVirtualFile)) {
	file_put_contents($outdatedVirtualFile, '<?php');
}

assert(isset($this) && ($this instanceof PHP_CodeSniffer\Runner));

// If the PCNTL extension isn't installed, we can't fork. (Copied from Runner.php - we need this set here to use correct settings in OutdatedFiles)
if (!function_exists('pcntl_fork')) {
	$this->config->parallel = 1;
}

require_once __DIR__ . '/bootstrap.php';
