<?php declare(strict_types=1);

$outdatedVirtualFile = sys_get_temp_dir() . '/phpcs-ignores-outdated.php';
if (!file_exists($outdatedVirtualFile)) {
	file_put_contents($outdatedVirtualFile, '<?php');
}

require_once __DIR__ . '/bootstrap.php';
