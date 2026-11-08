<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

final class Settings extends Model
{
	public ?string $endpoint = null;

	public ?string $signatureKey = null;

	public ?string $signatureSalt = null;

	/**
	 * @return list<array{0:list<string>,1:string}>
	 */
	protected function defineRules(): array
	{
		return [
			[['endpoint', 'signatureKey', 'signatureSalt'], 'trim'],
		];
	}

	/**
	 * @return array{parser:array{class:class-string,attributes:list<string>}}
	 */
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
