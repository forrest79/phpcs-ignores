<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

use Nette\Neon\Neon;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Reports\Report;

final class BaselineReport implements Report
{

	/**
	 * @param File $phpcsFile
	 * @param array<mixed> $report
	 * @param bool $showSources
	 * @param int $width
	 */
	public function generateFileReport($report, File $phpcsFile, $showSources = FALSE, $width = 80): bool
	{
		assert(is_int($report['errors']));
		assert(is_int($report['warnings']));

		if ($report['errors'] === 0 && $report['warnings'] === 0) {
			return FALSE;
		}

		assert(is_string($report['filename']));

		$filename = str_replace('\\', '\\\\', $report['filename']);
		$filename = str_replace('"', '\"', $filename);
		$filename = str_replace('/', '\/', $filename);

		echo '"' . $filename . '":[';

		$messages = '';
		foreach ($report['messages'] as $lineErrors) {
			foreach ($lineErrors as $colErrors) {
				foreach ($colErrors as $error) {
					assert(is_string($error['message']));
					assert(is_string($error['source']));

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

		return TRUE;
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
		$showSources = FALSE,
		$width = 80,
		$interactive = FALSE,
		$toScreen = TRUE,
	): void
	{
		$json = '{"files":{' . rtrim($cachedData, ',') . '}}' . PHP_EOL;
		$data = json_decode($json, TRUE, flags: JSON_THROW_ON_ERROR);

		$ignoreErrors = [];
		if (($data['files'] ?? []) !== []) {
			foreach ($data['files'] as $path => $errors) {
				foreach ($errors as $error) {
					$sniff = $error['sniff'];
					$message = $error['message'];
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

		echo Neon::encode(['ignoreErrors' => $finalIgnoreErrors], TRUE);
	}

}
