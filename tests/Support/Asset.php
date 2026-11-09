<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests\Support;

use craft\elements\Asset as AssetElement;
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
