<?php

declare(strict_types=1);

use craft\services\Images;
use SomehowDigital\Craft\ImgProxy\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * The processing options of an imgproxy URL.
 *
 * @return list<string>
 */
function urlOptions(?string $url): array
{
	return array_slice(urlSegments($url), 1, -1);
}

/**
 * The signature of an imgproxy URL.
 */
function urlSignature(?string $url): string
{
	return urlSegments($url)[0];
}

/**
 * The source URL of an imgproxy URL.
 */
function urlSource(?string $url): string
{
	$segments = urlSegments($url);

	$source = base64_decode(strtr((string) end($segments), '-_', '+/'), true);

	return $source === false ? '' : $source;
}

/**
 * The path of an imgproxy URL, which is what gets signed, without the signature.
 */
function urlPath(?string $url): string
{
	return implode('/', array_slice(urlSegments($url), 1));
}

/**
 * The segments of the path of an imgproxy URL.
 *
 * @return list<string>
 */
function urlSegments(?string $url): array
{
	if ($url === null) {
		throw new LogicException('The asset has no URL.');
	}

	return explode('/', ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
}

/**
 * Makes the formats the only ones Craft's image driver can open, whichever driver the host has.
 *
 * @param list<string> $formats
 */
function imageFormats(array $formats): void
{
	Craft::$app->set('images', new class ($formats) extends Images {
		/**
		 * @param list<string> $formats
		 */
		public function __construct(
			private readonly array $formats,
		) {
			parent::__construct();
		}

		/**
		 * @return list<string>
		 */
		public function getSupportedImageFormats(): array
		{
			return $this->formats;
		}
	});
}
