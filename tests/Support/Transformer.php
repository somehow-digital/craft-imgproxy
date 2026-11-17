<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests\Support;

use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;

/**
 * A transformer that needs no database, and records the transform it is asked for.
 */
class Transformer implements ImageTransformerInterface
{
	public ?ImageTransform $transform = null;

	public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
	{
		$this->transform = $imageTransform;

		return 'https://transforms.example.test/' . $asset->getFilename();
	}

	public function invalidateAssetTransforms(Asset $asset): void {}
}
