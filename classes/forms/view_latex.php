<?php

namespace mod_latex\forms;

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

use coding_exception;
use dml_exception;
use mod_latex\lib\latex;
use mod_latex\lib\latex_submissions;
use mod_latex\services\layout;
use moodle_exception;
use moodleform;
use MoodleQuickForm;

class view_latex extends moodleform {

	private MoodleQuickForm $form;
	private layout $layout;
	private array $init_js;

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	protected function definition(): void {
		$this->form = $this->_form;

		$this->layout = layout::get_instance();
		$answer = latex_submissions::get_latest_value();

		$this->hidden_fields('id', $this->layout->get_id());
		$this->hidden_fields('active', layout::IS_EDIT);
		$this->hidden_fields('answeraction', $this->add_answer_action());

		$this->add_file_latex();
		$this->add_solution_latex();
		$this->add_answer_latex();

		$this->current_data();

		$this->add_action_buttons('Закрыть', $answer === "" ? 'Отправить ответ' : 'Изменить ответ');
		$this->init_js[] = ['id' => 'answer', 'value' => $answer];

		layout::init_latex_js($this->init_js);
	}

	/**
	 * @param string $id
	 * @param string $value
	 */
	private function hidden_fields(string $id, string $value): void {
		$this->form->addElement('hidden', $id);
		$this->form->setType($id, PARAM_RAW);
		$this->form->setDefault($id, $value);
	}

	protected function add_answer_latex(): void {
		$this->form->addElement('text', "answer", "Ответ на задание", ['class' => 'hidden-input']);
		$this->form->setType("answer", PARAM_TEXT);
		$this->form->addRule("answer", null, 'required', null, 'client');
	}

	protected function add_file_latex(): void {
		$options = [
			'subdirs' => 0,
			'maxfiles' => EDITOR_UNLIMITED_FILES,
			'maxbytes' => get_config('moodlecourse', 'maxbytes'),
			'accepted_types' => [],
			'return_types' => FILE_INTERNAL | FILE_EXTERNAL
		];

		$element_name = "files";

		$this->_form->addElement('filemanager', $element_name, 'Решение в виде файла', null, $options);
	}

	protected function add_solution_latex(): void {
		global $PAGE, $OUTPUT;
		$this->form->addElement('html', $OUTPUT->render_from_template('mod_latex/components/steps-container', []));
		$PAGE->requires->js_call_amd('mod_latex/main', 'initSteps', [$this->add_answer_action()]);
	}

	/**
	 * @return string
	 */
	private function add_answer_action(): string {
		$answer_action = '';
		if (!empty($this->layout->get_submissions()) && !empty($this->layout->get_submissions()->answeraction)) {
			$answer_action = $this->layout->get_submissions()->answeraction;
		}
		return $answer_action;
	}

	private function current_data(): void {
		global $PAGE;

		$draft_item_id = file_get_submitted_draft_itemid(latex::FILE_SAVE_AREA_SUBMISSIONS);

		file_prepare_draft_area(
			$draft_item_id,
			$this->layout->get_context()->id,
			latex::FILE_SAVE_COMPONENT,
			latex::FILE_SAVE_AREA_SUBMISSIONS,
			$this->layout->get_submissions_id(),
			['subdirs' => 0]
		);

		$answer = '';
		if (!is_null($this->layout->get_submissions())) {
			$answer = $this->layout->get_submissions()->answer;
		}

		$this->set_data([
			'answer' => $answer,
			'files' => $draft_item_id
		]);
	}
}