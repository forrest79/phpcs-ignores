<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use Nette\Neon\Neon;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Reports\Report;

final class BaselineReport implements Report
{
	private string $cwd;

	private int $cwdLength;


	public function __construct()
	{
		$cwd = getcwd();
		if ($cwd === false) {
			throw new \RuntimeException('Can\'t get current directory.');
		}

		$this->cwd = $cwd;
		$this->cwdLength = strlen($cwd);
	}


	/**
	 * @param array<mixed> $report
	 * @param bool $showSources
	 * @param int $width
	 */
	public function generateFileReport($report, File $phpcsFile, $showSources = false, $width = 80): bool
	{
		assert(is_int($report['errors']) && is_int($report['warnings']));

		if ($report['errors'] === 0 && $report['warnings'] === 0) {
			return false;
		}

		assert(is_string($report['filename']) && is_array($report['messages']));

		$filename = $report['filename'];

		// use relative path to current working directory
		if (str_starts_with($filename, $this->cwd)) {
			$filename = substr($filename, $this->cwdLength + 1);
		}

		$filename = str_replace('\\', '\\\\', $filename);
		$filename = str_replace('"', '\"', $filename);
		$filename = str_replace('/', '\/', $filename);

		echo '"' . $filename . '":[';

		$messages = '';
		foreach ($report['messages'] as $lineErrors) {
			assert(is_array($lineErrors));
			foreach ($lineErrors as $colErrors) {
				assert(is_array($colErrors));
				foreach ($colErrors as $error) {
					assert(is_array($error) && is_string($error['message']) && is_string($error['source']));

					$message = str_replace("\n", '\n', $error['message']);
					$message = str_replace("\r", '\r', $message);
					$message = str_replace("\t", '\t', $message);

					$messages .= json_encode([
						'sniff' => $error['source'],
						'message' => $message,
					]) . ',';
				}
			}
		}

		echo rtrim($messages, ',') . '],';

		return true;
	}


	/**
	 * @inheritDoc
	 */
	public function generate(
		$cachedData,
		$totalFiles,
		$totalErrors,
		$totalWarnings,
		$totalFixable,
		$showSources = false,
		$width = 80,
		$interactive = false,
		$toScreen = true,
	): void
	{
		$json = '{"files":{' . rtrim($cachedData, ',') . '}}' . PHP_EOL;
		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		assert(is_array($data));

		$ignoreErrors = [];
		if (is_array($data['files'])) {
			foreach ($data['files'] as $path => $errors) {
				assert(is_array($errors));
				foreach ($errors as $error) {
					assert(is_array($error) && is_string($error['sniff']) && $error['message']);

					$sniff = $error['sniff'];
					$message = $error['message'];
					assert(is_string($message));
					$ignoreErrors[$path][$sniff][$message] = ($ignoreErrors[$path][$sniff][$message] ?? 0) + 1;
				}
			}
		}

		$finalIgnoreErrors = [];
		ksort($ignoreErrors);
		foreach ($ignoreErrors as $path => $sniffs) {
			ksort($sniffs);
			foreach ($sniffs as $sniff => $messages) {
				ksort($messages);
				foreach ($messages as $message => $count) {
					$finalIgnoreErrors[] = [
						'sniff' => $sniff,
						'message' => $message,
						'count' => $count,
						'path' => $path,
					];
				}
			}
		}

		echo Neon::encode(['ignoreErrors' => $finalIgnoreErrors], true);
	}

}
