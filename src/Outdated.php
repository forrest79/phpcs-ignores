<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class Outdated
{
	private const ERROR_EXIT_CODE = 3;

	private bool $xmlOutput;

	/** @var array<string, array<string, array<string, int>>> */
	private array $originalIgnoreErrors = [];

	/** @var array<string, array<string, array<string, int>>> */
	private array $ignoredErrors = [];

	private static string|NULL $prefixPathDetection = NULL;


	public function __construct(bool $xmlOutput)
	{
		$this->xmlOutput = $xmlOutput;
	}


	public function __destruct()
	{
		$outdatedIgnores = $this->getOutdatedIgnores();
		if ($outdatedIgnores !== []) {
			echo $this->printReport($outdatedIgnores);
			exit(self::ERROR_EXIT_CODE);
		}
	}


	/**
	 * @param array<string, array<string, array<string, int>>> $ignoreErrors
	 */
	public function setOriginalIgnoreErrors(array $ignoreErrors): void
	{
		$this->originalIgnoreErrors = $ignoreErrors;
	}


	public function addIgnoredError(string $path, string $sniff, string $message): void
	{
		$this->ignoredErrors[$path][$sniff][$message] ??= 0;

		$this->ignoredErrors[$path][$sniff][$message]++;
	}


	/**
	 * @return array<string, list<array{source: string, message: string}>>
	 */
	private function getOutdatedIgnores(): array
	{
		$report = [];
		foreach ($this->originalIgnoreErrors as $path => $sniffs) {
			foreach ($sniffs as $sniff => $messages) {
				foreach ($messages as $message => $expectedCount) {
					if (!isset($this->ignoredErrors[$path][$sniff][$message])) {
						$report[$path][] = [
							'source' => $sniff,
							'message' => sprintf(
								'Ignored sniff \'%s\' with message \'%s\' was not matched in report.',
								$sniff,
								$message,
							),
						];
					} else {
						$realCount = $this->ignoredErrors[$path][$sniff][$message];
						$diff = $expectedCount - $realCount;
						if ($diff > 0) {
							$report[$path][] = [
								'source' => $sniff,
								'message' => sprintf(
									'Ignored sniff \'%s\' with message \'%s\' is expected to occur %d times, but occurred only %s.',
									$sniff,
									$message,
									$expectedCount,
									$realCount === 1 ? '1 time' : ($realCount . ' times'),
								),
							];
						}
					}
				}
			}
		}

		return $report;
	}


	/**
	 * @param array<string, list<array{source: string, message: string}>> $outdatedIgnores
	 */
	private function printReport(array $outdatedIgnores): string
	{
		$report = '';

		if ($this->xmlOutput) {
			$report .= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
			$report .= '<checkstyle version="3.7.1">' . PHP_EOL;

			foreach ($outdatedIgnores as $path => $errors) {
				$report .= sprintf('<file name="%s">', $path) . PHP_EOL;

				foreach ($errors as $error) {
					$report .= sprintf(
						' <error line="0" column="0" severity="error" message="%s" source="%s"/>',
						htmlspecialchars($error['message'], ENT_XML1 | ENT_COMPAT),
						htmlspecialchars($error['source'], ENT_XML1 | ENT_COMPAT),
					) . PHP_EOL;
				}

				$report .= '</file>' . PHP_EOL;
			}

			$report .= '</checkstyle>' . PHP_EOL;
		} else {
			$separator = str_repeat('-', 70);
			foreach ($outdatedIgnores as $path => $errors) {
				$report .= PHP_EOL . PHP_EOL . 'FILE: ' . self::relativePath($path) . PHP_EOL;

				foreach ($errors as $error) {
					$report .= $separator . PHP_EOL;

					$message = explode(PHP_EOL, wordwrap($error['message'] . ' (' . $error['source'] . ')', 70));
					foreach ($message as $i => $line) {
						if ($i === 0) {
							$report .= ' OUTDATED ';
						} else {
							$report .= '          ';
						}
						$report .= '| ' . $line . PHP_EOL;
					}
				}

				$report .= $separator . PHP_EOL;
			}
		}

		return $report . PHP_EOL;
	}


	private static function relativePath(string $path): string
	{
		if (self::$prefixPathDetection === NULL) {
			$reflection = new \ReflectionClass(PHP_CodeSniffer\Config::class);
			$filename = $reflection->getFileName();
			assert(is_string($filename));
			self::$prefixPathDetection = $filename;
		}

		$cnt = strlen($path);
		for ($i = 0; $i < $cnt; $i++) {
			if (substr($path, $i, 1) !== substr(self::$prefixPathDetection, $i, 1)) {
				$path = substr($path, $i);
				break;
			}
		}

		return $path;
	}

}
