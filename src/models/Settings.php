<?php

namespace SomehowDigital\Craft\ImgProxy\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

class Settings extends Model
{
	public ?string $endpoint = null;
	public ?string $signatureKey = null;
	public ?string $signatureSalt = null;

	public function defineRules(): array
	{
		return [
			[['endpoint', 'signatureKey', 'signatureSalt'], 'string'],
			[['endpoint', 'signatureKey', 'signatureSalt'], 'trim'],
		];
	}

	protected function defineBehaviors(): array
	{
		return [
			'parser' => [
				'class' => EnvAttributeParserBehavior::class,
				'attributes' => [
					'endpoint',
					'signatureKey',
					'signatureSalt',
				],
			],
		];
	}
}
