<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests;

use Craft;
use craft\helpers\ArrayHelper;
use craft\services\Config;
use craft\web\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;
use SomehowDigital\Craft\ImgProxy\models\Settings;
use SomehowDigital\Craft\ImgProxy\Plugin;
use SomehowDigital\Craft\ImgProxy\Tests\Support\Asset;
use SomehowDigital\Craft\ImgProxy\Tests\Support\Plugins;
use SomehowDigital\Craft\ImgProxy\Tests\Support\WarningException;
use yii\base\Event;
use yii\caching\ArrayCache;
use yii\di\Container;

/**
 * Boots a fresh Craft app with the plugin for each test, without a database.
 */
abstract class TestCase extends BaseTestCase
{
	/** @var string[] names of the environment variables set by the test */
	private array $variables = [];

	protected function setUp(): void
	{
		parent::setUp();

		$previous = set_error_handler(null);
		restore_error_handler();
		set_error_handler(static function (int $level, string $message, string $file = '', int $line = 0) use ($previous): bool {
			// Craft's Twig nodes aren't `yield`-ready; not ours to fix.
			if (str_contains($message, 'YieldReady') && str_contains($message, 'craft\\web\\twig\\nodes\\')) {
				return true;
			}

			// Fail on warnings everywhere, including mutants, which PHPUnit doesn't treat as plugin code.
			if (($level & (E_WARNING | E_USER_WARNING)) !== 0 && (error_reporting() & $level) !== 0) {
				throw new WarningException($message, 0, $level, $file, $line);
			}

			return $previous !== null && (bool) $previous($level, $message, $file, $line);
		}, E_USER_DEPRECATED | E_WARNING | E_USER_WARNING);

		Craft::$container = new Container();

		$this->createApp();
	}

	protected function tearDown(): void
	{
		foreach ($this->variables as $name) {
			unset($_SERVER[$name]);
		}

		Event::offAll();
		// @phpstan-ignore assign.propertyType (Yii types the app as always set)
		Craft::$app = null;
		Craft::$container = new Container();

		restore_error_handler();

		parent::tearDown();
	}

	protected function createApp(): Application
	{
		$src = CRAFT_VENDOR_PATH . '/craftcms/cms/src';

		$configService = new Config();
		$configService->appType = 'web';
		$configService->env = 'test';
		$configService->configDir = Craft::getAlias('@config');
		$configService->appDefaultsDir = "{$src}/config/defaults";

		/** @var array{class:class-string<Application>} $config */
		$config = ArrayHelper::merge(
			['vendorPath' => CRAFT_VENDOR_PATH, 'env' => 'test', 'components' => ['config' => $configService]],
			require "{$src}/config/app.php",
			require "{$src}/config/app.web.php",
			[
				'isInstalled' => false,
				'components' => [
					'log' => ['targets' => []],
					'cache' => ['class' => ArrayCache::class],
					'plugins' => ['class' => Plugins::class],
				],
			],
		);

		$app = Craft::createObject($config);

		// Tests run on the command line, which requests take for console requests.
		$app->getRequest()->setIsConsoleRequest(false);

		$this->createPlugin();

		return $app;
	}

	protected function createPlugin(): Plugin
	{
		/** @var Plugin $plugin */
		// @phpstan-ignore argument.type (Craft's stub only knows the `class` key)
		$plugin = Craft::createObject([
			'class' => Plugin::class,
			'basePath' => dirname(__DIR__) . '/src',
			// Like Craft, merges the plugin's config.
			...Plugin::config(),
		], ['imgproxy', Craft::$app]);

		Plugin::setInstance($plugin);
		Craft::$app->setModule('imgproxy', $plugin);

		/** @var Plugins $plugins */
		$plugins = Craft::$app->getPlugins();
		$plugins->install($plugin);

		$this->settings(['endpoint' => 'https://imgproxy.example.test']);

		return $plugin;
	}

	/**
	 * An image asset, without a date of its last modification unless given.
	 *
	 * @param array<string,mixed> $config
	 */
	protected function asset(array $config = []): Asset
	{
		return Asset::image($config);
	}

	/**
	 * Configures the plugin.
	 *
	 * @param array<mixed> $values
	 */
	protected function settings(array $values): void
	{
		/** @var Settings $settings */
		$settings = Plugin::instance()->getSettings();
		$settings->setAttributes($values, false);
	}

	/**
	 * Sets environment variables, which are removed again after the test.
	 *
	 * @param array<string,string> $variables
	 */
	protected function env(array $variables): void
	{
		foreach ($variables as $name => $value) {
			$_SERVER[$name] = $value;
			$this->variables[] = $name;
		}
	}
}
