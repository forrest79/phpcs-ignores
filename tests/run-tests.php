<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores\Tests;

final class TestsRunner
{
	private const PHPCS_BIN = '../vendor/bin/phpcs';
	private const PHPCBF_BIN = '../vendor/bin/phpcbf';

	private const BOOTSTRAP_PARAM = '--bootstrap=../src/bootstrap.php';
	private const BOOTSTRAP_OUTDATED_PARAM = '--bootstrap=../src/bootstrap-outdated.php';
	private const BOOTSTRAP_HIDE_OUTDATED_WARNINGS_PARAM = '--bootstrap=../src/bootstrap-hide-outdated-warnings.php';
	private const PARALLEL_PARAM = '--parallel=2';
	private const CACHE_PARAM = '--cache=temp/cache.json';

	private const EXIT_CODE_OK = 0;
	private const EXIT_CODE_ERROR = 1;
	private const EXIT_CODE_FIXABLE_ERROR = 2;

	/**
	 * [$title, $parameters, $cleanCache]
	 */
	private const DEFAULT_TEST_CASES = [
		['without parallel, without cache', [], FALSE],
		['with parallel, without cache', [self::PARALLEL_PARAM], FALSE],
		['without parallel, with cache', [self::CACHE_PARAM], FALSE],
		['without parallel, with cache again', [self::CACHE_PARAM], TRUE],
		['with parallel, with cache', [self::PARALLEL_PARAM, self::CACHE_PARAM], FALSE],
		['with parallel, with cache again', [self::PARALLEL_PARAM, self::CACHE_PARAM], TRUE],
	];


	public function run(): bool
	{
		$success = $this->runTests(
			'Test correct phpcs.xml (no ignores, expecting no errors)',
			fn (): bool => $this->tests1(),
		);

		$success = $success && $this->runTests(
			'Test correct phpcs.xml (no ignores, expecting one error)',
			fn (): bool => $this->tests2(),
		);

		$success = $success && $this->runTests(
			'Test ignoring errors (expecting no error)',
			fn (): bool => $this->tests3(),
		);

		$success = $success && $this->runTests(
			'Test ignoring errors (expecting one error)',
			fn (): bool => $this->tests4(),
		);

		$success = $success && $this->runTests(
			'Test ignoring errors (expecting no error, one outdated error)',
			fn (): bool => $this->tests5(),
		);

		$success = $success && $this->runTests(
			'Test ignoring errors with hiding outdated warnings (expecting no error, one outdated warning and one outdated file should be ignored)',
			fn (): bool => $this->tests6(),
		);

		$success = $success && $this->runTests(
			'Test ignoring errors (expecting one error and expecting outdated error)',
			fn (): bool => $this->tests7(),
		);

		$success = $success && $this->runTests(
			'Test outdated files (expecting no outdated file)',
			fn (): bool => $this->tests8(),
		);

		$success = $success && $this->runTests(
			'Test outdated files (expecting one outdated file)',
			fn (): bool => $this->tests9(),
		);

		$success = $success && $this->runTests(
			'Test fix errors (expecting no fixed error)',
			fn (): bool => $this->tests10(),
		);

		$success = $success && $this->runTests(
			'Test fix errors (expecting one fixed error)',
			fn (): bool => $this->tests11(),
		);

		$success = $success && $this->runTests(
			'Test generate baseline',
			fn (): bool => $this->tests12(),
		);

		return $success;
	}


	private function runTests(string $title, callable $tests): bool
	{
		self::cleanCache();

		echo '> ' . $title . ':' . PHP_EOL;
		echo '--' . str_repeat('-', strlen($title)) . '-' . PHP_EOL;
		$success = $tests();
		echo ($success ? ' -> OK' : 'FAIL') . PHP_EOL . PHP_EOL;
		return $success;
	}


