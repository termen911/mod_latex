<?php

namespace mod_latex\external;

use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use JsonException;
use mod_latex\services\external_json;
use mod_latex\services\external_lib_custom;

class keyboard_configurations extends external_lib_custom {

	public const CONFIGS_DIR_PATH = '/mod/latex/configs';

	public static function get_configs_parameters(): external_function_parameters {
		return new external_function_parameters([]);
	}

	public static function get_configs_returns(): external_multiple_structure {
		return new external_multiple_structure(
			new external_single_structure([
				'keyboard' => new external_value(PARAM_TEXT, 'Keyboard key'),
				'layer' => new external_value(PARAM_TEXT, 'Layer'),
				'label' => new external_value(PARAM_TEXT, 'Label'),
				'tooltip' => new external_value(PARAM_TEXT, 'Tooltip'),
				'data' => new external_single_structure([
					"styles" => new external_value(PARAM_TEXT, 'styles'),
					"rows" => new external_json('', true, ''),
				]),
			])
		);
	}

	/**
	 * @throws JsonException
	 */
	public static function get_configs(): array {
		global $CFG;

		$configs = [];

		foreach (scandir($CFG->dirroot . self::CONFIGS_DIR_PATH) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$read = file_get_contents($CFG->dirroot . self::CONFIGS_DIR_PATH . "/{$item}");
			if (!self::isJson($read)) {
				continue;
			}
			$configs[] = json_decode($read, true, 512, JSON_THROW_ON_ERROR);
		}

		return $configs;
	}

	private static function isJson($string): bool {
		try {
			json_decode($string, false, 512, JSON_THROW_ON_ERROR);
			return json_last_error() === JSON_ERROR_NONE;
		} catch (JsonException $e) {
			return false;
		}
	}
}