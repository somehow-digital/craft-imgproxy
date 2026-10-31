<?php

namespace SomehowDigital\Craft\ImgProxy\Tests\Support;

use craft\elements\Asset as AssetElement;
use craft\models\Volume;

/**
 * An asset stored in a volume with a fixed URL, which doesn't need a database or a filesystem.
 */
class Asset extends AssetElement
{
	public function getVolume(): Volume
	{
		return new class() extends Volume {
			public function getRootUrl(): ?string
			{
				return 'https://assets.example.test/';
			}
		};
	}
}
