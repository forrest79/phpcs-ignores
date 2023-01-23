<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class Reporter extends PHP_CodeSniffer\Reporter
{
	private const WAIT_FOR_ALL_PROCESSES_SECONDS = 60;

	private Ignores $ignores;

	private string|NULL $outdatedVirtualFile;

	private string|NULL $outdatedDataFile = NULL;

	private array $originalIgnoreErrors = [];


    public function __construct(Ignores $ignores, PHP_CodeSniffer\Config $config, string|NULL $outdatedVirtualFile)
    {
    	parent::__construct($config);
    	$this->ignores = $ignores;
    	$this->outdatedVirtualFile = $outdatedVirtualFile;

		if ($outdatedVirtualFile !== NULL) {
			$this->originalIgnoreErrors = $ignores->getRemainingIgnoreErrors();

			if ($config->parallel === 1) {
				$this->outdatedDataFile = NULL;
			} else {
				$outdatedDataFile = tempnam(sys_get_temp_dir(), 'phpcsignores-');
				file_put_contents($outdatedDataFile, json_encode(['processCount' => $config->parallel, 'completedCount' => 0, 'remainingIgnoreErrors' => []]));
				$this->outdatedDataFile = $outdatedDataFile;
			}
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


	public function cacheFileReport(PHP_CodeSniffer\Files\File $phpcsFile): void
    {
		$newPhpCsFile = unserialize(str_replace('O:31:"PHP_CodeSniffer\Files\LocalFile"', 'O:27:"Forrest79\PhpCsIgnores\File"', serialize($phpcsFile)));
		assert($newPhpCsFile instanceof File);

		parent::cacheFileReport(
			$phpcsFile->getFilename() === $this->outdatedVirtualFile
				? $this->checkOutdatedIgnoreErrors($newPhpCsFile)
				: $this->checkIgnoredErrors($phpcsFile, $newPhpCsFile),
		);
	}


	private function checkIgnoredErrors(PHP_CodeSniffer\Files\File $originalPhpcsFile, File $newPhpCsFile): File
	{
		$errorCount = 0;
		$warningCount = 0;
		$fixableCount = 0;

        if ($originalPhpcsFile->getErrorCount() !== 0) {
			$errors = [];
			foreach ($originalPhpcsFile->getErrors() as $line => $lineErrors) {
				foreach ($lineErrors as $column => $colErrors) {
					$newErrors = [];
					foreach ($colErrors as $data) {
						if ($this->ignores->isIgnored($newPhpCsFile->getFilename(), $data['source'], $data['message'])) {
							$errorCount++;
							if ($data['fixable']) {
								$fixableCount++;
							}
							continue;
						}

						$newErrors[] = $data;
					}

					if ($newErrors !== []) {
						$errors[$line][$column] = $newErrors;
					}
				}

				if (isset($errors[$line])) {
					ksort($errors[$line]);
				}
			}

			$newPhpCsFile->setErrors($errors);
			$newPhpCsFile->setIgnoredErrorCount($errorCount);
		}

        if ($originalPhpcsFile->getWarningCount() !== 0) {
			$warnings = [];
			foreach ($originalPhpcsFile->getWarnings() as $line => $lineWarnings) {
				foreach ($lineWarnings as $column => $colWarnings) {
					$newWarnings = [];
					foreach ($colWarnings as $data) {
						if ($this->ignores->isIgnored($newPhpCsFile->getFilename(), $data['source'], $data['message'])) {
							$warningCount++;
							if ($data['fixable']) {
								$fixableCount++;
							}
							continue;
						}

						$newWarnings[] = $data;
					}

					if ($newWarnings !== []) {
						$warnings[$line][$column] = $newWarnings;
					}
				}

				if (isset($warnings[$line])) {
					ksort($warnings[$line]);
				}
			}

			$newPhpCsFile->setWarnings($warnings);
			$newPhpCsFile->setIgnoredWarningCount($warningCount);
		}

		$newPhpCsFile->setIgnoredFixableCount($fixableCount);

		return $newPhpCsFile;
	}


	private function checkOutdatedIgnoreErrors(File $newPhpCsFile): File
	{
			$outdatedErrorCount = 0;
			$outdatedErrors = [];

			if ($this->config->parallel === 1) {
				$remainingOutdatedErrors = $this->ignores->getRemainingIgnoreErrors();
			} else {
				// wait to complete all processes
				$start = microtime(TRUE);
				do {
					$json = $this->loadOutdatedDataFile();
					if ($json['processCount'] === ($json['completedCount'] + 1)) {
						$remainingOutdatedErrors = array_intersect_key($json['remainingIgnoreErrors'], $this->ignores->getRemainingIgnoreErrors());
						break;
					}

					usleep(10000); // 10ms

					if ((microtime(TRUE) - $start) > self::WAIT_FOR_ALL_PROCESSES_SECONDS) {
						throw new \RuntimeException(sprintf('Waiting time for complete all processes - %d seconds - exceeded.', self::WAIT_FOR_ALL_PROCESSES_SECONDS));
					}
				} while (TRUE);
			}

			foreach ($remainingOutdatedErrors as $path => $sniffs) {
				foreach ($sniffs as $sniff => $messageCounts) {
					foreach ($messageCounts as $message => $count) {
						$expectedCount = $this->originalIgnoreErrors[$path][$sniff][$message];
						$realCount = $expectedCount - $count;
						if ($realCount === 0) {
							$outdatedMessage = sprintf(
								'Ignored sniff \'%s\' with message \'%s\' was not matched in report.',
								$sniff,
								$message,
							);
						} else {
							$outdatedMessage = sprintf(
								'Ignored sniff \'%s\' with message \'%s\' is expected to occur %d times, but occurred only %s.',
								$sniff,
								$message,
								$expectedCount,
								$realCount === 1 ? '1 time' : ($realCount . ' times'),
							);
						}

						$outdatedErrorCount++;
						$outdatedErrors[] = [
							'message' => '=====' . PHP_EOL . $path . PHP_EOL . '-----' . PHP_EOL . PHP_EOL . $outdatedMessage,
							'source' => $sniff,
							'listener' => '',
							'severity' => 0,
							'fixable' => FALSE,
						];
					}
				}
			}

			$newPhpCsFile->path = 'OUTDATED IGNORE ERRORS';

			// reset errors and fixable - outdated will be warnings
			$newPhpCsFile->setIgnoredErrorCount($newPhpCsFile->getErrorCount());
			$newPhpCsFile->setIgnoredFixableCount($newPhpCsFile->getFixableCount());
			$newPhpCsFile->setErrors([]);

			$newPhpCsFile->setIgnoredWarningCount($newPhpCsFile->getWarningCount() - $outdatedErrorCount);
			$newPhpCsFile->setWarnings($outdatedErrors !== [] ? [1 => [1 => $outdatedErrors]]: $outdatedErrors); // 1/1 = line/col

			@unlink($this->outdatedVirtualFile); // intentionally @ - file may not exists
			$this->outdatedVirtualFile = NULL;

			if ($this->outdatedDataFile !== NULL) {
				@unlink($this->outdatedDataFile); // intentionally @ - file may not exists
				@unlink($this->getOutdatedDataFileLock()); // intentionally @ - file may not exists
				$this->outdatedDataFile = NULL;
			}

			return $newPhpCsFile;
	}


	private function getOutdatedDataFileLock(): string
	{
		if ($this->outdatedDataFile === NULL) {
			throw new \RuntimeException('outdatedDataFile should not be NULL');
		}

		return $this->outdatedDataFile . '.lock';
	}


	private function loadOutdatedDataFile(): array
	{
		$data = file_get_contents($this->outdatedDataFile);
		return json_decode($data, NULL, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
	}

}
