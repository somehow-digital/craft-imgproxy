<?php

declare(strict_types=1);

use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\imagetransforms\FallbackTransformer;
use craft\imagetransforms\ImageTransformer;
use craft\models\ImageTransform;
use craft\services\Assets;
use craft\services\ImageTransforms;
use craft\web\View;
use SomehowDigital\Craft\ImgProxy\imagetransforms\ImgproxyTransformer;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\services\BuilderService;
use SomehowDigital\Craft\ImgProxy\Tests\Support\Transformer;
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
 * Makes `thumbnail` the only transform defined in the project, which is shared like Craft does.
 */
function fakeTransforms(): ImageTransform
{
	$thumbnail = new ImageTransform(['quality' => 60]);

	Craft::$app->set('imageTransforms', new class ($thumbnail) extends ImageTransforms {
		public function __construct(
			private readonly ImageTransform $thumbnail,
		) {
			parent::__construct();
		}

		public function getTransformByHandle(string $handle): ?ImageTransform
		{
			return $handle === 'thumbnail' ? $this->thumbnail : null;
		}
	});

	return $thumbnail;
}

/**
 * Replaces the plugin's transformer, which needs a database, with one that records the transform it is asked for.
 */
function fakeTransformer(): Transformer
{
	$transformer = new Transformer();

	Craft::$container->set(ImgproxyTransformer::class, static fn(): Transformer => $transformer);

	return $transformer;
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

it('registers the transformer in place of the one of Craft', function (): void {
	expect(Craft::createObject(ImageTransformer::class))->toBeInstanceOf(ImgproxyTransformer::class);
});

it('has settings', function (): void {
	expect(Plugin::instance()->hasCpSettings)->toBeTrue();
	expect(Plugin::instance()->getSettings())->toBeInstanceOf(Settings::class);
});

it('renders the settings', function (): void {
	Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
	$this->settings(['endpoint' => 'https://imgproxy.example.test']);

	$html = (new ReflectionMethod(Plugin::class, 'settingsHtml'))->invoke(Plugin::instance());

	expect($html)->toContain(
		'id="endpoint-field"',
		'id="signatureKey-field"',
		'id="signatureSalt-field"',
		'id="mode-field"',
		'id="transforms-field"',
		'id="fallbackFormat-field"',
		'id="thumbnails-field"',
		'id="thumbnailFormat-field"',
		'value="https://imgproxy.example.test"',
		'<option value="store"',
		'<option value="avif"',
	);
});

describe('asset URLs', function (): void {
	it('defines the URL of a transform given as an array', function (): void {
		$url = $this->asset()->getUrl(['width' => 300, 'height' => 200, 'upscale' => false]);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toBe(['rs:fill-down:300:200', 'q:70']);
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
		'no upscaling' => ['_300x200_crop_center-center_none_ns', ['rs:fill-down:300:200', 'q:70']],
		'letterbox with fill' => ['_300x200_letterbox_center-center_none_ff0000', ['rs:fit:300:200:1', 'ex:1', 'bg:ff0000', 'q:70']],
	]);

	it('extends a base transform', function (): void {
		fakeTransforms();

		expect(urlOptions($this->asset()->getUrl(['transform' => 'thumbnail', 'format' => 'webp'])))
			->toBe(['q:60', 'f:webp']);
	});

	it('normalizes the fill color of a transform given as an array', function (): void {
		expect(urlOptions($this->asset()->getUrl(['fill' => 'ff0000'])))->toBe(['bg:ff0000', 'q:70']);
	});

	it('handles files with an uppercase extension', function (): void {
		$url = $this->asset(['filename' => 'IMAGE.JPG'])->getUrl(['width' => 300]);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlSource($url))->toBe('https://assets.example.test/IMAGE.JPG');
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

	it('leaves URLs to Craft when the volume has no public URLs', function (): void {
		expect(definedUrl($this->asset(['hasUrls' => false]), ['width' => 300]))->toBeNull();
	});

	it('leaves unknown transforms to Craft', function (): void {
		fakeTransforms();

		expect(definedUrl($this->asset(), 'unknown'))->toBeNull();
	});
});

describe('transforms', function (): void {
	it('leaves transforms to Craft when they are disabled', function (): void {
		$this->settings(['transforms' => false]);

		expect(definedUrl($this->asset(), ['width' => 300]))->toBeNull();
	});

	it('does not change the transforms defined in the project', function (): void {
		$thumbnail = fakeTransforms();

		$this->asset(['filename' => 'image.heic'])->getUrl('thumbnail');

		expect($thumbnail->format)->toBeNull();
	});

	it('leaves transforms of other transformers to Craft', function (): void {
		$transform = new ImageTransform(['width' => 300]);
		$transform->setTransformer(FallbackTransformer::class);

		expect(definedUrl($this->asset(), $transform))->toBeNull();
	});

	it("leaves the transforms to Craft in the store mode, which stores them with the plugin's transformer", function (): void {
		$this->settings(['mode' => 'store']);

		expect(definedUrl($this->asset(), ['width' => 300]))->toBeNull();
	});

	it('serves the files Craft cannot open from imgproxy in the store mode', function (): void {
		imageFormats(['jpg']);
		$this->settings(['mode' => 'store']);

		$url = definedUrl($this->asset(['filename' => 'image.heic']), ['width' => 300]);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toContain('f:jpg');
	});
});

describe('thumbnail URLs', function (): void {
	it('leaves thumbnails to Craft when the plugin is not configured', function (): void {
		$this->settings(['endpoint' => null]);

		$event = new DefineAssetThumbUrlEvent(['asset' => $this->asset(), 'width' => 120, 'height' => 80]);
		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('leaves thumbnails to Craft when they are disabled', function (): void {
		$this->settings(['thumbnails' => false]);

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
		$event = new DefineAssetThumbUrlEvent(['asset' => $this->asset(['hasUrls' => false]), 'width' => 120, 'height' => 80]);
		Craft::$app->getAssets()->trigger(Assets::EVENT_DEFINE_THUMB_URL, $event);

		expect($event->url)->toBeNull();
	});

	it('defines the URL of a thumbnail', function (): void {
		$url = Craft::$app->getAssets()->getThumbUrl($this->asset(), 120, 80);

		expect($url)->toStartWith('https://imgproxy.example.test/_/');
		expect(urlOptions($url))->toBe(['rs:fill:120:80:1', 'q:70', 'f:webp']);
		expect(urlSource($url))->toBe('https://assets.example.test/image.jpg');
	});

	it('uses the thumbnail format', function (): void {
		$this->settings(['thumbnailFormat' => 'avif']);

		$url = Craft::$app->getAssets()->getThumbUrl($this->asset(), 120, 80);

		expect(urlOptions($url))->toBe(['rs:fill:120:80:1', 'q:70', 'f:avif']);
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

	it('defines the URL of a thumbnail when the transforms are disabled', function (): void {
		$this->settings(['transforms' => false]);

		expect(Craft::$app->getAssets()->getThumbUrl($this->asset(), 120, 80))->toStartWith('https://imgproxy.example.test/_/');
	});

	it("has the plugin's transformer generate the thumbnails in the store mode", function (): void {
		$this->settings(['mode' => 'store']);
		$transformer = fakeTransformer();

		expect(Craft::$app->getAssets()->getThumbUrl($this->asset(['folderId' => 1]), 120, 80))->toBe('https://transforms.example.test/image.jpg');
		expect($transformer->transform?->getTransformer())->toBe(ImgproxyTransformer::class);
		expect($transformer->transform?->format)->toBe('webp');
	});

	it('serves the thumbnails of files Craft cannot open from imgproxy in the store mode', function (): void {
		imageFormats(['jpg']);
		$this->settings(['mode' => 'store']);

		expect(Craft::$app->getAssets()->getThumbUrl($this->asset(['filename' => 'image.heic']), 120, 80))->toStartWith('https://imgproxy.example.test/_/');
	});
});
