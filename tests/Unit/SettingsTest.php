<?php

declare(strict_types=1);

use craft\behaviors\EnvAttributeParserBehavior;
use SomehowDigital\Craft\ImgProxy\models\Settings;

it('has no values by default', function (): void {
	$settings = new Settings();

	expect($settings->endpoint)->toBeNull();
	expect($settings->signatureKey)->toBeNull();
	expect($settings->signatureSalt)->toBeNull();
	expect($settings->fallbackFormat)->toBe('jpg');
	expect($settings->thumbnailFormat)->toBe('webp');
	expect($settings->thumbnails)->toBeTrue();
});

it('trims its values', function (): void {
	$settings = new Settings([
		'endpoint' => ' https://imgproxy.example.test ',
		'signatureKey' => ' 736563726574 ',
		'signatureSalt' => ' 73616c74 ',
	]);

	expect($settings->validate())->toBeTrue();
	expect($settings->endpoint)->toBe('https://imgproxy.example.test');
	expect($settings->signatureKey)->toBe('736563726574');
	expect($settings->signatureSalt)->toBe('73616c74');
});

it('parses environment variables in all of its values', function (): void {
	$parser = (new Settings())->getBehavior('parser');

	expect($parser)->toBeInstanceOf(EnvAttributeParserBehavior::class);
	expect($parser instanceof EnvAttributeParserBehavior ? $parser->attributes : null)->toBe(['endpoint', 'signatureKey', 'signatureSalt', 'fallbackFormat', 'thumbnailFormat', 'thumbnails']);
});

it('keeps references to environment variables', function (): void {
	$settings = new Settings(['endpoint' => '$IMGPROXY_ENDPOINT']);

	expect($settings->validate())->toBeTrue();
	expect($settings->endpoint)->toBe('$IMGPROXY_ENDPOINT');
});

it('validates the signature', function (string $key, string $salt, array $invalid): void {
	$settings = new Settings(['signatureKey' => $key, 'signatureSalt' => $salt]);
	$settings->validate();

	expect($settings->hasErrors('signatureKey'))->toBe(in_array('signatureKey', $invalid, true));
	expect($settings->hasErrors('signatureSalt'))->toBe(in_array('signatureSalt', $invalid, true));
})->with([
	'both hexadecimal' => ['736563726574', '73616c74', []],
	'both empty' => ['', '', []],
	'key not hexadecimal' => ['secret', '73616c74', ['signatureKey']],
	'salt not hexadecimal' => ['736563726574', 'salt', ['signatureSalt']],
	'odd length' => ['736', '73616c74', ['signatureKey']],
	'only the key' => ['736563726574', '', ['signatureKey']],
	'only the salt' => ['', '73616c74', ['signatureSalt']],
]);

it('validates the endpoint', function (string $endpoint, bool $valid): void {
	$settings = new Settings(['endpoint' => $endpoint]);
	$settings->validate();

	expect($settings->hasErrors('endpoint'))->toBe(!$valid);
})->with([
	'https' => ['https://imgproxy.example.test', true],
	'http with a port and a path' => ['http://localhost:8080/imgproxy', true],
	'without a scheme' => ['imgproxy.example.test', false],
	'relative' => ['/imgproxy', false],
	'another scheme' => ['ftp://imgproxy.example.test', false],
	'not a URL' => ['https://', false],
]);

it('does not validate an empty endpoint', function (): void {
	$settings = new Settings(['endpoint' => '']);

	expect($settings->validate())->toBeTrue();
});

it('validates the resolved endpoint', function (): void {
	$this->env(['IMGPROXY_ENDPOINT' => 'imgproxy']);
	$settings = new Settings(['endpoint' => '$IMGPROXY_ENDPOINT']);

	expect($settings->validate())->toBeFalse();
	expect($settings->getFirstError('endpoint'))->toBe('Endpoint must be an HTTP or HTTPS URL.');
});

it('is not configured with an environment variable that is not set', function (): void {
	$settings = new Settings(['endpoint' => '$IMGPROXY_UNSET_ENDPOINT']);

	expect($settings->validate())->toBeTrue();
	expect($settings->isConfigured())->toBeFalse();
});

it('keeps its errors when checking the configuration', function (): void {
	$settings = new Settings(['endpoint' => 'https://imgproxy.example.test', 'signatureKey' => 'secret']);
	$settings->validate();
	$settings->isConfigured();

	expect($settings->hasErrors('signatureKey'))->toBeTrue();
});

