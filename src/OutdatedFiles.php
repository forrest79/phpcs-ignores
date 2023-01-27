<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class OutdatedFiles
{
	private static self|NULL $instance = NULL;

	private const WAIT_FOR_ALL_PROCESSES_SECONDS = 30;

	private Ignores $ignores;

	private PHP_CodeSniffer\Config $config;

	private string $outdatedVirtualFile;

	private string|NULL $outdatedDataFile = NULL;


	public function __construct(Ignores $ignores, PHP_CodeSniffer\Config $config, string $outdatedVirtualFile)
	{
		$this->ignores = $ignores;
		$this->config = $config;
		$this->outdatedVirtualFile = $outdatedVirtualFile;

		$files = $config->files;
		$files[] = $outdatedVirtualFile; // this must be last file to check - it's file that perform check what ignored files was not matched
		$config->files = $files;

		if ($config->parallel === 1) {
			$this->outdatedDataFile = NULL;
		} else {
			$outdatedDataFile = tempnam(sys_get_temp_dir(), 'phpcs-ignores-outdated');
			if ($outdatedDataFile === FALSE) {
				throw new \RuntimeException('Can\'t create phpcs-ignores-outdated temp file.');
			}

			file_put_contents($outdatedDataFile, json_encode(['processCount' => $config->parallel, 'completedCount' => 0, 'remainingIgnoreErrors' => []]));
			$this->outdatedDataFile = $outdatedDataFile;
		}
	}


	public function __destruct()
	{
		if ($this->outdatedDataFile !== NULL) {
			if (!file_exists($this->outdatedDataFile)) {
				// detecting outdated is probably done (destruct of last process)
				return;
			}

			$lockFile = $this->getOutdatedDataFileLock();

			$handle = fopen($lockFile, 'c+');
			if ($handle === FALSE) {
				throw new \RuntimeException(sprintf('Unable to create an exclusive lock on file \'%s\'.', $lockFile));
			}

			if (!flock($handle, LOCK_EX)) {
				throw new \RuntimeException(sprintf('Unable to acquire an exclusive lock on file \'%s\'.', $lockFile));
			}

			$json = $this->loadOutdatedDataFile();
			$json['completedCount']++;

			// we know that one file is only in one process, so valid remaining ignore errors are these it's in all processes
			$json['remainingIgnoreErrors'] = $json['completedCount'] === 1
				? $this->ignores->getRemainingIgnoreErrors()
				: array_intersect_key($json['remainingIgnoreErrors'], $this->ignores->getRemainingIgnoreErrors());

			file_put_contents($this->outdatedDataFile, json_encode($json));

			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}


	public function getOutdatedVirtualFile(): string
	{
		return $this->outdatedVirtualFile;
	}


	/**
	 * @return array<array<string, mixed>>
	 */
	public function checkOutdatedFiles(): array
	{
		$outdatedFiles = [];

		$remainingOutdatedErrors = $this->ignores->getRemainingIgnoreErrors();
		if ($this->config->parallel > 1) {
			// wait to complete all processes
			$start = microtime(TRUE);
			do {
				try {
					$json = $this->loadOutdatedDataFile();
					if ($json['processCount'] === ($json['completedCount'] + 1)) {
						$remainingOutdatedErrors = array_intersect_key($json['remainingIgnoreErrors'], $remainingOutdatedErrors);
						break;
					}
				} catch (\JsonException) {
					// do nothing - file can be corrupted, because is updating right now (for reading we're not using lock)
				}

				usleep(50000); // 50ms

				if ((microtime(TRUE) - $start) > self::WAIT_FOR_ALL_PROCESSES_SECONDS) {
					throw new \RuntimeException(sprintf('Waiting time for complete all processes - %d seconds - exceeded.', self::WAIT_FOR_ALL_PROCESSES_SECONDS));
				}
			} while (TRUE);
		}

		foreach ($remainingOutdatedErrors as $path => $sniffs) {
			foreach ($sniffs as $sniff => $messageCounts) {
				assert(is_array($messageCounts));

				foreach (array_keys($messageCounts) as $message) {
					assert(is_string($message));

					$outdatedFiles[] = [
						// these errors are always not matched
						'message' => 'File: ' . PHP_EOL . $path . PHP_EOL . PHP_EOL . self::formatOutdatedMessage(
							0,
							0,
							$sniff,
							$message,
						),
						'source' => $sniff,
						'listener' => '',
						'severity' => 0,
						'fixable' => FALSE,
					];
				}
			}
		}

		if ($this->outdatedDataFile !== NULL) {
			@unlink($this->outdatedDataFile); // intentionally @ - file may not exists
			@unlink($this->getOutdatedDataFileLock()); // intentionally @ - file may not exists
			$this->outdatedDataFile = NULL;
		}

		return $outdatedFiles;
	}


	private function getOutdatedDataFileLock(): string
	{
		if ($this->outdatedDataFile === NULL) {
			throw new \RuntimeException('Property outdatedDataFile should not be NULL.');
		}

		return $this->outdatedDataFile . '.lock';
	}


	/**
	 * @return array{processCount: int, completedCount: int, remainingIgnoreErrors: array<array<string, mixed>>}
	 */
	private function loadOutdatedDataFile(): array
	{
		if ($this->outdatedDataFile === NULL) {
			throw new \RuntimeException('Property outdatedDataFile should not be NULL.');
		}

		$data = file_get_contents($this->outdatedDataFile);
		if ($data === FALSE) {
			throw new \RuntimeException('Can\'t load outdated data file.');
		}

		/** @phpstan-var array{processCount: int, completedCount: int, remainingIgnoreErrors: array<array<string, mixed>>} */
		return json_decode($data, NULL, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
	}


	public static function formatOutdatedMessage(
		int $realCount,
		int $expectedCount,
		string $sniff,
		string $message,
	): string
	{
		if ($realCount === 0) {
			return sprintf(
				'Ignored sniff \'%s\' with message \'%s\' was not matched in report.',
				$sniff,
				$message,
			);
		} else {
			return sprintf(
				'Ignored sniff \'%s\' with message \'%s\' is expected to occur %d times, but occurred only %s.',
				$sniff,
				$message,
				$expectedCount,
				$realCount === 1 ? '1 time' : ($realCount . ' times'),
			);
		}
	}


	public function setInstance(): static
	{
		if (self::$instance !== NULL) {
			throw new \RuntimeException('Instance can be set just once.');
		}

		self::$instance = $this;

		return $this;
	}


	public static function getInstance(): self|NULL
	{
		return self::$instance;
	}

}
