<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\Tests\PHPStan;

use craft\base\Model;
use craft\base\Plugin;
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use yii\base\Component;

/**
 * What the classes of each layer extend and how they are named. Dependencies between layers are in deptrac.yaml.
 */
final class ArchitectureRules
{
	private const PLUGIN = 'SomehowDigital\Craft\ImgProxy';

	private const TESTS = self::PLUGIN . '\Tests';

	/**
	 * Layer namespaces with their base class and name suffix.
	 *
	 * Nested layer namespaces are excluded from their parent's rules.
	 *
	 * @var array<non-empty-string,array{base:?class-string,suffix:?non-empty-string}>
	 */
	private const LAYERS = [
		'helpers' => ['base' => null, 'suffix' => 'Helper'],
		'models' => ['base' => null, 'suffix' => null],
		'services' => ['base' => Component::class, 'suffix' => 'Service'],
	];

	/** Created by Yii from config, so the plugin registers rather than constructs them. */
	private const COMPONENTS = [
		'services',
	];

	/**
	 * Layer classes extend their Craft or Yii base class and carry its suffix.
	 *
	 * @return iterable<string,Rule>
	 */
	#[TestRule]
	public function layerClassesExtendTheirBaseAndCarryItsName(): iterable
	{
		foreach (self::LAYERS as $namespace => ['base' => $base, 'suffix' => $suffix]) {
			if ($base !== null) {
				yield " ({$namespace} extend)" => PHPat::rule()
					->classes($this->layer($namespace))
					->excluding(Selector::isInterface(), Selector::isTrait(), Selector::isEnum(), ...$this->nestedIn($namespace))
					->should()->extend()
					->classes(Selector::classname($base))
					->because("The classes of {$namespace} are {$base} classes, which Craft and Yii know how to create and run.");
			}

			if ($suffix !== null) {
				yield " ({$namespace} named)" => PHPat::rule()
					->classes($this->layer($namespace))
					->excluding(Selector::isInterface(), Selector::isTrait(), Selector::isEnum(), ...$this->nestedIn($namespace))
					->should()->beNamed('/' . $suffix . '$/', true)
					->because("The name of a class of {$namespace} ends with {$suffix}, so it tells what it is wherever it is used.");
			}
		}
	}

	#[TestRule]
	public function theRootHoldsOnlyThePlugin(): Rule
	{
		return PHPat::rule()
			->classes($this->root())
			->excluding(Selector::classname(self::PLUGIN . '\Plugin'))
			->shouldNot()->exist()
			->because('Projects and Craft reference the plugin by its name, so only it is at the root, where it never moves.');
	}

	#[TestRule]
	public function thePluginIsACraftPlugin(): Rule
	{
		return PHPat::rule()
			->classes(Selector::classname(self::PLUGIN . '\Plugin'))
			->should()->extend()
			->classes(Selector::classname(Plugin::class))
			->because('The plugin is the class that Craft loads the plugin through.');
	}

	#[TestRule]
	public function everyClassLivesInTheNamespaceOfALayer(): Rule
	{
		return PHPat::rule()
			->classes($this->plugin())
			->excluding($this->root(), ...array_map($this->layer(...), array_keys(self::LAYERS)))
			->shouldNot()->exist()
			->because('A class belongs to a layer, whose namespace deptrac.yaml defines the dependencies of.');
	}

	#[TestRule]
	public function concreteClassesAreFinal(): Rule
	{
		return PHPat::rule()
			->classes($this->plugin())
			->excluding(Selector::isAbstract(), Selector::isInterface(), Selector::isTrait(), Selector::isEnum())
			->should()->beFinal()
			->because('Classes are composed rather than extended, so a change to one cannot break a class extending it.');
	}

	#[TestRule]
	public function modelsAreCraftModelsOrImmutableValueObjects(): Rule
	{
		return PHPat::rule()
			->classes($this->layer('models'))
			->excluding(Selector::extends(Model::class))
			->should()->beReadonly()
			->because('A model either validates input as a Craft model, or holds data that nothing changes once it is created.');
	}

	#[TestRule]
	public function helpersAreNotConstructed(): Rule
	{
		return PHPat::rule()
			->classes(Selector::all())
			->shouldNot()->construct()
			->classes($this->layer('helpers'))
			->because('Helpers are stateless functions, which are called statically, as Craft does with its helpers.');
	}

	#[TestRule]
	public function componentsAreNotConstructed(): Rule
	{
		return PHPat::rule()
			->classes(Selector::all())
			->shouldNot()->construct()
			->classes(...array_map($this->layer(...), self::COMPONENTS))
			->because('Yii creates the components from their config, so they can be configured and replaced, rather than constructed by whoever uses them.');
	}

	private function plugin(): SelectorInterface
	{
		return Selector::AllOf(Selector::inNamespace(self::PLUGIN), Selector::Not(Selector::inNamespace(self::TESTS)));
	}

	/**
	 * Classes outside any sub-namespace.
	 */
	private function root(): SelectorInterface
	{
		return Selector::inNamespace('/^' . preg_quote(self::PLUGIN, '/') . '$/', true);
	}

	/**
	 * @param non-empty-string $namespace
	 */
	private function layer(string $namespace): SelectorInterface
	{
		return Selector::inNamespace(self::PLUGIN . '\\' . $namespace);
	}

	/**
	 * Other layers nested in the namespace.
	 *
	 * @param non-empty-string $namespace
	 * @return list<SelectorInterface>
	 */
	private function nestedIn(string $namespace): array
	{
		$nested = array_filter(array_keys(self::LAYERS), static fn(string $other): bool => str_starts_with($other, $namespace . '\\'));

		return array_values(array_map($this->layer(...), $nested));
	}
}
