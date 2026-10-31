<?php

use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\models\ImageTransform;
use craft\services\Assets;
use craft\services\ImageTransforms;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\services\Builder;

/**
 * The URL the plugin defines for an asset, which Craft generates itself when there is none.
 */
function definedUrl(Asset $asset, mixed $transform): ?string
{
	$event = new DefineAssetUrlEvent(['transform' => $transform, 'asset' => $asset]);
	$asset->trigger(Asset::EVENT_BEFORE_DEFINE_URL, $event);

	return $event->url;
}

/**
 * Makes `thumbnail` the only transform defined in the project.
 */
function fakeTransforms(): void
{
	Craft::$app->set('imageTransforms', new class() extends ImageTransforms {
		public function getTransformByHandle(string $handle): ?ImageTransform
		{
			return $handle === 'thumbnail' ? new ImageTransform(['quality' => 60]) : null;
		}
	});
}

it('registers the alias', function () {
	expect(Craft::getAlias('@imgproxy'))->toBe(Plugin::getInstance()->getBasePath());
});

it('registers the builder', function () {
	expect(Plugin::getInstance()->getBuilder())->toBeInstanceOf(Builder::class);
});

it('has settings', function () {
	expect(Plugin::getInstance()->hasCpSettings)->toBeTrue();
	expect(Plugin::getInstance()->getSettings())->toBeInstanceOf(Settings::class);
});

describe('asset URLs', function () {
	it('defines the URL of a transform given as an array', function () {
		$url = $this->asset()->getUrl(['width' => 300, 'height' => 200, 'upscale' => false]);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toBe(['rs:fill:300:200']);
	});

	it('defines the URL of a transform given as a model', function () {
		$url = $this->asset()->getUrl(new ImageTransform(['quality' => 80]));

		expect(urlOptions($url))->toBe(['q:80']);
	});

	it('defines the URL of a transform given by its handle', function () {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl('thumbnail')))->toBe(['q:60']);
	});

	it('defines the URL of a transform given by its handle with a leading underscore', function () {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl('_thumbnail')))->toBe(['q:60']);
	});

	it('defines the URL of a transform given as a string', function (string $transform, array $options) {
		expect(urlOptions($this->asset()->getUrl($transform)))->toBe($options);
	})->with([
		'dimensions' => ['_300x200_crop_center-center_none', ['rs:fill:300:200:1']],
		'width only' => ['_300xAUTO_fit_center-center_none', ['rs:fit:300:0:1']],
		'height only' => ['_AUTOx200_fit_center-center_none', ['rs:fit:0:200:1']],
		'position and quality' => ['_300x200_crop_top-left_80_none', ['rs:fill:300:200:1', 'g:nowe', 'q:80']],
		'no upscaling' => ['_300x200_crop_center-center_none_ns', ['rs:fill:300:200']],
		'letterbox with fill' => ['_300x200_letterbox_center-center_none_ff0000', ['rs:fit:300:200:1:1', 'bg:ff0000']],
	]);

	it('extends a base transform', function () {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl(['transform' => 'thumbnail', 'format' => 'webp'])))
			->toBe(['q:60', 'f:webp']);
	});

	it('normalizes the fill color of a transform given as an array', function () {
		expect(urlOptions($this->asset()->getUrl(['fill' => 'ff0000'])))->toBe(['bg:ff0000']);
	});

	it('leaves assets without a transform to Craft', function () {
		expect(definedUrl($this->asset(), null))->toBeNull();
	});

	it('leaves unsupported files to Craft', function () {
		expect(definedUrl($this->asset(['filename' => 'document.pdf']), ['width' => 300]))->toBeNull();
	});

	it('leaves unknown transforms to Craft', function () {
		fakeTransforms();

		expect(definedUrl($this->asset(), 'unknown'))->toBeNull();
	});
});

describe('thumbnail URLs', function () {
	it('defines the URL of a thumbnail', function () {
		$url = Craft::$app->getAssets()->getThumbUrl($this->asset(), 120, 80);

		$quality = Craft::$app->getConfig()->getGeneral()->defaultImageQuality;

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toContain("q:$quality", 'f:webp');
		expect(urlSource($url))->toBe('https://assets.example.test/image.jpg');
	});

	it('leaves unsupported files to Craft', function () {
		$event = new DefineAssetThumbUrlEvent([
			'asset' => $this->asset(['filename' => 'document.pdf']),
			'width' => 120,
			'height' => 80,
		]);

		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});
});