it('resolves the endpoint and the signature', function (): void {
	$this->env(['IMGPROXY_ENDPOINT' => 'https://imgproxy.example.test', 'IMGPROXY_KEY' => '736563726574', 'IMGPROXY_SALT' => '73616c74']);
	$settings = new Settings(['endpoint' => '$IMGPROXY_ENDPOINT', 'signatureKey' => '$IMGPROXY_KEY', 'signatureSalt' => '$IMGPROXY_SALT']);

	expect($settings->getEndpoint())->toBe('https://imgproxy.example.test');
	expect($settings->getSignatureKey())->toBe('736563726574');
	expect($settings->getSignatureSalt())->toBe('73616c74');
});

it('resolves unset values to empty strings', function (): void {
	$settings = new Settings();

	expect($settings->getEndpoint())->toBe('');
	expect($settings->getSignatureKey())->toBe('');
	expect($settings->getSignatureSalt())->toBe('');
});

it('is configured with an endpoint and a valid signature', function (string $endpoint, string $key, string $salt, bool $configured): void {
	expect((new Settings(['endpoint' => $endpoint, 'signatureKey' => $key, 'signatureSalt' => $salt]))->isConfigured())->toBe($configured);
})->with([
	'unsigned' => ['https://imgproxy.example.test', '', '', true],
	'signed' => ['https://imgproxy.example.test', '736563726574', '73616c74', true],
	'no endpoint' => ['', '', '', false],
	'invalid endpoint' => ['imgproxy.example.test', '', '', false],
	'invalid signature' => ['https://imgproxy.example.test', 'secret', '73616c74', false],
]);

it('names the invalid attribute in its error', function (): void {
	$settings = new Settings(['signatureKey' => 'secret', 'signatureSalt' => '73616c74']);
	$settings->validate();

	expect($settings->getFirstError('signatureKey'))->toBe('Signature Key must be a hexadecimal string.');
});

it('validates the fallback format', function (string $format, bool $valid, string $resolved): void {
	$settings = new Settings(['fallbackFormat' => $format]);

	expect($settings->validate())->toBe($valid);
	expect($settings->getFallbackFormat())->toBe($resolved);
})->with([
	'jpg' => ['jpg', true, 'jpg'],
	'png' => ['png', true, 'png'],
	'webp' => ['webp', true, 'webp'],
	'avif' => ['avif', true, 'avif'],
	'uppercase' => [' WEBP ', true, 'webp'],
	'unsupported' => ['heic', false, 'jpg'],
	'empty' => ['', true, 'jpg'],
]);

it('explains which fallback formats are supported', function (): void {
	$settings = new Settings(['fallbackFormat' => 'heic']);
	$settings->validate();

	expect($settings->getFirstError('fallbackFormat'))->toBe('Fallback Format must be one of: jpg, png, webp, avif.');
});

it('enables thumbnails', function (bool|string $value, ?string $variable, bool $enabled): void {
	if ($variable !== null) {
		$this->env(['IMGPROXY_THUMBNAILS' => $variable]);
	}

	expect((new Settings(['thumbnails' => $value]))->isThumbnailsEnabled())->toBe($enabled);
})->with([
	'by default' => [true, null, true],
	'disabled' => [false, null, false],
	'disabled as a string' => ['0', null, false],
	'enabled by a variable' => ['$IMGPROXY_THUMBNAILS', '1', true],
	'disabled by a variable' => ['$IMGPROXY_THUMBNAILS', '0', false],
	'an unset variable' => ['$IMGPROXY_THUMBNAILS_UNSET', null, true],
]);

it('validates the thumbnail format', function (string $format, bool $valid, string $resolved): void {
	$settings = new Settings(['thumbnailFormat' => $format]);

	expect($settings->validate())->toBe($valid);
	expect($settings->getThumbnailFormat())->toBe($resolved);
})->with([
	'webp' => ['webp', true, 'webp'],
	'avif' => ['avif', true, 'avif'],
	'jpg' => ['jpg', true, 'jpg'],
	'png' => ['png', true, 'png'],
	'uppercase' => [' AVIF ', true, 'avif'],
	'unsupported' => ['heic', false, 'webp'],
	'empty' => ['', true, 'webp'],
]);

it('names the invalid format attribute in its error', function (): void {
	$settings = new Settings(['thumbnailFormat' => 'heic']);
	$settings->validate();

	expect($settings->getFirstError('thumbnailFormat'))->toBe('Thumbnail Format must be one of: jpg, png, webp, avif.');
});
