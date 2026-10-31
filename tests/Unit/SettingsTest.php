<?php

use SomehowDigital\Craft\ImgProxy\models\Settings;

it('has no values by default', function () {
	$settings = new Settings();

	expect($settings->endpoint)->toBeNull();
	expect($settings->signatureKey)->toBeNull();
	expect($settings->signatureSalt)->toBeNull();
});

it('trims its values', function () {
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

it('keeps references to environment variables', function () {
	$settings = new Settings(['endpoint' => '$IMGPROXY_ENDPOINT']);

	expect($settings->validate())->toBeTrue();
	expect($settings->endpoint)->toBe('$IMGPROXY_ENDPOINT');
});
