<?php

namespace SomehowDigital\Craft\ImgProxy\services;

use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\models\ImageTransform;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\utilities\GravityUtility;
use SomehowDigital\ImgProxy\Masker\EncodingMasker;
use SomehowDigital\ImgProxy\Masker\EncryptionMasker;
use SomehowDigital\ImgProxy\Option\Background;
use SomehowDigital\ImgProxy\Option\CacheBuster;
use SomehowDigital\ImgProxy\Option\Enlarge;
use SomehowDigital\ImgProxy\Option\Format;
use SomehowDigital\ImgProxy\Option\Gravity;
use SomehowDigital\ImgProxy\Option\GravityFocusPoint;
use SomehowDigital\ImgProxy\Option\Height;
use SomehowDigital\ImgProxy\Option\Quality;
use SomehowDigital\ImgProxy\Option\Resize;
use SomehowDigital\ImgProxy\Option\ResizingType;
use SomehowDigital\ImgProxy\Option\ResizingTypeEnum;
use SomehowDigital\ImgProxy\Option\Width;
use SomehowDigital\ImgProxy\Signer\Signer;
use SomehowDigital\ImgProxy\Url;
use yii\base\Component;

class Builder extends Component
{
	public function getUrl(Asset $asset, ImageTransform $transform): string
	{
		$url = $this->create($asset);

		$options = [];

		if ($transform->width || $transform->height) {
			$resizingType = match ($transform->mode) {
				'fit' => ResizingTypeEnum::FIT,
				'letterbox' => ResizingTypeEnum::FORCE,
				'stretch' => ResizingTypeEnum::FORCE,
				default => ResizingTypeEnum::FILL,
			};

			$enlarge = $transform->upscale ? new Enlarge() : null;

			$options[] = new Resize(
				new ResizingType($resizingType),
				$transform->width ? new Width($transform->width) : null,
				$transform->height ? new Height($transform->height) : null,
				$enlarge,
			);
		}

		$position = $transform->position ?? 'center-center';

		if ($transform->mode === 'crop') {
			if ($asset->hasFocalPoint) {
				$focalPoint = $asset->getFocalPoint();
				$options[] = new GravityFocusPoint(
					(float) $focalPoint['x'],
					(float) $focalPoint['y'],
				);
			} else {
				$options[] = new Gravity(GravityUtility::mapPositionToGravity($position));
			}
		}

		if ($transform->mode === 'letterbox') {
			$options[] = new Gravity(GravityUtility::mapPositionToGravity($position));
			$fillColor = $transform->fill ?? 'transparent';
			if ($fillColor === 'transparent') {
				$options[] = new Background(0, 0, 0, 0);
			} else {
				$hex = ltrim($fillColor, '#');
				$options[] = new Background($hex);
			}
		}

		if ($transform->quality) {
			$options[] = new Quality($transform->quality);
		}

		if ($transform->format) {
			$options[] = new Format($transform->format);
		}

		if ($asset->dateModified) {
			$options[] = new CacheBuster((string) $asset->dateModified->getTimestamp());
		}

		if ($options) {
			$url = $url->options(...$options);
		}

		return $this->build($url->build());
	}

	private function create(Asset $asset): Url
	{
		$plugin = Plugin::getInstance();
		$settings = $plugin->getSettings();

		$signatureKey = App::parseEnv($settings->signatureKey);
		$signatureSalt = App::parseEnv($settings->signatureSalt);
		$encryptionKey = App::parseEnv($settings->encryptionKey);

		$masker = $encryptionKey
			? new EncryptionMasker($encryptionKey)
			: new EncodingMasker();

		$signer = ($signatureKey && $signatureSalt)
			? new Signer($signatureKey, $signatureSalt)
			: null;

		return Url::create($masker, $signer)->source(Assets::generateUrl($asset));
	}

	private function build(string $path): string
	{
		$endpoint = App::parseEnv(Plugin::getInstance()->getSettings()->endpoint);

		return rtrim($endpoint, '/') . '/' . $path;
	}
}
