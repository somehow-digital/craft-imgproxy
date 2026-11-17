<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\imagetransforms;

use Craft;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\imagetransforms\ImageTransformer;
use craft\models\ImageTransform;
use craft\models\ImageTransformIndex;
use SomehowDigital\Craft\ImgProxy\Plugin;

/**
 * Craft's image transformer, which has imgproxy generate the transforms indexed for it, and Craft generate the others.
 *
 * It replaces Craft's transformer, since Craft generates deferred transforms with its own, whichever the transform names.
 * In the `store` mode, it indexes the transforms for itself, so Craft stores, defers and invalidates them as usual.
 *
 * @internal
 */
final class ImgproxyTransformer extends ImageTransformer
{
	public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
	{
		$transform = Plugin::instance()->getBuilder()->getStoredTransform($asset, $imageTransform);

		return parent::getTransformUrl($asset, $transform ?? $imageTransform, $immediately);
	}

	protected function generateTransformedImage(Asset $asset, ImageTransformIndex $index): void
	{
		if ($index->transformer !== self::class) {
			parent::generateTransformedImage($asset, $index);

			return;
		}

		// Craft names the file after the detected format, so imgproxy has to deliver that format.
		$transform = clone $index->getTransform();
		$transform->format = $index->detectedFormat;

		$response = Craft::createGuzzleClient()->get(Plugin::instance()->getBuilder()->getUrl($asset, $transform));

		// Something in front of imgproxy, such as a proxy, may answer with a page, which must not be stored as an image.
		if (!str_starts_with($response->getHeaderLine('Content-Type'), 'image/')) {
			throw new ImageTransformException('imgproxy returned no image.');
		}

		// Guzzle buffers the body in a temporary stream, which is copied over rather than read into memory.
		$stream = $response->getBody()->detach() ?? throw new ImageTransformException('imgproxy returned no readable body.');

		$asset->getVolume()->getTransformFs()->writeFileFromStream(
			$this->getTransformBasePath($asset) . $this->getTransformSubpath($asset, $index),
			$stream,
		);
	}
}
