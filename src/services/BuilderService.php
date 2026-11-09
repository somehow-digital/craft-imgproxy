<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\Image;
use craft\models\ImageTransform;
use DateTime;
use SomehowDigital\Craft\ImgProxy\helpers\GravityHelper;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\ImgProxy\Masker\EncodingMasker;
use SomehowDigital\ImgProxy\Option\Background;
use SomehowDigital\ImgProxy\Option\CacheBuster;
use SomehowDigital\ImgProxy\Option\Enlarge;
use SomehowDigital\ImgProxy\Option\Extend;
use SomehowDigital\ImgProxy\Option\Format;
use SomehowDigital\ImgProxy\Option\Gravity;
use SomehowDigital\ImgProxy\Option\GravityEnum;
use SomehowDigital\ImgProxy\Option\GravityFocusPoint;
use SomehowDigital\ImgProxy\Option\Height;
use SomehowDigital\ImgProxy\Option\OptionInterface;
use SomehowDigital\ImgProxy\Option\Quality;
use SomehowDigital\ImgProxy\Option\Resize;
use SomehowDigital\ImgProxy\Option\ResizingType;
use SomehowDigital\ImgProxy\Option\ResizingTypeEnum;
use SomehowDigital\ImgProxy\Option\Width;
use SomehowDigital\ImgProxy\Signer\Signer;
use SomehowDigital\ImgProxy\Url;
use yii\base\Component;

/**
 * Builds the imgproxy URL of an asset for an image transform.
 */
final class BuilderService extends Component
{
	public function getUrl(Asset $asset, ImageTransform $transform): string
	{
		$url = $this->create($asset);

		$options = array_filter([
			$this->resize($asset, $transform),
			$this->extend($transform),
			$this->gravity($asset, $transform),
			$this->background($transform),
			$this->quality($transform),
			$this->format($asset, $transform),
			$this->cacheBuster($asset),
		], static fn(?OptionInterface $option): bool => $option instanceof OptionInterface);

		return $this->build($url->options(...$options)->build());
	}

	private function resize(Asset $asset, ImageTransform $transform): ?Resize
	{
		if (($transform->width ?? 0) === 0 && ($transform->height ?? 0) === 0) {
			return null;
		}

		[$width, $height] = $this->dimensions($asset, $transform);
		$upscale = $transform->upscale === true;

		$resizingType = match ($transform->mode) {
			'fit', 'letterbox' => ResizingTypeEnum::FIT,
			'stretch' => ResizingTypeEnum::FORCE,
			default => $upscale ? ResizingTypeEnum::FILL : ResizingTypeEnum::FILL_DOWN,
		};

		return new Resize(
			new ResizingType($resizingType),
			new Width($width),
			new Height($height),
			($upscale || $transform->mode === 'stretch') ? new Enlarge() : null,
		);
	}

	/**
	 * @return array{0:int,1:int}
	 */
	private function dimensions(Asset $asset, ImageTransform $transform): array
	{
		$width = $transform->width ?? 0;
		$height = $transform->height ?? 0;

		if ($width === 0 || $height === 0) {
			// Without a transform, the asset reports the size of the source instead of the size of the transform set on it.
			$source = (clone $asset)->setTransform(null);
			$sourceWidth = $source->getWidth();
			$sourceHeight = $source->getHeight();

			if ($sourceWidth !== null && $sourceHeight !== null) {
				/** @var array{0:int,1:int} */
				return Image::calculateMissingDimension($width, $height, $sourceWidth, $sourceHeight);
			}
		}

		return [$width, $height];
	}

	private function extend(ImageTransform $transform): ?Extend
	{
		if ($transform->mode !== 'letterbox') {
			return null;
		}

		return new Extend(true, $this->position($transform));
	}

	private function gravity(Asset $asset, ImageTransform $transform): ?OptionInterface
	{
		if ($asset->hasFocalPoint) {
			/** @var array{x:float,y:float} $focus */
			$focus = $asset->getFocalPoint();

			return new GravityFocusPoint($focus['x'], $focus['y']);
		}

		return $this->position($transform);
	}

	private function position(ImageTransform $transform): ?Gravity
	{
		$gravity = GravityHelper::mapPositionToGravity($transform->position);

		return $gravity === GravityEnum::CENTER ? null : new Gravity($gravity);
	}

	private function background(ImageTransform $transform): ?Background
	{
		if ($transform->fill !== null && str_starts_with($transform->fill, '#')) {
			return new Background(ltrim($transform->fill, '#'));
		}

		return null;
	}

	private function quality(ImageTransform $transform): Quality
	{
		// Like Craft, fall back to the default image quality when the transform has none.
		return new Quality($transform->quality ?? Craft::$app->getConfig()->getGeneral()->defaultImageQuality);
	}

	private function format(Asset $asset, ImageTransform $transform): ?Format
	{
		if ($transform->format !== null) {
			return new Format($transform->format);
		}

		// imgproxy keeps the source format, while Craft converts sources that browsers can't display to JPEG.
		return Image::isWebSafe($asset->getExtension())
			? null
			: new Format(Plugin::instance()->getSettings()->getFallbackFormat());
	}

	private function cacheBuster(Asset $asset): ?CacheBuster
	{
		return $asset->dateModified instanceof DateTime ? new CacheBuster((string) $asset->dateModified->getTimestamp()) : null;
	}

	private function create(Asset $asset): Url
	{
		$settings = Plugin::instance()->getSettings();

		$signatureKey = $this->parse($settings->signatureKey);
		$signatureSalt = $this->parse($settings->signatureSalt);

		$signer = ($signatureKey !== '' && $signatureSalt !== '')
			? new Signer($signatureKey, $signatureSalt)
			: null;

		return Url::create(new EncodingMasker(), $signer)->source(Assets::generateUrl($asset));
	}

	private function build(string $path): string
	{
		return rtrim($this->parse(Plugin::instance()->getSettings()->endpoint), '/') . '/' . $path;
	}

	/**
	 * Resolves environment variables and aliases, and returns an empty string for anything but a string.
	 */
	private function parse(?string $value): string
	{
		$parsed = App::parseEnv($value);

		return is_string($parsed) ? $parsed : '';
	}
}
