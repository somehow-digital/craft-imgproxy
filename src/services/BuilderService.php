<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\services;

use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
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
			$this->resize($transform),
			$this->gravity($asset, $transform),
			$this->background($transform),
			$this->quality($transform),
			$this->format($transform),
			$this->cacheBuster($asset),
		], static fn(?OptionInterface $option): bool => $option instanceof OptionInterface);

		if ($options !== []) {
			$url = $url->options(...$options);
		}

		return $this->build($url->build());
	}

	private function resize(ImageTransform $transform): ?Resize
	{
		if (($transform->width ?? 0) === 0 && ($transform->height ?? 0) === 0) {
			return null;
		}

		$resizingType = match ($transform->mode) {
			'fit' => ResizingTypeEnum::FIT,
			'letterbox' => ResizingTypeEnum::FIT,
			'stretch' => ResizingTypeEnum::FORCE,
			default => ResizingTypeEnum::FILL,
		};

		$letterbox = $transform->mode === 'letterbox';
		$upscale = $transform->upscale === true;

		// Resize drops empty values, so every value before the last one needs to be set to keep its position.
		return new Resize(
			new ResizingType($resizingType),
			new Width($transform->width ?? 0),
			new Height($transform->height ?? 0),
			($upscale || $letterbox) ? new Enlarge($upscale) : null,
			$letterbox ? new Extend() : null,
		);
	}

	private function gravity(Asset $asset, ImageTransform $transform): ?OptionInterface
	{
		if ($asset->hasFocalPoint) {
			/** @var array{x:float,y:float} $focus */
			$focus = $asset->getFocalPoint();

			return new GravityFocusPoint($focus['x'], $focus['y']);
		}

		if ($transform->position !== 'center-center') {
			return new Gravity(GravityHelper::mapPositionToGravity($transform->position));
		}

		return null;
	}

	private function background(ImageTransform $transform): ?Background
	{
		if ($transform->fill !== null && str_starts_with($transform->fill, '#')) {
			return new Background(ltrim($transform->fill, '#'));
		}

		return null;
	}

	private function quality(ImageTransform $transform): ?Quality
	{
		return $transform->quality !== null ? new Quality($transform->quality) : null;
	}

	private function format(ImageTransform $transform): ?Format
	{
		return $transform->format !== null ? new Format($transform->format) : null;
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
