<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy;

use Craft;
use craft\base\Plugin as PluginBase;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\services\Assets;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\services\BuilderService;
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
				$url = $event->sender instanceof Asset
					? $this->getBuilder()->getAssetUrl($event->sender, $event->transform)
					: null;

				if ($url !== null) {
					$event->url = $url;
				}
			}
		);
	}

	private function registerThumbnailHandler(): void
	{
		Event::on(
			Assets::class,
			Assets::EVENT_DEFINE_THUMB_URL,
			function (DefineAssetThumbUrlEvent $event): void {
				$url = $this->getBuilder()->getThumbnailUrl($event->asset, $event->width, $event->height);

				if ($url !== null) {
					$event->url = $url;
				}
			}
		);
	}
}
