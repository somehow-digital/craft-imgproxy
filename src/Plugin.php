<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy;

use Craft;
use craft\base\Plugin as PluginBase;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\helpers\ImageTransforms;
use craft\models\ImageTransform;
use craft\services\Assets;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\services\BuilderService;
use SomehowDigital\ImgProxy\Utility\Format;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Registers the builder and replaces the URLs of assets, and their thumbnails, with imgproxy URLs.
 *
 * @method Settings getSettings()
 */
final class Plugin extends PluginBase
{
	public bool $hasCpSettings = true;

	/**
	 * @return array{components:array<string,array{class:class-string}>}
	 */
	public static function config(): array
	{
		return [
			'components' => [
				'builder' => ['class' => BuilderService::class],
			],
		];
	}

	/**
	 * @throws InvalidConfigException when the plugin is not loaded.
	 */
	public static function instance(): self
	{
		return self::getInstance() ?? throw new InvalidConfigException('The imgproxy plugin is not loaded.');
	}

	public function init(): void
	{
		parent::init();

		$this->registerAliases();
		$this->registerAssetHandler();
		$this->registerThumbnailHandler();
	}

	public function getBuilder(): BuilderService
	{
		/** @var BuilderService */
		return $this->get('builder');
	}

	protected function createSettingsModel(): Settings
	{
		return new Settings();
	}

	protected function settingsHtml(): string
	{
		return Craft::$app->getView()->renderTemplate(
			'imgproxy/settings/plugin',
			['settings' => $this->getSettings()]
		);
	}

	private function registerAliases(): void
	{
		Craft::setAlias('@imgproxy', $this->getBasePath());
	}

	private function registerAssetHandler(): void
	{
		Event::on(
			Asset::class,
			Asset::EVENT_BEFORE_DEFINE_URL,
			function (DefineAssetUrlEvent $event): void {
				$asset = $event->sender;

				if (
					!$this->getSettings()->isConfigured()
					|| !$asset instanceof Asset
					|| !$this->isFetchable($asset)
				) {
					return;
				}

				try {
					$transform = ImageTransforms::normalizeTransform($event->transform);
				} catch (ImageTransformException) {
					// Craft raises this itself, unless the asset can't be transformed anyway.
					return;
				}

				if (!$transform instanceof ImageTransform) {
					return;
				}

				$event->url = $this->getBuilder()->getUrl($asset, $transform);
			}
		);
	}

	private function registerThumbnailHandler(): void
	{
		Event::on(
			Assets::class,
			Assets::EVENT_DEFINE_THUMB_URL,
			function (DefineAssetThumbUrlEvent $event): void {
				if (
					!$this->getSettings()->isConfigured()
					|| !$this->isFetchable($event->asset)
				) {
					return;
				}

				$transform = new ImageTransform([
					'width' => $event->width,
					'height' => $event->height,
					'format' => 'webp',
				]);

				$event->url = $this->getBuilder()->getUrl($event->asset, $transform);
			}
		);
	}

	/**
	 * Whether imgproxy can fetch the asset, which needs a supported format and a public absolute URL.
	 */
	private function isFetchable(Asset $asset): bool
	{
		return Format::supports($asset->getExtension())
			&& preg_match('#^https?://#i', (string) $asset->getVolume()->getRootUrl()) === 1;
	}
}
