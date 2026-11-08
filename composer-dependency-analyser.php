<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
	// Craft loads `Craft` itself.
	->ignoreUnknownClasses(['Craft'])
	// Required by Craft.
	->ignoreErrorsOnPackage('yiisoft/yii2', [ErrorType::SHADOW_DEPENDENCY])
	// Required by Pest.
	->ignoreErrorsOnPackageAndPath('phpunit/phpunit', __DIR__ . '/tests/TestCase.php', [ErrorType::SHADOW_DEPENDENCY])
	// Bundled with PHPStan.
	->ignoreErrorsOnPackageAndPath('nikic/php-parser', __DIR__ . '/tests/PHPStan', [ErrorType::SHADOW_DEPENDENCY]);
