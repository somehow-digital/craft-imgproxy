<?php

declare(strict_types=1);

use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\models\ImageTransform;
use craft\services\Assets;
use craft\services\ImageTransforms;
use craft\web\View;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\services\BuilderService;
use yii\base\Event;

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
	Craft::$app->set('imageTransforms', new class () extends ImageTransforms {
		public function getTransformByHandle(string $handle): ?ImageTransform
		{
			return $handle === 'thumbnail' ? new ImageTransform(['quality' => 60]) : null;
		}
	});
}

it('registers the alias', function (): void {
	expect(Craft::getAlias('@imgproxy'))->toBe(Plugin::instance()->getBasePath());
});

it('registers the builder', function (): void {
	expect(Plugin::instance()->getBuilder())->toBeInstanceOf(BuilderService::class);
});

it('initializes like a Craft plugin', function (): void {
	expect(Plugin::instance()->hasReadOnlyCpSettings)->toBeTrue();
});

it('has settings', function (): void {
	expect(Plugin::instance()->hasCpSettings)->toBeTrue();
	expect(Plugin::instance()->getSettings())->toBeInstanceOf(Settings::class);
});

it('renders the settings', function (): void {
	Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
	$this->settings(['endpoint' => 'https://imgproxy.example.test']);

	$html = (new ReflectionMethod(Plugin::class, 'settingsHtml'))->invoke(Plugin::instance());

	expect($html)->toContain('id="endpoint-field"', 'id="signatureKey-field"', 'id="signatureSalt-field"', 'value="https://imgproxy.example.test"');
});

describe('asset URLs', function (): void {
	it('defines the URL of a transform given as an array', function (): void {
		$url = $this->asset()->getUrl(['width' => 300, 'height' => 200, 'upscale' => false]);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toBe(['rs:fill:300:200', 'q:70']);
	});

	it('defines the URL of a transform given as a model', function (): void {
		$url = $this->asset()->getUrl(new ImageTransform(['quality' => 80]));

		expect(urlOptions($url))->toBe(['q:80']);
	});

	it('defines the URL of a transform given by its handle', function (): void {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl('thumbnail')))->toBe(['q:60']);
	});

	it('defines the URL of a transform given by its handle with a leading underscore', function (): void {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl('_thumbnail')))->toBe(['q:60']);
	});

	it('defines the URL of a transform given as a string', function (string $transform, array $options): void {
		expect(urlOptions($this->asset()->getUrl($transform)))->toBe($options);
	})->with([
		'dimensions' => ['_300x200_crop_center-center_none', ['rs:fill:300:200:1', 'q:70']],
		'width only' => ['_300xAUTO_fit_center-center_none', ['rs:fit:300:0:1', 'q:70']],
		'height only' => ['_AUTOx200_fit_center-center_none', ['rs:fit:0:200:1', 'q:70']],
		'position and quality' => ['_300x200_crop_top-left_80_none', ['rs:fill:300:200:1', 'g:nowe', 'q:80']],
		'no upscaling' => ['_300x200_crop_center-center_none_ns', ['rs:fill:300:200', 'q:70']],
		'letterbox with fill' => ['_300x200_letterbox_center-center_none_ff0000', ['rs:fit:300:200:1:1', 'bg:ff0000', 'q:70']],
	]);

	it('extends a base transform', function (): void {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl(['transform' => 'thumbnail', 'format' => 'webp'])))
			->toBe(['q:60', 'f:webp']);
	});

	it('normalizes the fill color of a transform given as an array', function (): void {
		expect(urlOptions($this->asset()->getUrl(['fill' => 'ff0000'])))->toBe(['bg:ff0000', 'q:70']);
	});

	it('leaves assets without a transform to Craft', function (): void {
		expect(definedUrl($this->asset(), null))->toBeNull();
	});

	it('leaves unsupported files to Craft', function (): void {
		expect(definedUrl($this->asset(['filename' => 'document.pdf']), ['width' => 300]))->toBeNull();
	});

	it('leaves files that Craft does not know as images to Craft', function (): void {
		expect(definedUrl($this->asset(['filename' => 'image.ico', 'kind' => Asset::KIND_UNKNOWN]), ['width' => 300]))->toBeNull();
	});

	it('leaves URLs of other senders to Craft', function (): void {
		$event = new DefineAssetUrlEvent(['transform' => ['width' => 300]]);

		Event::trigger(Asset::class, Asset::EVENT_BEFORE_DEFINE_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('leaves URLs to Craft when the plugin is not configured', function (): void {
		$this->settings(['endpoint' => null]);

		expect(definedUrl($this->asset(), ['width' => 300]))->toBeNull();
	});

	it('leaves URLs to Craft when the signature is invalid', function (): void {
		$this->settings(['signatureKey' => 'invalid', 'signatureSalt' => 'invalid']);

		expect(definedUrl($this->asset(), ['width' => 300]))->toBeNull();
	});

	it('leaves URLs to Craft when the volume has no public URLs', function (?string $rootUrl): void {
		expect(definedUrl($this->asset(['rootUrl' => $rootUrl]), ['width' => 300]))->toBeNull();
	})->with([
		'no URL' => [null],
		'empty URL' => [''],
		'relative URL' => ['/uploads/'],
	]);

	it('leaves unknown transforms to Craft', function (): void {
		fakeTransforms();

		expect(definedUrl($this->asset(), 'unknown'))->toBeNull();
	});
});

describe('thumbnail URLs', function (): void {
	it('leaves thumbnails to Craft when the plugin is not configured', function (): void {
		$this->settings(['endpoint' => null]);

		$event = new DefineAssetThumbUrlEvent(['asset' => $this->asset(), 'width' => 120, 'height' => 80]);
		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('leaves thumbnails to Craft when the signature is invalid', function (): void {
		$this->settings(['signatureKey' => 'invalid', 'signatureSalt' => 'invalid']);

		$event = new DefineAssetThumbUrlEvent(['asset' => $this->asset(), 'width' => 120, 'height' => 80]);
		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('leaves thumbnails to Craft when the volume has no public URLs', function (): void {
		$event = new DefineAssetThumbUrlEvent(['asset' => $this->asset(['rootUrl' => '']), 'width' => 120, 'height' => 80]);
		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('defines the URL of a thumbnail', function (): void {
		$url = Craft::$app->getAssets()->getThumbUrl($this->asset(), 120, 80);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toBe(['rs:fill:120:80:1', 'q:70', 'f:webp']);
		expect(urlSource($url))->toBe('https://assets.example.test/image.jpg');
	});

	it('leaves files that Craft does not know as images to Craft', function (): void {
		$event = new DefineAssetThumbUrlEvent([
			'asset' => $this->asset(['filename' => 'image.ico', 'kind' => Asset::KIND_UNKNOWN]),
			'width' => 120,
			'height' => 80,
		]);

		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('leaves unsupported files to Craft', function (): void {
		$event = new DefineAssetThumbUrlEvent([
			'asset' => $this->asset(['filename' => 'document.pdf']),
			'width' => 120,
			'height' => 80,
		]);

		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});
});
