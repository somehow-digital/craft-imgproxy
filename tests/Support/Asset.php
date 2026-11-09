<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests\Support;

use craft\elements\Asset as AssetElement;
use craft\models\ImageTransform;
use craft\models\Volume;

/**
 * An asset stored in a volume with a fixed URL, which doesn't need a database or a filesystem.
 */
class Asset extends AssetElement
{
	/**
	 * The root URL of the volume, which is empty or missing when the volume has no public URLs.
	 */
	public ?string $rootUrl = 'https://assets.example.test/';

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
		return new class ($this->rootUrl) extends Volume {
			public function __construct(
				private readonly ?string $rootUrl
			) {
				parent::__construct();
			}

			public function getRootUrl(): ?string
			{
				return $this->rootUrl;
			}
		};
	}
}
