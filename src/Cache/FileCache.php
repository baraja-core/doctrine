<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;

abstract class FileCache extends CacheProvider
{
	protected string $directory;

	private string $extension;

	private int $umask;

	private int $directoryStringLength;

	private int $extensionStringLength;

	private bool $isRunningOnWindows;


	/**
	 * @throws \InvalidArgumentException
	 */
	public function __construct(string $directory, string $extension = '', int $umask = 0002)
	{
		$this->umask = $umask;

		if (!$this->createPathIfNeeded($directory)) {
			throw new \InvalidArgumentException(
				'The directory "' . $directory . '" does not exist and could not be created.',
			);
		}
		if (!is_writable($directory)) {
			throw new \InvalidArgumentException('The directory "' . $directory . '" is not writable.');
		}

		// YES, this needs to be *after* createPathIfNeeded()
		$this->directory = (string) realpath($directory);
		$this->extension = $extension;

		$this->directoryStringLength = strlen($this->directory);
		$this->extensionStringLength = strlen($this->extension);
		$this->isRunningOnWindows = defined('PHP_WINDOWS_VERSION_BUILD');
	}


	/**
	 * Gets the cache directory.
	 */
	public function getDirectory(): string
	{
		return $this->directory;
	}


	/**
	 * Gets the cache file extension.
	 */
	public function getExtension(): string
	{
		return $this->extension;
	}


	protected function getFilename(string $id): string
	{
		$hash = hash('sha256', $id);

		// This ensures that the filename is unique and that there are no invalid chars in it.
		if (
			$id === ''
			|| ((strlen($id) * 2 + $this->extensionStringLength) > 255)
			|| (
				$this->isRunningOnWindows
				&& ($this->directoryStringLength + 4 + strlen($id) * 2 + $this->extensionStringLength) > 258
			)
		) {
			// Most filesystems have a limit of 255 chars for each path component. On Windows the the whole path is limited
			// to 260 chars (including terminating null char). Using long UNC ("\\?\" prefix) does not work with the PHP API.
			// And there is a bug in PHP (https://bugs.php.net/bug.php?id=70943) with path lengths of 259.
			// So if the id in hex representation would surpass the limit, we use the hash instead. The prefix prevents
			// collisions between the hash and bin2hex.
			$filename = '_' . $hash;
		} else {
			$filename = bin2hex($id);
		}

		return $this->directory
			. DIRECTORY_SEPARATOR
			. substr($hash, 0, 2)
			. DIRECTORY_SEPARATOR
			. $filename
			. $this->extension;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id): bool
	{
		$filename = $this->getFilename($id);

		return @unlink($filename) || !file_exists($filename);
	}


	protected function doFlush(): bool
	{
		foreach ($this->getIterator() as $name => $file) {
			if ($file->isDir()) {
				// Remove the intermediate directories which have been created to balance the tree. It only takes effect
				// if the directory is empty. If several caches share the same directory but with different file extensions,
				// the other ones are not removed.
				@rmdir($name);
			} elseif ($this->isFilenameEndingWithExtension($name)) {
				// If an extension is set, only remove files which end with the given extension.
				// If no extension is set, we have no other choice than removing everything.
				@unlink($name);
			}
		}

		return true;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats(): ?array
	{
		$usage = 0;
		foreach ($this->getIterator() as $name => $file) {
			if ($file->isDir() || !$this->isFilenameEndingWithExtension($name)) {
				continue;
			}

			$usage += $file->getSize();
		}

		$free = disk_free_space($this->directory);

		return [
			Cache::STATS_HITS => null,
			Cache::STATS_MISSES => null,
			Cache::STATS_UPTIME => null,
			Cache::STATS_MEMORY_USAGE => $usage,
			Cache::STATS_MEMORY_AVAILABLE => $free,
		];
	}


	/**
	 * Writes a string content to file in an atomic way.
	 *
	 * @param string $filename Path to the file where to write the data.
	 * @param string $content The content to write
	 * @return bool TRUE on success, FALSE if path cannot be created, if path is not writable or an any other error.
	 */
	protected function writeFile(string $filename, string $content): bool
	{
		$filepath = pathinfo($filename, PATHINFO_DIRNAME);

		if (!$this->createPathIfNeeded($filepath)) {
			return false;
		}
		if (!is_writable($filepath)) {
			return false;
		}

		$tmpFile = tempnam($filepath, 'swap');
		assert($tmpFile !== false);
		@chmod($tmpFile, 0666 & (~$this->umask));

		if (file_put_contents($tmpFile, $content) !== false) {
			@chmod($tmpFile, 0666 & (~$this->umask));
			if (@rename($tmpFile, $filename)) {
				return true;
			}

			@unlink($tmpFile);
		}

		return false;
	}


	/**
	 * @return bool TRUE on success or if path already exists, FALSE if path cannot be created.
	 */
	private function createPathIfNeeded(string $path): bool
	{
		return !(!is_dir($path) && @mkdir($path, 0777 & (~$this->umask), true) === false && !is_dir($path));
	}


	/**
	 * @return \Iterator<string, \SplFileInfo>
	 */
	private function getIterator(): \Iterator
	{
		return new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
	}


	private function isFilenameEndingWithExtension(string $name): bool
	{
		return $this->extension === ''
			|| strrpos($name, $this->extension) === strlen($name) - $this->extensionStringLength;
	}
}
