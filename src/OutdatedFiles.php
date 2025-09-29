<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class OutdatedFiles
{
	private static self|null $instance = null;

	private const WAIT_FOR_ALL_PROCESSES_SECONDS = 30;

	private Ignores $ignores;

	private string $outdatedVirtualFile;

	private int $parentPID;

	private string|null $outdatedDataFile = null;


	public function __construct(Ignores $ignores, PHP_CodeSniffer\Config $config, string $outdatedVirtualFile)
	{
		$this->ignores = $ignores;
		$this->outdatedVirtualFile = $outdatedVirtualFile;

		$files = $config->files;
		$files[] = $outdatedVirtualFile; // this must be last file to check - it's file that perform check what ignored files was not matched
		$config->files = $files;

		if ($config->parallel === 1) {
			$this->outdatedDataFile = null;
		} else {
			$parentPID = getmypid();
			if ($parentPID === false) {
				throw new \RuntimeException('Can\'t get actual PID.');
			}
			$this->parentPID = $parentPID;

			$outdatedDataFile = tempnam(sys_get_temp_dir(), 'phpcs-ignores-outdated');
			if ($outdatedDataFile === false) {
				throw new \RuntimeException('Can\'t create phpcs-ignores-outdated temp file.');
			}

			file_put_contents($outdatedDataFile, json_encode(null));
			$this->outdatedDataFile = $outdatedDataFile;
		}
	}


	public function __destruct()
	{
		if ($this->outdatedDataFile !== null) {
			if (!file_exists($this->outdatedDataFile)) {
				// detecting outdated is probably done (destruct of last process)
				return;
			}

			$lockFile = $this->getOutdatedDataFileLock();

			$handle = fopen($lockFile, 'c+');
			if ($handle === false) {
				throw new \RuntimeException(sprintf('Unable to create an exclusive lock on file \'%s\'.', $lockFile));
			}

			if (!flock($handle, LOCK_EX)) {
				throw new \RuntimeException(sprintf('Unable to acquire an exclusive lock on file \'%s\'.', $lockFile));
			}

			$json = $this->loadOutdatedDataFile();

			// we know that one checked file is only in one process, so valid remaining ignore errors are these it's in all processes (array_intersect_key)
			$remainingIgnoreErrors = $json === null
				? $this->ignores->getRemainingIgnoreErrors() // for first process we need to fill array, so in next processes we can make intersect (array_intersect_key)
				: array_intersect_key($json, $this->ignores->getRemainingIgnoreErrors());

			file_put_contents($this->outdatedDataFile, json_encode($remainingIgnoreErrors));

			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}


	public function getOutdatedVirtualFile(): string
	{
		return $this->outdatedVirtualFile;
	}


	/**
	 * @return list<array<string, mixed>>
	 */
	public function checkOutdatedFiles(): array
	{
		$outdatedFiles = [];

		$remainingOutdatedErrors = $this->ignores->getRemainingIgnoreErrors();

		if ($this->outdatedDataFile !== null) { // only for parallel
			// wait to complete all processes
			$start = microtime(true);
			do {
				try {
					if (!$this->isSomeChildRunning()) {
						$json = $this->loadOutdatedDataFile();
						if ($json === null) {
							throw new \RuntimeException('There should be some data in json file, but there is initial null.');
						}

						$remainingOutdatedErrors = array_intersect_key($json, $remainingOutdatedErrors);
						break;
					}
				} catch (\JsonException) {
					// do nothing - file can be corrupted, because is updating right now (for reading we're not using lock)
				}

				usleep(50000); // 50ms

				$pid = getmypid();
				assert($pid !== false);

				if ((microtime(true) - $start) > (self::WAIT_FOR_ALL_PROCESSES_SECONDS * 1000)) {
					throw new \RuntimeException(sprintf(
						"Waiting time for complete all processes (%d seconds) exceeded.\n\nDEBUG INFO - Parent PID: %d, Current child PID: %d\n\nRunning processes: %s",
						self::WAIT_FOR_ALL_PROCESSES_SECONDS,
						$this->parentPID,
						$pid,
						implode(PHP_EOL, $this->getRunningChildProcesses()),
					));
				}
			} while (true);
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
						'fixable' => false,
					];
				}
			}
		}

		if ($this->outdatedDataFile !== null) {
			@unlink($this->outdatedDataFile); // intentionally @ - file may not exists
			@unlink($this->getOutdatedDataFileLock()); // intentionally @ - file may not exists
			$this->outdatedDataFile = null;
		}

		return $outdatedFiles;
	}


	private function isSomeChildRunning(): bool
	{
		$processCount = 0;
		foreach ($this->getRunningChildProcesses() as $pidLine) {
			if (preg_match('#^(?<pid>[0-9]+)#', trim($pidLine), $matches) !== 1) {
				throw new \RuntimeException(sprintf('Could not get child PID number from "%s" line.', $pidLine));
			}

			$pid = (int) $matches['pid'];
			if ($pid === getmypid()) {
				continue;
			}

			$processCount++;
		}

		return $processCount > 0;
	}


	/**
	 * @return list<string>
	 */
	private function getRunningChildProcesses(): array
	{
		exec(sprintf('ps --ppid %d | tail -n +2', $this->parentPID), $output, $exitCode);
		if ($exitCode !== 0) {
			throw new \RuntimeException('Could not determine child processes on your system.');
		}

		return $output;
	}


	private function getOutdatedDataFileLock(): string
	{
		if ($this->outdatedDataFile === null) {
			throw new \RuntimeException('Property outdatedDataFile should not be null.');
		}

		return $this->outdatedDataFile . '.lock';
	}


	/**
	 * @return array<array<string, mixed>>|null
	 */
	private function loadOutdatedDataFile(): array|null
	{
		if ($this->outdatedDataFile === null) {
			throw new \RuntimeException('Property outdatedDataFile should not be null.');
		}

		$data = file_get_contents($this->outdatedDataFile);
		if ($data === false) {
			throw new \RuntimeException('Can\'t load outdated data file.');
		}

		/** @phpstan-var array<array<string, mixed>>|null */
		return json_decode($data, null, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
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
		if (self::$instance !== null) {
			throw new \RuntimeException('Instance can be set just once.');
		}

		self::$instance = $this;

		return $this;
	}


	public static function getInstance(): self|null
	{
		return self::$instance;
	}

}
