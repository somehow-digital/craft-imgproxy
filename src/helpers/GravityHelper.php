<?php

declare(strict_types=1);

namespace SomehowDigital\Craft\ImgProxy\helpers;

use SomehowDigital\ImgProxy\Option\GravityEnum;

/**
 * @internal
 */
final class GravityHelper
{
	public static function mapPositionToGravity(string $position): GravityEnum
	{
		return match ($position) {
			'top-left' => GravityEnum::NORTH_WEST,
			'top-center' => GravityEnum::NORTH,
			'top-right' => GravityEnum::NORTH_EAST,
			'center-left' => GravityEnum::WEST,
			'center-right' => GravityEnum::EAST,
			'bottom-left' => GravityEnum::SOUTH_WEST,
			'bottom-center' => GravityEnum::SOUTH,
			'bottom-right' => GravityEnum::SOUTH_EAST,
			default => GravityEnum::CENTER,
		};
	}
}
