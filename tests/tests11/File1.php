<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores\Tests;

final class File1
{

	/**
	 * @param bool $report
	 */
	public function method1($report): bool
	{
		return !$report;
	}

}
