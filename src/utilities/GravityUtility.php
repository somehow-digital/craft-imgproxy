<?php

namespace SomehowDigital\Craft\ImgProxy\utilities;

use SomehowDigital\ImgProxy\Option\GravityEnum;

class GravityUtility
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
