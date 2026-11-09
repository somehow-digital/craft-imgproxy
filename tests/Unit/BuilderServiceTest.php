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

	it('maps the mode to a resizing type', function (string $mode, string $type): void {
		expect(urlOptions(imageUrl(['mode' => $mode, 'width' => 300, 'height' => 200, 'upscale' => false])))
			->toBe(["rs:{$type}:300:200", 'q:70']);
	})->with([
		['crop', 'fill'],
		['fit', 'fit'],
		['stretch', 'force'],
	]);

	it('enlarges images when upscaling', function (): void {
		expect(urlOptions(imageUrl(['width' => 300, 'height' => 200, 'upscale' => true])))
			->toBe(['rs:fill:300:200:1', 'q:70']);
	});

	it('fits and extends images for letterboxing', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => true])))
			->toBe(['rs:fit:300:200:1:1', 'q:70']);
	});

	it('resizes with only a width', function (): void {
		expect(urlOptions(imageUrl(['width' => 300, 'upscale' => false])))->toBe(['rs:fill:300:0', 'q:70']);
	});

	it('keeps the position of the height when upscaling with only a width', function (): void {
		expect(urlOptions(imageUrl(['width' => 300, 'upscale' => true])))->toBe(['rs:fill:300:0:1', 'q:70']);
	});

	it('keeps the position of the width with only a height', function (): void {
		expect(urlOptions(imageUrl(['height' => 200, 'upscale' => false])))->toBe(['rs:fill:0:200', 'q:70']);
	});

	it('keeps the position of the extend option when letterboxing without upscaling', function (): void {
		expect(urlOptions(imageUrl(['mode' => 'letterbox', 'width' => 300, 'height' => 200, 'upscale' => false])))
			->toBe(['rs:fit:300:200:0:1', 'q:70']);
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
			'rs:fit:300:200:1:1',
			'g:soea',
			'bg:00ff00',
			'q:75',
			'f:avif',
			'cb:1767225600',
		]);
	});
});
