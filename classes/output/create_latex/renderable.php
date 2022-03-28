<?php
/**
 * Mod latex create latex teacher renderable
 *
 * @package    mod_latex
 * @copyright  2022
 * @author     Sergey Nechaev
 * @description Подготовка данных для визуализатора отрисовка блока по созданию задания
 */

namespace mod_latex\output\create_latex;

use renderer_base;

defined('MOODLE_INTERNAL') || die();

class renderable implements \renderable, \templatable {

	public function __construct() {
	}

	public function export_for_template(renderer_base $output): array {
		return [];
	}
}