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
		return new class () extends Volume {
			public function getRootUrl(): string
			{
				return 'https://assets.example.test/';
			}
		};
	}
}
