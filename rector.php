<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->withRootFiles()
	// Targets the lowest PHP version in composer.json.
	->withPhpSets()
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		codingStyle: true,
		typeDeclarations: true,
		typeDeclarationDocblocks: true,
		privatization: true,
		instanceOf: true,
		earlyReturn: true,
		rectorPreset: true,
		phpunitCodeQuality: true,
	)
	->withComposerBased(phpunit: true)
	->withAttributesSets(phpunit: true)
	->withSkip([
		// Composer keys its loaders by resolved paths, which `..` doesn't match.
		DirnameDirConcatStringToDirectStringPathRector::class,
		// ECS handles member spacing.
		NewlineBetweenClassLikeStmtsRector::class,
	])
	->withImportNames(removeUnusedImports: true)
	->withParallel();
