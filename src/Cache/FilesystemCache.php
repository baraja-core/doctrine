<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


final class FilesystemCache extends FileCache
{
	public const EXTENSION = '.doctrinecache.data';


	public function __construct(string $directory, string $extension = self::EXTENSION, int $umask = 0002)
	{
		parent::__construct($directory, $extension, $umask);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$data = '';
		$lifetime = -1;
		$filename = $this->getFilename($id);

		if (!is_file($filename)) {
			return false;
		}

		$resource = fopen($filename, 'rb');
		assert($resource !== false);
		$line = fgets($resource);

		if ($line !== false) {
			$lifetime = (int) $line;
		}
		if ($lifetime !== 0 && $lifetime < time()) {
			fclose($resource);

			return false;
		}

		while (($line = fgets($resource)) !== false) {
			$data .= $line;
		}

		fclose($resource);

		return unserialize($data);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		$lifetime = -1;
		$filename = $this->getFilename($id);

		if (!is_file($filename)) {
			return false;
		}

		$resource = fopen($filename, 'rb');
		assert($resource !== false);
		$line = fgets($resource);

		if ($line !== false) {
			$lifetime = (int) $line;
		}

		fclose($resource);

		return $lifetime === 0 || $lifetime > time();
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifeTime = 0)
	{
		if ($lifeTime > 0) {
			$lifeTime = time() + $lifeTime;
		}

		$data = serialize($data);
		$filename = $this->getFilename($id);

		return $this->writeFile($filename, $lifeTime . PHP_EOL . $data);
	}
}
