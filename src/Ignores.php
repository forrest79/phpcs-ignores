<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use Nette\Neon\Neon;
use PHP_CodeSniffer;

final class Ignores
{
	/** @var list<string> */
	private array $configFiles = [];

	/** @var array<string, array<string, array<string, int>>> */
	private array $ignoreErrors = [];


	public function __construct(PHP_CodeSniffer\Ruleset $ruleset)
	{
		$rulesetPaths = $ruleset->paths;

		foreach (array_merge([getcwd() . '/.phpcs.xml', getcwd() . '/phpcs.xml'], $rulesetPaths) as $path) {
			$filter = dirname($path) . '/' . basename($path, '.xml') . '*.neon';
			$configFiles = glob($filter);
			if ($configFiles === FALSE) {
				throw new \RuntimeException(sprintf('Can\'t load config files with filter \'%s\'.', $filter));
			}

			$this->configFiles = array_merge($configFiles, $this->configFiles);
		}

		foreach ($this->configFiles as $configFile) {
			$configFileDir = dirname($configFile);
			$configFileData = @file_get_contents($configFile); // intentionally @ - file may not exists
			if ($configFileData === FALSE) {
				throw new \RuntimeException(sprintf('Can\'t load config file \'%s\'.', $configFile));
			}

			$ignoreErrors = Neon::decode($configFileData);
			if (isset($ignoreErrors['ignoreErrors']) && is_array($ignoreErrors['ignoreErrors'])) {
				foreach ($ignoreErrors['ignoreErrors'] as $ignoreError) {
					$path = $ignoreError['path'];
					assert(is_string($path));
					if (!str_starts_with($path, '/')) {
						$path = self::getAbsolutePath($configFileDir . '/' . $path);
					}

					$sniff = $ignoreError['sniff'];
					assert(is_string($sniff));

					$message = $ignoreError['message'];
					assert(is_string($message));

					$this->ignoreErrors[$path][$sniff][$message] = (int) $ignoreError['count'];

					if ($this->ignoreErrors[$path][$sniff][$message] <= 0) {
						throw new \RuntimeException(sprintf('Count for file \'%s\', sniff \'%s\' and message \'%s\' must be greater than 0.', $path, $sniff, $message));
					}
				}
			}
		}
	}


	public function isIgnored(string $path, string $sniff, string $message): bool
	{
		if (isset($this->ignoreErrors[$path][$sniff][$message])) {
			$this->ignoreErrors[$path][$sniff][$message]--;
			if ($this->ignoreErrors[$path][$sniff][$message] === 0) {
				unset($this->ignoreErrors[$path][$sniff][$message]);
			}

			if ($this->ignoreErrors[$path][$sniff] === []) {
				unset($this->ignoreErrors[$path][$sniff]);
			}

			if ($this->ignoreErrors[$path] === []) {
				unset($this->ignoreErrors[$path]);
			}

			return TRUE;
		}

		return FALSE;
	}


	public function getRemainingIgnoreErrors(): array
	{
		return $this->ignoreErrors;
	}


	/**
	 * Path may not exists, that's why we can't use realpath().
	 */
	private static function getAbsolutePath(string $path): string
	{
		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		$absolutes = [];
		foreach ($parts as $part) {
			if ($part === '.') {
				continue;
			}
			if ($part === '..') {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return implode(DIRECTORY_SEPARATOR, $absolutes);
	}

}
