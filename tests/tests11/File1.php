<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores\Tests;

final class File1
{

	/**
	 * @param bool $report
	 * @return bool
	 */
	public function method1($report)
	{
		return !$report;
	}

}
