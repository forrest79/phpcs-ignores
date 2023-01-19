<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use Nette\Neon\Neon;
use PHP_CodeSniffer;

final class Ignores
{
	private static self|NULL $instance = NULL;

	/** @var list<string> */
	private array $configFiles = [];

	/** @var array<string, array<string, array<string, int>>> */
	private array $ignoreErrors = [];

	/** @var array<string, array<string, array<string, array<string, int>>>> */
	private array $ignoreErrorsByHash = [];

	private Outdated|NULL $outdated = NULL;


	/**
	 * @param array<string> $rulesetPaths
	 */
	public function __construct(array $rulesetPaths, Outdated|NULL $outdated = NULL)
	{
		foreach ($rulesetPaths as $path) {
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

		if ($outdated !== NULL) {
			$outdated->setOriginalIgnoreErrors($this->ignoreErrors);
			$this->outdated = $outdated;
		}
	}


	/**
	 * @see PhpCsInjections for usage
	 * @param array<string, mixed> $data
	 */
	public function isIgnored(
		PHP_CodeSniffer\Fixer $fixer,
		string $path,
		string $sniff,
		string $message,
		array $data,
		string|NULL $sniffMessage,
	): bool
	{
		$message = $sniffMessage ?? $message;

		if ($data !== []) {
			$message = vsprintf($message, $data);
		}

		// Internally - PHPCS can check one error more than once, when fixing is active - we need to check this separately for every file check ( magic :-( )
		$hash = ($fixer->enabled ? 'F-YES' : 'F-NO') . ':' . $fixer->loops;
		$this->ignoreErrorsByHash[$hash] ??= $this->ignoreErrors;

		if (isset($this->ignoreErrorsByHash[$hash][$path][$sniff][$message])) {
			$this->ignoreErrorsByHash[$hash][$path][$sniff][$message]--;
			if ($this->ignoreErrorsByHash[$hash][$path][$sniff][$message] === 0) {
				unset($this->ignoreErrorsByHash[$hash][$path][$sniff][$message]);
			}

			if (!$fixer->enabled) {
				$this->outdated?->addIgnoredError($path, $sniff, $message);
			}

			return TRUE;
		}

		return FALSE;
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


	/**
	 * @return list<string>
	 */
	public function getConfigFiles(): array
	{
		return $this->configFiles;
	}


	public static function getInstance(PHP_CodeSniffer\Config $config, PHP_CodeSniffer\Ruleset $ruleset): self
	{
		if (self::$instance === NULL) {
			$outdated = NULL;

			$settings = $config->getSettings();
			if (($settings['cache'] === FALSE) && ($settings['parallel'] === 1)) {
				$outdated = new Outdated(array_key_exists('checkstyle', $settings['reports'] ?? []));
			}

			self::$instance = new self($ruleset->paths, $outdated);
		}

		return self::$instance;
	}

}
