<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;

final class Settings extends Model
{
	public ?string $endpoint = null;

	public ?string $signatureKey = null;

	public ?string $signatureSalt = null;

	/**
	 * Whether the endpoint and the signature are usable, so the plugin can replace the URLs of Craft.
	 */
	public function isConfigured(): bool
	{
		return $this->resolve($this->endpoint) !== '' && $this->getSignatureErrors() === [];
	}

	/**
	 * @internal Validation rule.
	 */
	public function validateSignature(string $attribute): void
	{
		if (isset($this->getSignatureErrors()[$attribute])) {
			$this->addError($attribute, $this->getSignatureErrors()[$attribute]);
		}
	}

	/**
	 * @return list<array{0:list<string>,1:string}>
	 */
	protected function defineRules(): array
	{
		return [
			[['endpoint', 'signatureKey', 'signatureSalt'], 'trim'],
			[['signatureKey', 'signatureSalt'], 'validateSignature'],
		];
	}

	/**
	 * Checks the resolved values without touching the model, so it is safe to call for every URL.
	 *
	 * @return array<string,string> the error message of every invalid attribute.
	 */
	private function getSignatureErrors(): array
	{
		$values = [
			'signatureKey' => $this->resolve($this->signatureKey),
			'signatureSalt' => $this->resolve($this->signatureSalt),
		];

		$errors = [];

		foreach ($values as $attribute => $value) {
			if ($value === '') {
				continue;
			}

			if (!ctype_xdigit($value) || strlen($value) % 2 !== 0) {
				$errors[$attribute] = Craft::t('imgproxy', '{attribute} must be a hexadecimal string.', [
					'attribute' => $this->getAttributeLabel($attribute),
				]);
			} elseif (in_array('', $values, true)) {
				$errors[$attribute] = Craft::t('imgproxy', 'The signature key and salt must be set together.');
			}
		}

		return $errors;
	}

	/**
	 * Resolves environment variables and aliases.
	 */
	private function resolve(?string $value): string
	{
		$resolved = App::parseEnv($value);

		return is_string($resolved) ? $resolved : '';
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
