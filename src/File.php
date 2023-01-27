<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class File extends PHP_CodeSniffer\Files\LocalFile
{
	/** @var array<string, array<string, int>> */
	private array $originalIgnoreErrors;

	/** @var array<string, array<string, int>> */
	private array $ignoreErrors;

	/** @var array<string, array<string, int>> */
	private array $fixableIgnoreErrors;

	private int $ignoredErrorCount = 0;

	private int $ignoredWarningCount = 0;

	private int $ignoredFixableCount = 0;


	/**
	 * @param string $path
	 */
	public function __construct($path, PHP_CodeSniffer\Ruleset $ruleset, PHP_CodeSniffer\Config $config)
	{
		parent::__construct($path, $ruleset, $config);

		$this->originalIgnoreErrors = Ignores::getInstance()->getRemainingIgnoreErrorsForFileAndClean($path);
		$this->ignoreErrors = $this->originalIgnoreErrors;
	}


	public function process(): void
	{
		// PHPCBF can run process multiple time and every time we want to ignore the same errors...
		$this->fixableIgnoreErrors = $this->originalIgnoreErrors;

		$outdatedFiles = OutdatedFiles::getInstance();
		if (($outdatedFiles !== NULL) && ($this->getFilename() === $outdatedFiles->getOutdatedVirtualFile())) {
			$this->checkOutdatedFiles($outdatedFiles);
		} else {
			parent::process(); // This will provide full cache - we want it!
			$this->checkIgnoredErrors(); // And now we can remove ignored errors from this file.
		}
	}


	/**
	 * @param bool $error
	 * @param string $message
	 * @param int $line
	 * @param int $column
	 * @param string $code
	 * @param array<mixed> $data
	 * @param int $severity
	 * @param bool $fixable
	 */
	protected function addMessage($error, $message, $line, $column, $code, $data, $severity, $fixable): bool
	{
		// This condition is for PHPCBF - when error is ignored and fixable, we want to ignore it immediately.
		// For PHPCS we want to process all errors in a classic way - because than we have complete cache a that we want.
		if ($this->fixer->enabled) {
			// Work out which sniff generated the message. (Copied and simplified from vendor/squizlabs/php_codesniffer/src/Files/File.php:844)
			$parts = explode('.', $code);
			if ($parts[0] === 'Internal') {
				$sniffCode = $code;
			} else {
				if ($parts[0] !== $code) {
					$sniffCode = $code;
				} else {
					$sniffCode = PHP_CodeSniffer\Util\Common::getSniffCode($this->activeListener) . '.' . $code;
				}
			}

			// (Copied and simplified from vendor/squizlabs/php_codesniffer/src/Files/File.php:1056)
			if ($data !== []) {
				$message = vsprintf($message, $data);
			}

			if ($this->isFixableIgnored($sniffCode, $message)) {
				return FALSE;
			}
		}

		return parent::addMessage($error, $message, $line, $column, $code, $data, $severity, $fixable);
	}


	private function checkIgnoredErrors(): void
	{
		$ignoredErrorCount = 0;
		$ignoredWarningCount = 0;
		$ignoredFixableCount = 0;

		if ($this->getErrorCount() !== 0) {
			$errors = [];
			foreach ($this->getErrors() as $line => $lineErrors) {
				foreach ($lineErrors as $column => $colErrors) {
					$newErrors = [];
					foreach ($colErrors as $data) {
						if ($this->isIgnored($data['source'], $data['message'])) {
							$ignoredErrorCount++;
							if ($data['fixable']) {
								$ignoredFixableCount++;
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

			$this->setErrors($errors);
			$this->setIgnoredErrorCount($ignoredErrorCount);
		}

		// outdated are add as warning, so warnings and warning count is set at the end
		$warnings = [];
		if ($this->getWarningCount() !== 0) {
			foreach ($this->getWarnings() as $line => $lineWarnings) {
				foreach ($lineWarnings as $column => $colWarnings) {
					$newWarnings = [];
					foreach ($colWarnings as $data) {
						if ($this->isIgnored($data['source'], $data['message'])) {
							$ignoredWarningCount++;
							if ($data['fixable']) {
								$ignoredFixableCount++;
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
		}

		$this->setIgnoredFixableCount($ignoredFixableCount);

		$outdatedErrorCount = 0;
		$outdatedErrors = [];
		foreach ($this->ignoreErrors as $sniff => $messageCounts) {
			foreach ($messageCounts as $message => $count) {
				$expectedCount = $this->originalIgnoreErrors[$sniff][$message];
				$realCount = $expectedCount - $count;

				$outdatedErrors[] = [
					'message' => OutdatedFiles::formatOutdatedMessage(
						$realCount,
						$expectedCount,
						$sniff,
						$message,
					),
					'source' => $sniff,
					'listener' => '',
					'severity' => 0,
					'fixable' => FALSE,
				];

				$outdatedErrorCount++;
			}
		}

		if ($outdatedErrorCount > 0) {
			$warnings[1][1] = $outdatedErrors; // 1/1 = line/col
		}

		$this->setWarnings($warnings);
		$this->setIgnoredWarningCount($ignoredWarningCount - $outdatedErrorCount);
	}


	private function isIgnored(string $sniff, string $message): bool
	{
		if (isset($this->ignoreErrors[$sniff][$message])) {
			$this->ignoreErrors[$sniff][$message]--;
			if ($this->ignoreErrors[$sniff][$message] === 0) {
				unset($this->ignoreErrors[$sniff][$message]);
			}

			if ($this->ignoreErrors[$sniff] === []) {
				unset($this->ignoreErrors[$sniff]);
			}

			return TRUE;
		}

		return FALSE;
	}


	private function isFixableIgnored(string $sniff, string $message): bool
	{
		if (isset($this->fixableIgnoreErrors[$sniff][$message])) {
			$this->fixableIgnoreErrors[$sniff][$message]--;
			if ($this->fixableIgnoreErrors[$sniff][$message] === 0) {
				unset($this->fixableIgnoreErrors[$sniff][$message]);
			}

			return TRUE;
		}

		return FALSE;
	}


	private function checkOutdatedFiles(OutdatedFiles $outdatedFiles): void
	{
		$this->path = '/outdated/ignored-files';

		$outdatedFiles = $outdatedFiles->checkOutdatedFiles();
		$this->setIgnoredWarningCount(-1 * count($outdatedFiles));
		$this->setWarnings($outdatedFiles !== [] ? [1 => [1 => $outdatedFiles]] : []); // 1/1 = line/col
	}


	/**
	 * @param array<int, array<int, list<array<string, mixed>>>> $errors
	 */
	private function setErrors(array $errors): void
	{
		$this->errors = $errors;
	}


	private function setIgnoredErrorCount(int $count): void
	{
		$this->ignoredErrorCount = $count;
	}


	public function getErrorCount(): int
	{
		return parent::getErrorCount() - $this->ignoredErrorCount;
	}


	/**
	 * @param array<int, array<int, list<array<string, mixed>>>> $warnings
	 */
	private function setWarnings(array $warnings): void
	{
		$this->warnings = $warnings;
	}


	private function setIgnoredWarningCount(int $count): void
	{
		$this->ignoredWarningCount = $count;
	}


	public function getWarningCount(): int
	{
		return parent::getWarningCount() - $this->ignoredWarningCount;
	}


	private function setIgnoredFixableCount(int $count): void
	{
		$this->ignoredFixableCount = $count;
	}


	public function getFixableCount(): int
	{
		return parent::getFixableCount() - $this->ignoredFixableCount;
	}

}
