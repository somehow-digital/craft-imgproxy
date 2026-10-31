<?php

use SomehowDigital\Craft\ImgProxy\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * The processing options of an imgproxy URL.
 *
 * @return string[]
 */
function urlOptions(string $url): array
{
	return array_slice(urlSegments($url), 1, -1);
}

/**
 * The signature of an imgproxy URL.
 */
function urlSignature(string $url): string
{
	return urlSegments($url)[0];
}

/**
 * The source URL of an imgproxy URL.
 */
function urlSource(string $url): string
{
	$segments = urlSegments($url);

	return (string) base64_decode(strtr(end($segments), '-_', '+/'));
}

/**
 * The path of an imgproxy URL, which is what gets signed, without the signature.
 */
function urlPath(string $url): string
{
	return implode('/', array_slice(urlSegments($url), 1));
}

/**
 * The segments of the path of an imgproxy URL.
 *
 * @return string[]
 */
function urlSegments(string $url): array
{
	return explode('/', ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
}
