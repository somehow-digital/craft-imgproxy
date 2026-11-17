<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\services;

use Craft;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\helpers\Assets;
use craft\helpers\Image;
use craft\helpers\ImageTransforms;
use craft\models\ImageTransform;
use DateTime;
use SomehowDigital\Craft\ImgProxy\helpers\GravityHelper;
use SomehowDigital\Craft\ImgProxy\imagetransforms\ImgproxyTransformer;
use SomehowDigital\Craft\ImgProxy\models\Settings;
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
use SomehowDigital\ImgProxy\Utility\Format as SupportedFormat;
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
			$this->format($transform),
			$this->cacheBuster($asset),
		], static fn(?OptionInterface $option): bool => $option instanceof OptionInterface);

		return $this->build($url->options(...$options)->build());
	}

	/**
	 * The imgproxy URL for an asset and a transform in any form Craft accepts, or null when Craft is to handle it.
	 *
	 * In the `store` mode, Craft handles it with this plugin's transformer, see getStoredTransform().
	 */
	public function getAssetUrl(Asset $asset, mixed $transform): ?string
	{
		$transform = $this->resolve($asset, $transform, false);

		return $transform instanceof ImageTransform ? $this->getUrl($asset, $transform) : null;
	}

	/**
	 * The URL of a control panel thumbnail, or null when Craft is to handle it.
	 */
	public function getThumbnailUrl(Asset $asset, int $width, int $height): ?string
	{
		$settings = Plugin::instance()->getSettings();

		if (!$settings->isThumbnailsEnabled() || !$this->supports($asset)) {
			return null;
		}

		$transform = new ImageTransform([
			'width' => $width,
			'height' => $height,
			'format' => $settings->getThumbnailFormat(),
		]);

		if (!$this->stores($asset)) {
			return $this->getUrl($asset, $transform);
		}

		$transform->setTransformer(ImgproxyTransformer::class);

		return $asset->getUrl($transform);
	}

	/**
	 * The transform for this plugin's transformer to have imgproxy generate and Craft store, or null when Craft
	 * generates it.
	 *
	 * @internal Used by the transformer.
	 */
	public function getStoredTransform(Asset $asset, ImageTransform $transform): ?ImageTransform
	{
		$transform = $this->resolve($asset, $transform, true);
		$transform?->setTransformer(ImgproxyTransformer::class);

		return $transform;
	}

	/**
	 * A copy of the transform with the fallback format, or null when imgproxy does not handle it, or not as stored.
	 */
	private function resolve(Asset $asset, mixed $transform, bool $stored): ?ImageTransform
	{
		if (!Plugin::instance()->getSettings()->isTransformsEnabled() || $this->stores($asset) !== $stored || !$this->supports($asset)) {
			return null;
		}

		$transform = $this->normalize($transform);

		if ($transform instanceof ImageTransform) {
			$transform->format ??= $this->fallbackFormat($asset);
		}

		return $transform;
	}

	/**
	 * Whether Craft stores the transforms of the asset, which it only does for files its image driver can open.
	 * imgproxy serves the others.
	 */
	private function stores(Asset $asset): bool
	{
		return Plugin::instance()->getSettings()->getMode() === Settings::MODE_STORE
			&& Image::canManipulateAsImage($asset->getExtension());
	}

	/**
	 * A copy of the transform, since Craft shares the transforms defined in the project, or null when it is unknown or
	 * for another transformer, such as Craft's fallback transformer for thumbnails.
	 */
	private function normalize(mixed $transform): ?ImageTransform
	{
		try {
			$transform = ImageTransforms::normalizeTransform($transform);
		} catch (ImageTransformException) {
			return null;
		}

		return $transform instanceof ImageTransform && $transform->getTransformer() === ImageTransform::DEFAULT_TRANSFORMER
			? clone $transform
			: null;
	}

	private function supports(Asset $asset): bool
	{
		return Plugin::instance()->getSettings()->isConfigured()
			&& $asset->kind === Asset::KIND_IMAGE
			&& SupportedFormat::supports($asset->getExtension())
			&& $asset->getVolume()->getFs()->hasUrls;
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

	private function format(ImageTransform $transform): ?Format
	{
		return $transform->format === null ? null : new Format($transform->format);
	}

	/**
	 * imgproxy keeps the source format, while Craft converts sources that browsers can't display to JPEG.
	 */
	private function fallbackFormat(Asset $asset): ?string
	{
		return Image::isWebSafe($asset->getExtension())
			? null
			: Plugin::instance()->getSettings()->getFallbackFormat();
	}

	private function cacheBuster(Asset $asset): ?CacheBuster
	{
		return $asset->dateModified instanceof DateTime ? new CacheBuster((string) $asset->dateModified->getTimestamp()) : null;
	}

	private function create(Asset $asset): Url
	{
		$settings = Plugin::instance()->getSettings();

		$signatureKey = $settings->getSignatureKey();
		$signatureSalt = $settings->getSignatureSalt();

		$signer = ($signatureKey !== '' && $signatureSalt !== '')
			? new Signer($signatureKey, $signatureSalt)
			: null;

		return Url::create(new EncodingMasker(), $signer)->source(Assets::generateUrl($asset));
	}

	private function build(string $path): string
	{
		return rtrim(Plugin::instance()->getSettings()->getEndpoint(), '/') . '/' . $path;
	}
}
