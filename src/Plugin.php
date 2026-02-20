<?php

namespace SomehowDigital\Craft\ImgProxy;

use Craft;
use craft\base\Model;
use craft\base\Plugin as PluginBase;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\models\ImageTransform;
use craft\services\Assets;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\services\Builder;
use SomehowDigital\ImgProxy\Utility\Format;
use yii\base\Event;

class Plugin extends PluginBase
{
	public bool $hasCpSettings = true;

	public function init(): void
	{
		parent::init();

		$this->registerAliases();
		$this->registerComponents();
		$this->registerAssetHandler();
		$this->registerThumbnailHandler();
	}

	public function getBuilder(): Builder
	{
		return $this->get('builder');
	}

	protected function createSettingsModel(): ?Model
	{
		return new Settings();
	}

	protected function settingsHtml(): ?string
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

	private function registerComponents(): void
	{
		$this->setComponents([
			'builder' => [
				'class' => Builder::class,
			],
		]);
	}

	private function registerAssetHandler(): void
	{
		Event::on(
			Asset::class,
			Asset::EVENT_BEFORE_DEFINE_URL,
			function (DefineAssetUrlEvent $event) {
				$transform = $event->transform;
				$asset = $event->sender;

				if (
					!$transform ||
					!Format::supports($asset->getExtension())
				) {
					return;
				}

				if (is_string($transform)) {
					$transform = Craft::$app->getImageTransforms()->getTransformByHandle($transform);
				}

				if (is_array($transform)) {
					$transform = new ImageTransform($transform);
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
			function (DefineAssetThumbUrlEvent $event) {
				if (!Format::supports($event->asset->getExtension())) {
					return;
				}

				$transform = new ImageTransform([
					'width' => $event->width,
					'height' => $event->height,
					'format' => 'webp',
					'quality' => Craft::$app->getConfig()->getGeneral()->defaultImageQuality,
				]);

				$event->url = $this->getBuilder()->getUrl($event->asset, $transform);
			}
		);
	}
}
