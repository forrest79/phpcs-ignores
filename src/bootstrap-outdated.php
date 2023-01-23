<?php declare(strict_types=1);

$outdatedVirtualFileOriginal = tempnam(sys_get_temp_dir(), 'phpcs-ignores-outdated');
$outdatedVirtualFile = $outdatedVirtualFileOriginal . '.php';
file_put_contents($outdatedVirtualFile, '<?php');
@unlink($outdatedVirtualFileOriginal); // intentionally @ - file may not exists

require_once __DIR__ . '/bootstrap.php';
