<?php

declare(strict_types=1);

use SomehowDigital\Craft\ImgProxy\helpers\GravityHelper;
use SomehowDigital\ImgProxy\Option\GravityEnum;

it('maps a position to its gravity', function (string $position, GravityEnum $gravity): void {
	expect(GravityHelper::mapPositionToGravity($position))->toBe($gravity);
})->with([
	['top-left', GravityEnum::NORTH_WEST],
	['top-center', GravityEnum::NORTH],
	['top-right', GravityEnum::NORTH_EAST],
	['center-left', GravityEnum::WEST],
	['center-center', GravityEnum::CENTER],
	['center-right', GravityEnum::EAST],
	['bottom-left', GravityEnum::SOUTH_WEST],
	['bottom-center', GravityEnum::SOUTH],
	['bottom-right', GravityEnum::SOUTH_EAST],
]);

it('falls back to the center for an unknown position', function (): void {
	expect(GravityHelper::mapPositionToGravity('unknown'))->toBe(GravityEnum::CENTER);
});
