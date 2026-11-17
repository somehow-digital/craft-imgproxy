<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests\Support;

use craft\elements\Asset as AssetElement;
use craft\fs\Local;
use craft\models\ImageTransform;
use craft\models\Volume;

/**
 * An asset stored in a volume with a fixed URL, which doesn't need a database or a filesystem.
 */
class Asset extends AssetElement
{
	public ?string $rootUrl = 'https://assets.example.test/';

	/**
	 * Whether the filesystem of the volume has public URLs.
	 */
	public bool $hasUrls = true;

	/**
	 * The path of the filesystem of the volume, which stores the transforms too.
	 */
	public ?string $path = null;

	/**
	 * The width and the height the asset reports for its source, which are both unknown or both known in Craft.
	 *
	 * @var array{0:int|null,1:int|null}|null
	 */
	public ?array $dimensions = null;

	/**
	 * An image asset, without a date of its last modification unless given.
	 *
	 * @param array<string,mixed> $config
	 */
	public static function image(array $config = []): self
	{
		return new self([
			'filename' => 'image.jpg',
			'kind' => self::KIND_IMAGE,
			...$config,
		]);
	}

	/**
	 * @param array<mixed>|string|ImageTransform|null $transform
	 */
	public function getWidth(array|string|ImageTransform|null $transform = null): ?int
	{
		return $this->dimensions !== null ? $this->dimensions[0] : parent::getWidth($transform);
	}

	/**
	 * @param array<mixed>|string|ImageTransform|null $transform
	 */
	public function getHeight(mixed $transform = null): ?int
	{
		return $this->dimensions !== null ? $this->dimensions[1] : parent::getHeight($transform);
	}

	public function getVolume(): Volume
	{
		return new class ($this->rootUrl, $this->hasUrls, $this->path) extends Volume {
			public function __construct(
				private readonly ?string $rootUrl,
				bool $hasUrls,
				?string $path,
			) {
				parent::__construct();
				$this->setFs(new Local(['hasUrls' => $hasUrls, 'path' => $path]));
			}

			public function getRootUrl(): ?string
			{
				return $this->rootUrl;
			}
		};
	}
}
