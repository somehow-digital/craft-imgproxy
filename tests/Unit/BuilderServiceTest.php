<?php

declare(strict_types=1);

use craft\models\ImageTransform;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\Tests\Support\Asset;

/**
 * The imgproxy URL of an asset for a transform.
 *
 * @param array<string,mixed> $transform
 * @param array<string,mixed> $asset
 */
function imageUrl(array $transform = [], array $asset = []): string
{
	return Plugin::instance()->getBuilder()->getUrl(Asset::image($asset), new ImageTransform($transform));
}

describe('endpoint', function (): void {
	it('prepends the endpoint', function (): void {
		expect(imageUrl())->toStartWith('https://imgproxy.example.test/_/');
	});

	it('removes a trailing slash from the endpoint', function (): void {
		$this->settings(['endpoint' => 'https://imgproxy.example.test/']);

		expect(imageUrl())->toStartWith('https://imgproxy.example.test/_/');
	});

	it('reads the endpoint from an environment variable', function (): void {
		$this->env(['IMGPROXY_ENDPOINT' => 'https://environment.example.test']);
		$this->settings(['endpoint' => '$IMGPROXY_ENDPOINT']);

		expect(imageUrl())->toStartWith('https://environment.example.test/_/');
	});
});

describe('source', function (): void {
	it('encodes the URL of the asset', function (): void {
		expect(urlSource(imageUrl()))->toBe('https://assets.example.test/image.jpg');
	});

	it('includes the folder of the asset', function (): void {
		expect(urlSource(imageUrl(asset: ['folderPath' => 'photos/2026/'])))
			->toBe('https://assets.example.test/photos/2026/image.jpg');
	});

	it('encodes special characters of the filename', function (): void {
		expect(urlSource(imageUrl(asset: ['filename' => 'my image.jpg'])))
			->toBe('https://assets.example.test/my%20image.jpg');
	});
});

describe('signature', function (): void {
	$key = bin2hex('secret');
	$salt = bin2hex('salt');

	it('leaves URLs unsigned without a key and a salt', function (): void {
		expect(urlSignature(imageUrl()))->toBe('_');
	});

	it('leaves URLs unsigned with only a key or a salt', function (array $settings): void {
		$this->settings($settings);

		expect(urlSignature(imageUrl()))->toBe('_');
	})->with([
		'key' => [['signatureKey' => $key]],
		'salt' => [['signatureSalt' => $salt]],
	]);

	it('signs URLs with the key and the salt', function () use ($key, $salt): void {
		$this->settings(['signatureKey' => $key, 'signatureSalt' => $salt]);

		$url = imageUrl(['width' => 300, 'height' => 200]);
		$digest = hash_hmac('sha256', 'salt/' . urlPath($url), 'secret', true);

		expect(urlSignature($url))->toBe(rtrim(strtr(base64_encode($digest), '+/', '-_'), '='));
	});

	it('reads the key and the salt from environment variables', function () use ($key, $salt): void {
		$this->env(['IMGPROXY_KEY' => $key, 'IMGPROXY_SALT' => $salt]);
		$this->settings(['signatureKey' => '$IMGPROXY_KEY', 'signatureSalt' => '$IMGPROXY_SALT']);

		expect(urlSignature(imageUrl()))->not->toBe('_');
	});
});