	private function tests1(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests01', NULL, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_OK) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 0) {
				echo 'there are some errors or warnings' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests2(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests02', NULL, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 1) || $data['totals']['warnings'] !== 0) {
				echo 'there should be just one error and no warning' . PHP_EOL;
				return FALSE;
			}

			$file2 = __DIR__ . '/tests02/File2.php';
			if ($data['files'][$file2]['errors'] !== 1) {
				echo 'there is missing error for file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			if ($data['files'][$file2]['messages'][0]['source'] !== 'SlevomatCodingStandard.Files.TypeNameMatchesFileName.NoMatchBetweenTypeNameAndFileName') {
				echo 'there is bad sniff for error in file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests3(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests03', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_OK) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 0) {
				echo 'there are some errors or warnings' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests4(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests04', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_FIXABLE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 1) || $data['totals']['warnings'] !== 0) {
				echo 'there should be just one error and no warning' . PHP_EOL;
				return FALSE;
			}

			$file2 = __DIR__ . '/tests04/File2.php';
			if ($data['files'][$file2]['errors'] !== 1) {
				echo 'there is missing error for file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			if ($data['files'][$file2]['messages'][0]['source'] !== 'SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon') {
				echo 'there is bad sniff for error in file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests5(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests05', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 1) {
				echo 'there should be no error and just one warning' . PHP_EOL;
				return FALSE;
			}

			$file2 = __DIR__ . '/tests05/File2.php';
			if ($data['files'][$file2]['warnings'] !== 1) {
				echo 'there is missing warning for file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			if ($data['files'][$file2]['messages'][0]['message'] !== 'Ignored sniff \'SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon\' with message \'There must be no whitespace between closing parenthesis and return type colon.\' is expected to occur 2 times, but occurred only 1 time.') {
				echo 'there is bad message for warning in file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests6(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests06', self::BOOTSTRAP_HIDE_OUTDATED_WARNINGS_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_OK) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 0) {
				echo 'there should be no error and no warning' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests7(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests07', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_FIXABLE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 1) || $data['totals']['warnings'] !== 1) {
				echo 'there should be no error and just one warning' . PHP_EOL;
				return FALSE;
			}

			$file1 = __DIR__ . '/tests07/File1.php';
			if ($data['files'][$file1]['errors'] !== 1) {
				echo 'there is missing error for file ' . $file1 . PHP_EOL;
				return FALSE;
			}

			if ($data['files'][$file1]['messages'][0]['source'] !== 'SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon') {
				echo 'there is bad sniff for warning in file ' . $file1 . PHP_EOL;
				return FALSE;
			}

			$file2 = __DIR__ . '/tests07/File2.php';
			if ($data['files'][$file2]['warnings'] !== 1) {
				echo 'there is missing warning for file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			if ($data['files'][$file2]['messages'][0]['message'] !== 'Ignored sniff \'SlevomatCodingStandard.PHP.DisallowReference.DisallowedPassingByReference\' with message \'Passing by reference is disallowed.\' was not matched in report.') {
				echo 'there is bad message for warning in file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests8(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests08', self::BOOTSTRAP_OUTDATED_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_OK) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 0) {
				echo 'there are some errors or warnings' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests9(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests09', self::BOOTSTRAP_OUTDATED_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$data = self::parseJson($exec['output']);
			if (($data['totals']['errors'] !== 0) || $data['totals']['warnings'] !== 1) {
				echo 'there should be no error and just one warning' . PHP_EOL;
				return FALSE;
			}

			$outdatedFile = '/outdated/ignored-files';
			if ($data['files'][$outdatedFile]['warnings'] !== 1) {
				echo 'there is missing warning for outdated file' . PHP_EOL;
				return FALSE;
			}

			$file3 = __DIR__ . '/tests09/File3.php';
			if ($data['files'][$outdatedFile]['messages'][0]['message'] !== 'File: \n' . $file3 . '\n\nIgnored sniff \'SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon\' with message \'There must be no whitespace between closing parenthesis and return type colon.\' was not matched in report.') {
				echo 'there is bad message for warning in outdated file' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests10(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCBF_BIN, 'tests10', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_OK) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			if (!str_contains($exec['output'], 'No violations were found')) {
				echo 'there is probably some errors to fix' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests11(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCBF_BIN, 'tests11', self::BOOTSTRAP_PARAM, $params);
			if ($exec['exitCode'] !== self::EXIT_CODE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$file2 = __DIR__ . '/tests11/File2.php';
			if (preg_match('#\/tests\/tests11\/File2.php[ ]+1      0#', $exec['output']) === 0) {
				echo 'there is probably bad info about fixed and remaining errors in file ' . $file2 . PHP_EOL;
				return FALSE;
			}

			if (!str_contains($exec['output'], 'A TOTAL OF 1 ERROR WERE FIXED IN 1 FILE')) {
				echo 'there is probably bad info about fixed errors' . PHP_EOL;
				return FALSE;
			}

			if (file_get_contents($file2) !== file_get_contents($file2 . '.fixed')) {
				echo 'file ' . $file2 . ' is badly fixed' . PHP_EOL;
				return FALSE;
			}

			copy($file2 . '.orig', $file2);

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	private function tests12(): bool
	{
		foreach (self::DEFAULT_TEST_CASES as $test) {
			[$title, $params, $cleanCache] = $test;

			echo sprintf('   - %s: ', $title);

			$exec = $this->exec(self::PHPCS_BIN, 'tests12', self::BOOTSTRAP_PARAM, $params, '\\\\Forrest79\\\\PhpCsIgnores\\\\BaselineReport');
			if ($exec['exitCode'] !== self::EXIT_CODE_FIXABLE_ERROR) {
				echo 'PHPCS exited unexpectedly' . PHP_EOL;
				return FALSE;
			}

			$expected = <<<'NEON'
ignoreErrors:
	-
		sniff: SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
		message: 'Method \Forrest79\PhpCsIgnores\Tests\File1::method1() does not have native return type hint for its return value but it should be possible to add it based on @return annotation "bool".'
		count: 1
		path: tests12/File1.php

	-
		sniff: SlevomatCodingStandard.TypeHints.ReturnTypeHintSpacing.WhitespaceBeforeColon
		message: There must be no whitespace between closing parenthesis and return type colon.
		count: 2
		path: tests12/File2.php
NEON;

			if (trim($exec['output']) !== trim($expected)) {
				echo 'generated ignore errors are wrong' . PHP_EOL;
				return FALSE;
			}

			echo 'OK' . PHP_EOL;

			if ($cleanCache) {
				self::cleanCache();
			}
		}

		return TRUE;
	}


	/**
	 * @param array<string> $parameters
	 * @return array{exitCode: int, output: string}
	 */
	private function exec(
		string $bin,
		string $dir,
		string|NULL $boostrap,
		array $parameters = [],
		string $report = 'json',
	): array
	{
		chdir(__DIR__);

		if ($boostrap !== NULL) {
			$parameters[] = $boostrap;
		}

		$command = sprintf('%s --standard=%s/phpcs.xml --report=%s %s -s %s', $bin, $dir, $report, implode(' ', $parameters), $dir);

		if (exec($command, $output, $exitCode) === FALSE) {
			throw new \RuntimeException('Can\'t run PHPCS.');
		}

		return ['exitCode' => $exitCode, 'output' => implode(PHP_EOL, $output)];
	}


	private static function cleanCache(): void
	{
		@unlink(__DIR__ . '/temp/cache.json'); // intentionally @ - file may not exist
	}


	/**
	 * @return array<string, mixed>
	 */
	private static function parseJson(string $json): array
	{
		/** @phpstan-var array<string, mixed> */
		return json_decode($json, NULL, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
	}

}

exit((new TestsRunner())->run() ? 0 : 1);
