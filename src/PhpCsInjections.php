<?php declare(strict_types=1);

namespace Forrest79\PhpCsIgnores;

/**
 * @inspiration bypassFinals() from Nette\Tester (https://tester.nette.org/en/)
 */
final class PhpCsInjections
{
	private const PROTOCOL = 'file';

	/** @var resource|NULL */
	public $context;

	/** @var resource|NULL */
	private $handle;

	/** @var array<callable(string $path, string $code): string> */
	private static array $injections = [];


	public static function register(): void
	{
		self::setInjections([static function (string $path, string $code): string {
			if (str_ends_with($path, 'vendor/phpcsstandards/php_codesniffer/src/Files/FileList.php') || str_ends_with($path, 'vendor/squizlabs/php_codesniffer/src/Files/FileList.php')) { // squizlabs - backwards compatibility with an abandon PHP_CodeSniffer version
				// already patched (why?)
				if (str_contains($code, File::class)) {
					if (PHP_CODESNIFFER_VERBOSITY > 0) {
						echo sprintf('File \'%s\' is already patched', $path) . PHP_EOL;
					}
					return $code;
				}

				// can't find where to put patch (new PHPCS version?)
				$search = 'new LocalFile(';
				if (!str_contains($code, $search)) {
					if (PHP_CODESNIFFER_VERBOSITY > 0) {
						echo sprintf('Can\'t find \'%s\' in file \'%s\'. Patch can\'t be applied', $search, $path) . PHP_EOL;
					}
					return $code;
				}

				$injectCode = 'new \\' . File::class . '(';

				return str_replace($search, $injectCode, $code);
			}

			return $code;
		}]);
	}


	/**
	 * @param array<callable(string $path, string $code): string> $injections
	 */
	private static function setInjections(array $injections): void
	{
		self::$injections = $injections;
		stream_wrapper_unregister(self::PROTOCOL);
		stream_wrapper_register(self::PROTOCOL, self::class);
	}


	public function dir_closedir(): void
	{
		closedir($this->handle);
	}


	public function dir_opendir(string $path, int $options): bool
	{
		$this->handle = $this->context !== NULL
			? $this->native('opendir', $path, $this->context)
			: $this->native('opendir', $path);

		return (bool) $this->handle;
	}


	/**
	 * @return string|FALSE
	 */
	public function dir_readdir()
	{
		return readdir($this->handle);
	}


	public function dir_rewinddir(): bool
	{
		return (bool) rewinddir($this->handle);
	}


	public function mkdir(string $path, int $mode, int $options): bool
	{
		$recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

		return $this->context !== NULL
			? $this->native('mkdir', $path, $mode, $recursive, $this->context)
			: $this->native('mkdir', $path, $mode, $recursive);
	}


	public function rename(string $pathFrom, string $pathTo): bool
	{
		return $this->context !== NULL
			? $this->native('rename', $pathFrom, $pathTo, $this->context)
			: $this->native('rename', $pathFrom, $pathTo);
	}


	public function rmdir(string $path, int $options): bool
	{
		return $this->context !== NULL
			? $this->native('rmdir', $path, $this->context)
			: $this->native('rmdir', $path);
	}


	/**
	 * @return resource|NULL
	 */
	public function stream_cast(int $castAs)
	{
		return $this->handle;
	}


	public function stream_close(): void
	{
		fclose($this->handle);
	}


	public function stream_eof(): bool
	{
		return feof($this->handle);
	}


	public function stream_flush(): bool
	{
		return fflush($this->handle);
	}


	/**
	 * @param int<0, 7> $operation
	 */
	public function stream_lock(int $operation): bool
	{
		return $operation > 0
			? flock($this->handle, $operation)
			: TRUE;
	}


	/**
	 * @param mixed $value
	 */
	public function stream_metadata(string $path, int $option, $value): bool
	{
		switch ($option) {
			case STREAM_META_TOUCH:
				return $this->native('touch', $path, $value[0] ?? time(), $value[1] ?? time());
			case STREAM_META_OWNER_NAME:
			case STREAM_META_OWNER:
				return $this->native('chown', $path, $value);
			case STREAM_META_GROUP_NAME:
			case STREAM_META_GROUP:
				return $this->native('chgrp', $path, $value);
			case STREAM_META_ACCESS:
				return $this->native('chmod', $path, $value);
		}

		return FALSE;
	}


	public function stream_open(string $path, string $mode, int $options, string|NULL &$openedPath): bool
	{
		$usePath = (bool) ($options & STREAM_USE_PATH);
		if (($mode === 'rb') && (pathinfo($path, PATHINFO_EXTENSION) === 'php')) {
			$content = $this->native('file_get_contents', $path, $usePath, $this->context);
			if ($content === FALSE) {
				return FALSE;
			} else {
				foreach (self::$injections as $injection) {
					$content = $injection($path, $content);
				}

				$this->handle = tmpfile();
				$this->native('fwrite', $this->handle, $content);
				$this->native('fseek', $this->handle, 0);
				return TRUE;
			}
		} else {
			$this->handle = $this->context !== NULL
				? $this->native('fopen', $path, $mode, $usePath, $this->context)
				: $this->native('fopen', $path, $mode, $usePath);
			return (bool) $this->handle;
		}
	}


	/**
	 * @return string|FALSE
	 */
	public function stream_read(int $count)
	{
		return fread($this->handle, $count);
	}


	public function stream_seek(int $offset, int $whence = SEEK_SET): bool
	{
		return fseek($this->handle, $offset, $whence) === 0;
	}


	public function stream_set_option(int $option, int $arg1, int $arg2): bool
	{
		return FALSE;
	}


	/**
	 * @return array<mixed>|FALSE
	 */
	public function stream_stat()
	{
		return fstat($this->handle);
	}


	public function stream_tell(): int
	{
		return ftell($this->handle);
	}


	public function stream_truncate(int $newSize): bool
	{
		return ftruncate($this->handle, $newSize);
	}


	/**
	 * @return int|FALSE
	 */
	public function stream_write(string $data)
	{
		return fwrite($this->handle, $data);
	}


	public function unlink(string $path): bool
	{
		return $this->native('unlink', $path);
	}


	/**
	 * @return mixed
	 */
	public function url_stat(string $path, int $flags)
	{
		$func = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';
		return $flags & STREAM_URL_STAT_QUIET
			? @$this->native($func, $path)
			: $this->native($func, $path);
	}


	/**
	 * @return mixed
	 */
	private function native(string $func)
	{
		stream_wrapper_restore(self::PROTOCOL);
		try {
			return $func(...array_slice(func_get_args(), 1));
		} finally {
			stream_wrapper_unregister(self::PROTOCOL);
			stream_wrapper_register(self::PROTOCOL, self::class);
		}
	}

}
