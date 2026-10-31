<?php

use craft\models\ImageTransform;
use SomehowDigital\Craft\ImgProxy\Plugin;

/**
 * The imgproxy URL of an asset for a transform.
 *
 * @param array<string,mixed> $transform
 * @param array<string,mixed> $asset
 */
function imageUrl(array $transform = [], array $asset = []): string
{
	return Plugin::getInstance()->getBuilder()->getUrl(test()->asset($asset), new ImageTransform($transform));
}

describe('endpoint', function () {
	it('prepends the endpoint', function () {
		expect(imageUrl())->toStartWith('https://imgproxy.example.test/_/');
	});

	it('removes a trailing slash from the endpoint', function () {
		$this->settings(['endpoint' => 'https://imgproxy.example.test/']);

		expect(imageUrl())->toStartWith('https://imgproxy.example.test/_/');
	});

	it('reads the endpoint from an environment variable', function () {
		$this->env(['IMGPROXY_ENDPOINT' => 'https://environment.example.test']);
		$this->settings(['endpoint' => '$IMGPROXY_ENDPOINT']);

		expect(imageUrl())->toStartWith('https://environment.example.test/_/');
	});
});

describe('source', function () {
	it('encodes the URL of the asset', function () {
		expect(urlSource(imageUrl()))->toBe('https://assets.example.test/image.jpg');
	});

	it('includes the folder of the asset', function () {
		expect(urlSource(imageUrl(asset: ['folderPath' => 'photos/2026/'])))
			->toBe('https://assets.example.test/photos/2026/image.jpg');
	});

	it('encodes special characters of the filename', function () {
		expect(urlSource(imageUrl(asset: ['filename' => 'my image.jpg'])))
			->toBe('https://assets.example.test/my%20image.jpg');
	});
});

describe('signature', function () {
	$key = bin2hex('secret');
	$salt = bin2hex('salt');

	it('leaves URLs unsigned without a key and a salt', function () {
		expect(urlSignature(imageUrl()))->toBe('_');
	});

	it('leaves URLs unsigned with only a key or a salt', function (array $settings) {
		$this->settings($settings);

		expect(urlSignature(imageUrl()))->toBe('_');
	})->with([
		'key' => [['signatureKey' => $key]],
		'salt' => [['signatureSalt' => $salt]],
	]);

	it('signs URLs with the key and the salt', function () use ($key, $salt) {
		$this->settings(['signatureKey' => $key, 'signatureSalt' => $salt]);

		$url = imageUrl(['width' => 300, 'height' => 200]);
		$digest = hash_hmac('sha256', 'salt' . urlPath($url), 'secret', true);

		expect(urlSignature($url))->toBe(rtrim(strtr(base64_encode($digest), '+/', '-_'), '='));
	});

	it('reads the key and the salt from environment variables', function () use ($key, $salt) {
		$this->env(['IMGPROXY_KEY' => $key, 'IMGPROXY_SALT' => $salt]);
		$this->settings(['signatureKey' => '$IMGPROXY_KEY', 'signatureSalt' => '$IMGPROXY_SALT']);

		expect(urlSignature(imageUrl()))->not->toBe('_');
	});
});

describe('resize', function () {
	it('has no resize option without dimensions', function () {
		expect(urlOptions(imageUrl()))->toBe([]);
	});

	it('maps the mode to a resizing type', function (string $mode, string $type) {
		expect(urlOptions(imageUrl(['mode' => $mode, 'width' => 300, 'height' => 200, 'upscale' => false])))
			->toBe(["rs:$type:300:200"]);
	})->with([
		['crop', 'fill'],
		['fit', 'fit'],
		['stretch', 'force'],
	]);

	it('enlarges images when upscaling', function () {
		expect(urlOptions(imageUrl(['width' => 300, 'height' => 200, 'upscale' => true])))
			->toBe(['rs:fill:300:200:1']);
	});

	it('fits and extends images for letterboxing', function () {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => true])))
			->toBe(['rs:fit:300:200:1:1']);
	});

	it('resizes with only a width', function () {
		expect(urlOptions(imageUrl(['width' => 300, 'upscale' => false])))->toBe(['rs:fill:300:0']);
	});

	it('keeps the position of the height when upscaling with only a width', function () {
		expect(urlOptions(imageUrl(['width' => 300, 'upscale' => true])))->toBe(['rs:fill:300:0:1']);
	});

	it('keeps the position of the width with only a height', function () {
		expect(urlOptions(imageUrl(['height' => 200, 'upscale' => false])))->toBe(['rs:fill:0:200']);
	});

	it('keeps the position of the extend option when letterboxing without upscaling', function () {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => false])))
			->toBe(['rs:fit:300:200:0:1']);
	});
});

describe('gravity', function () {
	it('maps the position to a gravity', function () {
		expect(urlOptions(imageUrl(['position' => 'top-left'])))->toBe(['g:nowe']);
	});

	it('has no gravity for the center', function () {
		expect(urlOptions(imageUrl(['position' => 'center-center'])))->toBe([]);
	});

	it('uses the focal point of the asset', function () {
		expect(urlOptions(imageUrl(asset: ['focalPoint' => ['x' => 0.25, 'y' => 0.75]])))->toBe(['g:fp:0.25:0.75']);
	});

	it('prefers the focal point of the asset over the position', function () {
		$url = imageUrl(['position' => 'top-left'], ['focalPoint' => ['x' => 0.25, 'y' => 0.75]]);

		expect(urlOptions($url))->toBe(['g:fp:0.25:0.75']);
	});
});

describe('options', function () {
	it('maps a hexadecimal fill color to a background', function () {
		expect(urlOptions(imageUrl(['fill' => '#ff0000'])))->toBe(['bg:ff0000']);
	});

	it('has no background for other fill colors', function () {
		expect(urlOptions(imageUrl(['fill' => 'transparent'])))->toBe([]);
	});

	it('maps the quality', function () {
		expect(urlOptions(imageUrl(['quality' => 80])))->toBe(['q:80']);
	});

	it('maps the format', function () {
		expect(urlOptions(imageUrl(['format' => 'webp'])))->toBe(['f:webp']);
	});

	it('busts the cache with the date of the last modification', function () {
		$url = imageUrl(asset: ['dateModified' => new DateTime('2026-01-01 00:00:00')]);

		expect(urlOptions($url))->toBe(['cb:1767225600']);
	});

	it('combines options', function () {
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
			'rs:fit:300:200:1:1',
			'g:soea',
			'bg:00ff00',
			'q:75',
			'f:avif',
			'cb:1767225600',
		]);
	});
});