describe('resize', function (): void {
	it('has no resize option without dimensions', function (): void {
		expect(urlOptions(imageUrl()))->toBe(['q:70']);
	});

	it('maps the mode and the upscaling to a resizing type', function (string $mode, bool $upscale, string $resize): void {
		expect(urlOptions(imageUrl(['mode' => $mode, 'width' => 300, 'height' => 200, 'upscale' => $upscale])))
			->toBe([$resize, 'q:70']);
	})->with([
		'crop with upscaling' => ['crop', true, 'rs:fill:300:200:1'],
		'crop without upscaling' => ['crop', false, 'rs:fill-down:300:200'],
		'fit with upscaling' => ['fit', true, 'rs:fit:300:200:1'],
		'fit without upscaling' => ['fit', false, 'rs:fit:300:200'],
		'stretch with upscaling' => ['stretch', true, 'rs:force:300:200:1'],
		'stretch without upscaling' => ['stretch', false, 'rs:force:300:200:1'],
	]);

	it('fits and extends images for letterboxing', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => true])))
			->toBe(['rs:fit:300:200:1', 'ex:1', 'q:70']);
	});

	it('positions letterboxed images', function (string $position, string $gravity): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'position' => $position, 'upscale' => false])))
			->toBe(['rs:fit:300:200', "ex:1:{$gravity}", "g:{$gravity}", 'q:70']);
	})->with([
		['top-left', 'nowe'],
		['top-center', 'no'],
		['top-right', 'noea'],
		['center-left', 'we'],
		['center-right', 'ea'],
		['bottom-left', 'sowe'],
		['bottom-center', 'so'],
		['bottom-right', 'soea'],
	]);

	it('centers letterboxed images for an invalid position', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'position' => 'invalid', 'upscale' => false])))
			->toBe(['rs:fit:300:200', 'ex:1', 'q:70']);
	});

	it('positions letterboxed images by the position instead of the focal point', function (): void {
		$url = imageUrl(
			['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'position' => 'top-left', 'upscale' => false],
			['focalPoint' => ['x' => 0.25, 'y' => 0.75]],
		);

		expect(urlOptions($url))->toBe(['rs:fit:300:200', 'ex:1:nowe', 'g:fp:0.25:0.75', 'q:70']);
	});

	it('does not extend images in other modes', function (string $mode): void {
		expect(urlOptions(imageUrl(['mode' => $mode, 'width' => 300, 'height' => 200, 'position' => 'top-left'])))
			->not->toContain('ex:1:nowe');
	})->with(['crop', 'fit', 'stretch']);

	it('keeps a missing dimension when the size of the asset is unknown', function (array $transform, array $asset, string $resize): void {
		/** @var array<string,mixed> $transform */
		/** @var array<string,mixed> $asset */
		expect(urlOptions(imageUrl($transform, $asset)))->toBe([$resize, 'q:70']);
	})->with([
		'only a width' => [['width' => 300, 'upscale' => false], [], 'rs:fill-down:300:0'],
		'only a width and upscaling' => [['width' => 300, 'upscale' => true], [], 'rs:fill:300:0:1'],
		'only a height' => [['height' => 200, 'upscale' => false], [], 'rs:fill-down:0:200'],
		'only the width of the asset' => [['width' => 300, 'upscale' => false], ['width' => 800], 'rs:fill-down:300:0'],
		'only the height of the asset' => [['width' => 300, 'upscale' => false], ['height' => 600], 'rs:fill-down:300:0'],
	]);

	it('calculates a missing dimension from the size of the asset, like Craft', function (array $transform, string $resize): void {
		/** @var array<string,mixed> $transform */
		expect(urlOptions(imageUrl($transform, ['width' => 800, 'height' => 600])))->toBe([$resize, 'q:70']);
	})->with([
		'only a width' => [['width' => 300, 'upscale' => false], 'rs:fill-down:300:225'],
		'only a height' => [['height' => 150, 'upscale' => false], 'rs:fill-down:200:150'],
		'only a width and upscaling' => [['width' => 300, 'upscale' => true], 'rs:fill:300:225:1'],
		'only a width and stretching' => [['width' => 300, 'mode' => 'stretch'], 'rs:force:300:225:1'],
		'only a height and fitting' => [['height' => 150, 'mode' => 'fit', 'upscale' => false], 'rs:fit:200:150'],
		'both dimensions' => [['width' => 300, 'height' => 100, 'upscale' => false], 'rs:fill-down:300:100'],
	]);

	it('keeps a missing dimension when the asset knows only one dimension of its source', function (array $dimensions): void {
		expect(urlOptions(imageUrl(['width' => 300, 'upscale' => false], ['dimensions' => $dimensions])))
			->toBe(['rs:fill-down:300:0', 'q:70']);
	})->with([
		'only the width' => [[800, null]],
		'only the height' => [[null, 600]],
	]);

	it('uses the size of the source, not the size of a transform set on the asset', function (): void {
		$asset = Asset::image(['width' => 800, 'height' => 600]);
		$asset->setTransform(['width' => 300, 'height' => 100]);

		$url = Plugin::instance()->getBuilder()->getUrl($asset, new ImageTransform(['width' => 600, 'upscale' => false]));

		expect(urlOptions($url))->toBe(['rs:fill-down:600:450', 'q:70']);
	});

	it('calculates the dimension of letterboxes from the size of the asset', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'upscale' => false], ['width' => 100, 'height' => 50])))
			->toBe(['rs:fit:300:150', 'ex:1', 'q:70']);
	});

	it('does not enlarge letterboxed images without upscaling', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => false])))
			->toBe(['rs:fit:300:200', 'ex:1', 'q:70']);
	});
});

