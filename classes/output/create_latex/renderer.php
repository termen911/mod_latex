<?php
/**
 * Mod latex create latex teacher renderer
 *
 * @package    mod_latex
 * @copyright  2022
 * @author     Sergey Nechaev
 * @description Визуализатор для отрисовка блока по созданию задания
 */

namespace mod_latex\output\create_latex;

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

class renderer extends plugin_renderer_base {
	public function render_renderable(renderable $controller) {
		return $this->render_from_template('mod_latex/create_latex/edit', $controller->export_for_template($this));
	}
}