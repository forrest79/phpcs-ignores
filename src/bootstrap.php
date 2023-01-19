<?php declare(strict_types=1);

Forrest79\PhpCsIgnores\PhpCsInjections::register();

assert($this instanceof PHP_CodeSniffer\Runner);

$settings = $this->config->getSettings();

$ignores = Forrest79\PhpCsIgnores\Ignores::getInstance($this->config, $this->ruleset);

if ($settings['cache'] === TRUE) { // Cache invalidation
	$phpcsCacheFile = $settings['cacheFile'];
	assert(is_string($phpcsCacheFile));

	$ignoresHashFile = $phpcsCacheFile . '-phpcs-ignore-hash';

	$ignoresHash = @file_get_contents($ignoresHashFile); // intentionally @ - file may not exists
	if ($ignoresHash === FALSE) {
		$ignoresHash = '';
	}

	$actualHash = '';
	foreach ($ignores->getConfigFiles() as $file) {
		$actualHash .= md5_file($file);
	}

	if ($ignoresHash !== $actualHash) {
		if (PHP_CODESNIFFER_VERBOSITY > 0) {
			echo 'Invalidating PHPCS cache because ignore definition was changed.' . PHP_EOL;
		}
		@unlink($phpcsCacheFile); // intentionally @ - file may not exists

		file_put_contents($ignoresHashFile, $actualHash);
	}
}