describe('gravity', function (): void {
	it('maps the position to a gravity', function (): void {
		expect(urlOptions(imageUrl(['position' => 'top-left'])))->toBe(['g:nowe', 'q:70']);
	});

	it('has no gravity for the center', function (): void {
		expect(urlOptions(imageUrl(['position' => 'center-center'])))->toBe(['q:70']);
	});

	it('uses the focal point of the asset', function (): void {
		expect(urlOptions(imageUrl(asset: ['focalPoint' => ['x' => 0.25, 'y' => 0.75]])))->toBe(['g:fp:0.25:0.75', 'q:70']);
	});

	it('prefers the focal point of the asset over the position', function (): void {
		$url = imageUrl(['position' => 'top-left'], ['focalPoint' => ['x' => 0.25, 'y' => 0.75]]);

		expect(urlOptions($url))->toBe(['g:fp:0.25:0.75', 'q:70']);
	});
});

describe('options', function (): void {
	it('maps a hexadecimal fill color to a background', function (): void {
		expect(urlOptions(imageUrl(['fill' => '#ff0000'])))->toBe(['bg:ff0000', 'q:70']);
	});

	it('has no background for other fill colors', function (): void {
		expect(urlOptions(imageUrl(['fill' => 'transparent'])))->toBe(['q:70']);
	});

	it('maps the quality', function (?int $quality, array $options): void {
		expect(urlOptions(imageUrl(['quality' => $quality])))->toBe($options);
	})->with([
		'lowest' => [1, ['q:1']],
		'highest' => [100, ['q:100']],
		'zero falls back to the default (the model drops it)' => [0, ['q:70']],
		'null falls back to the default' => [null, ['q:70']],
	]);

	it('maps the format', function (): void {
		expect(urlOptions(imageUrl(['format' => 'webp'])))->toBe(['q:70', 'f:webp']);
	});

	it('converts sources that browsers cannot display to JPEG, like Craft', function (string $filename): void {
		expect(urlOptions(imageUrl(asset: ['filename' => $filename])))->toBe(['q:70', 'f:jpg']);
	})->with(['image.heic', 'image.tif', 'image.tiff', 'image.bmp', 'IMAGE.HEIC']);

	it('converts sources that browsers cannot display to the fallback format', function (): void {
		$this->settings(['fallbackFormat' => 'webp']);

		expect(urlOptions(imageUrl(asset: ['filename' => 'image.heic'])))->toBe(['q:70', 'f:webp']);
	});

	it('keeps the source format of sources that browsers can display', function (string $filename): void {
		expect(urlOptions(imageUrl(asset: ['filename' => $filename])))->toBe(['q:70']);
	})->with(['image.jpg', 'image.jpeg', 'image.png', 'image.gif', 'image.webp', 'image.avif', 'image.svg']);

	it('prefers the format of the transform over the converted one', function (): void {
		expect(urlOptions(imageUrl(['format' => 'webp'], ['filename' => 'image.heic'])))->toBe(['q:70', 'f:webp']);
	});

	it('converts sources that browsers cannot display when the format is empty', function (): void {
		expect(urlOptions(imageUrl(['format' => ''], ['filename' => 'image.heic'])))->toBe(['q:70', 'f:jpg']);
	});

	it('leaves out an empty format', function (): void {
		expect(urlOptions(imageUrl(['format' => ''])))->toBe(['q:70']);
	});

	it('busts the cache with the date of the last modification', function (): void {
		$url = imageUrl(asset: ['dateModified' => new DateTime('2026-01-01 00:00:00')]);

		expect(urlOptions($url))->toBe(['q:70', 'cb:1767225600']);
	});

	it('combines options', function (): void {
		$url = imageUrl([
			'mode' => 'letterbox',
			'width' => 300,
			'height' => 200,
			'position' => 'bottom-right',
			'fill' => '#00ff00',
			'quality' => 75,
			'format' => 'avif',
			'upscale' => true,
		], [
			'dateModified' => new DateTime('2026-01-01 00:00:00'),
		]);

		expect(urlOptions($url))->toBe([
			'rs:fit:300:200:1',
			'ex:1:soea',
			'g:soea',
			'bg:00ff00',
			'q:75',
			'f:avif',
			'cb:1767225600',
		]);
	});
});

describe('asset URLs', function (): void {
	it('returns null without a transform', function (): void {
		expect(Plugin::instance()->getBuilder()->getAssetUrl(Asset::image(), null))->toBeNull();
	});

	it('returns null for an asset that is not supported', function (): void {
		expect(Plugin::instance()->getBuilder()->getAssetUrl(Asset::image(['filename' => 'document.pdf']), ['width' => 300]))->toBeNull();
	});
});

describe('thumbnail URLs', function (): void {
	it('returns null when the thumbnails are disabled', function (): void {
		$this->settings(['thumbnails' => false]);

		expect(Plugin::instance()->getBuilder()->getThumbnailUrl(Asset::image(), 120, 80))->toBeNull();
	});
});
