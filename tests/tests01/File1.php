<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores\Tests;

final class File1
{

	public function method1(bool $report): bool
	{
		return !$report;
	}

}
