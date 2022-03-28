<?php

namespace mod_latex\services;

class helper {

	/**
	 * @param mixed $value
	 * @param false $die
	 */
	public static function dump($value, bool $die = false): void {
		echo "<pre>";
		print_r($value);
		echo "</pre>";
		!$die ?: die();
	}
}