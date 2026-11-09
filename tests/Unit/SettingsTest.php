<?php

declare(strict_types=1);

use craft\behaviors\EnvAttributeParserBehavior;
use SomehowDigital\Craft\ImgProxy\models\Settings;

it('has no values by default', function (): void {
	$settings = new Settings();

	expect($settings->endpoint)->toBeNull();
	expect($settings->signatureKey)->toBeNull();
	expect($settings->signatureSalt)->toBeNull();
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
	expect($parser instanceof EnvAttributeParserBehavior ? $parser->attributes : null)->toBe(['endpoint', 'signatureKey', 'signatureSalt']);
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
