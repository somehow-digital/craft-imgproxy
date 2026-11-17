<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;

final class Settings extends Model
{
	/**
	 * imgproxy serves the transforms, so their URLs point to it.
	 */
	public const MODE_PROXY = 'proxy';

	/**
	 * imgproxy generates the transforms, which Craft stores and serves like its own.
	 */
	public const MODE_STORE = 'store';

	private const FORMATS = ['jpg', 'png', 'webp', 'avif'];

	private const MODES = [self::MODE_PROXY, self::MODE_STORE];

	public ?string $endpoint = null;

	public ?string $signatureKey = null;

	public ?string $signatureSalt = null;

	/**
	 * How imgproxy transforms the images, `proxy` or `store`.
	 */
	public string $mode = self::MODE_PROXY;

	/**
	 * Whether imgproxy generates the image transforms, or Craft does.
	 */
	public bool|string $transforms = true;

	/**
	 * The format for sources that browsers can't display, such as HEIC and TIFF, when the transform has none.
	 */
	public string $fallbackFormat = 'jpg';

	/**
	 * The format of the thumbnails of the control panel.
	 */
	public string $thumbnailFormat = 'webp';

	/**
	 * Whether imgproxy generates the thumbnails of the control panel, or they are handled like the image transforms.
	 */
	public bool|string $thumbnails = true;

	/**
	 * Whether the endpoint and the signature are usable, so the plugin can replace the URLs of Craft.
	 */
	public function isConfigured(): bool
	{
		return $this->getEndpointError() === null && $this->getSignatureErrors() === [];
	}

	/**
	 * The endpoint with environment variables and aliases resolved.
	 */
	public function getEndpoint(): string
	{
		return $this->resolve($this->endpoint);
	}

	/**
	 * The signature key with environment variables and aliases resolved.
	 */
	public function getSignatureKey(): string
	{
		return $this->resolve($this->signatureKey);
	}

	/**
	 * The signature salt with environment variables and aliases resolved.
	 */
	public function getSignatureSalt(): string
	{
		return $this->resolve($this->signatureSalt);
	}

	/**
	 * The resolved mode, or `proxy` when it is invalid.
	 */
	public function getMode(): string
	{
		return $this->getSupported($this->mode, self::MODES) ?? self::MODE_PROXY;
	}

	/**
	 * Whether imgproxy generates the image transforms. An unset environment variable counts as enabled.
	 */
	public function isTransformsEnabled(): bool
	{
		return App::parseBooleanEnv($this->transforms) ?? true;
	}

	/**
	 * Whether imgproxy generates the thumbnails of the control panel. An unset environment variable counts as enabled.
	 */
	public function isThumbnailsEnabled(): bool
	{
		return App::parseBooleanEnv($this->thumbnails) ?? true;
	}

	/**
	 * @internal Validation rule.
	 */
	public function validateEndpoint(string $attribute): void
	{
		$error = $this->getEndpointError();

		if ($error !== null) {
			$this->addError($attribute, $error);
		}
	}

	/**
	 * The resolved fallback format, or JPEG when it is invalid.
	 */
	public function getFallbackFormat(): string
	{
		return $this->getSupported($this->fallbackFormat, self::FORMATS) ?? 'jpg';
	}

	/**
	 * The resolved thumbnail format, or WebP when it is invalid.
	 */
	public function getThumbnailFormat(): string
	{
		return $this->getSupported($this->thumbnailFormat, self::FORMATS) ?? 'webp';
	}

	/**
	 * @internal Validation rule.
	 */
	public function validateFormat(string $attribute): void
	{
		$value = $attribute === 'thumbnailFormat' ? $this->thumbnailFormat : $this->fallbackFormat;

		$this->validateSupported($attribute, $value, self::FORMATS);
	}

	/**
	 * @internal Validation rule.
	 */
	public function validateMode(string $attribute): void
	{
		$this->validateSupported($attribute, $this->mode, self::MODES);
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
			[['endpoint', 'signatureKey', 'signatureSalt', 'mode', 'fallbackFormat', 'thumbnailFormat'], 'trim'],
			[['endpoint'], 'validateEndpoint'],
			[['mode'], 'validateMode'],
			[['fallbackFormat', 'thumbnailFormat'], 'validateFormat'],
			[['signatureKey', 'signatureSalt'], 'validateSignature'],
		];
	}

	/**
	 * Checks the resolved endpoint without touching the model, so it is safe to call for every URL.
	 */
	private function getEndpointError(): ?string
	{
		$endpoint = $this->getEndpoint();

		if (filter_var($endpoint, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $endpoint) === 1) {
			return null;
		}

		return Craft::t('imgproxy', '{attribute} must be an HTTP or HTTPS URL.', [
			'attribute' => $this->getAttributeLabel('endpoint'),
		]);
	}

	/**
	 * Checks the resolved values without touching the model, so it is safe to call for every URL.
	 *
	 * @return array<string,string> the error message of every invalid attribute.
	 */
	private function getSignatureErrors(): array
	{
		$values = [
			'signatureKey' => $this->getSignatureKey(),
			'signatureSalt' => $this->getSignatureSalt(),
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
	 * @param list<string> $supported
	 */
	private function validateSupported(string $attribute, string $value, array $supported): void
	{
		if ($this->getSupported($value, $supported) === null) {
			$this->addError($attribute, Craft::t('imgproxy', '{attribute} must be one of: {values}.', [
				'attribute' => $this->getAttributeLabel($attribute),
				'values' => implode(', ', $supported),
			]));
		}
	}

	/**
	 * The resolved value in lowercase, or null when it is not supported.
	 *
	 * @param list<string> $supported
	 */
	private function getSupported(string $value, array $supported): ?string
	{
		$value = strtolower($this->resolve($value));

		return in_array($value, $supported, true) ? $value : null;
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
					'mode',
					'transforms',
					'fallbackFormat',
					'thumbnailFormat',
					'thumbnails',
				],
			],
		];
	}
}
