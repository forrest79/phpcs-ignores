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

	private bool $showOutdatedWarnings = TRUE;


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
			if (is_array($ignoreErrors) && isset($ignoreErrors['ignoreErrors']) && is_array($ignoreErrors['ignoreErrors'])) {
				foreach ($ignoreErrors['ignoreErrors'] as $ignoreError) {
					assert(is_array($ignoreError) && is_string($ignoreError['path']) && is_string($ignoreError['sniff']) && is_string($ignoreError['message']));

					$path = $ignoreError['path'];
					if (!str_starts_with($path, '/')) {
						$path = self::getAbsolutePath($configFileDir . '/' . $path);
					}

					$sniff = $ignoreError['sniff'];

					$message = $ignoreError['message'];

					$this->ignoreErrors[$path][$sniff][$message] = (int) $ignoreError['count'];

					if ($this->ignoreErrors[$path][$sniff][$message] <= 0) {
						throw new \RuntimeException(sprintf('Count for file \'%s\', sniff \'%s\' and message \'%s\' must be greater than 0.', $path, $sniff, $message));
					}
				}
			}
		}
	}


	/**
	 * @return array<string, array<string, int>>
	 */
	public function getRemainingIgnoreErrorsForFileAndClean(string $path): array
	{
		$remainingErrors = $this->ignoreErrors[$path] ?? [];
		unset($this->ignoreErrors[$path]);
		return $remainingErrors;
	}


	/**
	 * @return array<string, array<string, array<string, int>>>
	 */
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


	public function shouldShowOutdatedWarnings(): bool
	{
		return $this->showOutdatedWarnings;
	}


	public function hideOutdatedWarnings(): void
	{
		$this->showOutdatedWarnings = FALSE;
	}


	public function setInstance(): static
	{
		if (self::$instance !== NULL) {
			throw new \RuntimeException('Instance can be set just once.');
		}

		self::$instance = $this;

		return $this;
	}


	public static function getInstance(): self
	{
		if (self::$instance === NULL) {
			throw new \RuntimeException('Instance is not set.');
		}

		return self::$instance;
	}

}
