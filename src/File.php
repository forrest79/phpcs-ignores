<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use PHP_CodeSniffer;

final class File extends PHP_CodeSniffer\Files\File
{
	private int $ignoredErrorCount = 0;

	private int $ignoredWarningCount = 0;

	private int $ignoredFixableCount = 0;


	public function setErrors(array $errors): void
	{
		$this->errors = $errors;
	}


	public function setIgnoredErrorCount(int $count): void
	{
		$this->ignoredErrorCount = $count;
	}


	public function getErrorCount(): int
	{
		return parent::getErrorCount() - $this->ignoredErrorCount;
	}


	public function setWarnings(array $warnings): void
	{
		$this->warnings = $warnings;
	}


	public function setIgnoredWarningCount(int $count): void
	{
		$this->ignoredWarningCount = $count;
	}


	public function getWarningCount(): int
	{
		return parent::getWarningCount() - $this->ignoredWarningCount;
	}


	public function setIgnoredFixableCount(int $count): void
	{
		$this->ignoredFixableCount = $count;
	}


	public function getFixableCount(): int
	{
		return parent::getFixableCount() - $this->ignoredFixableCount;
	}

}
