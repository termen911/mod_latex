<?php

use mod_latex\forms\view_latex;
use mod_latex\lib\latex_submissions;
use mod_latex\services\layout;

require_once('../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE, $USER;

$id = required_param('id', PARAM_INT);
$active = optional_param('active', '', PARAM_TEXT);

$layout = layout::get_instance();
$layout->initialize($id, $active);

$course_context = context_course::instance($layout->get_course()->id);

if (!has_capability('mod/assign:view', $course_context)){
	throw new file_access_exception('В доступе к заданию, отказано!');
}

$PAGE->set_url(new moodle_url("/mod/latex/view.php", ['id' => $id]));
$PAGE->set_context(context_module::instance($id));
$PAGE->set_cm($layout->get_cm());
$PAGE->set_title($layout->get_latex()->name);

//Подключаем шрифт для математических формул
layout::init_latex_css();
//Инициируем отображение математической формулы
layout::init_latex_js([['id' => 'latex_task', 'value' => $layout->get_latex()->task, 'read' => true]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_latex/execution', $layout->get_latex());
$PAGE->requires->js_call_amd('mod_latex/main', 'initShowActions');

$latex_submissions = new latex_submissions();
if ($layout->is_edit()){
	$form = new view_latex(new moodle_url('/mod/latex/view.php', ['id' => $id]));

	if ($form->is_cancelled()) {
		redirect($PAGE->url);
	} else if ($rawData = $form->get_data()) {
		$answer = $latex_submissions->add_instance($rawData);
		\core\notification::add('Ответ на задание успешно сохранен', 'success');
		redirect($PAGE->url);
	} else {
		$form->display();
	}
}

echo $OUTPUT->footer();
