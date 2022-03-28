<?php

namespace mod_latex\forms;

use coding_exception;
use context_module;
use mod_latex\lib\latex;
use mod_latex\services\layout;
use MoodleQuickForm;

trait create_latex {

	private MoodleQuickForm $form;

	private string $js_main_module = 'mod_latex/main';

	private string $prefix = 'latex';

	private array $fields_init_latex = [
		'task',
		'answer'
	];

	/**
	 * @throws coding_exception
	 */
	public function definition(): void {
		$this->add_hidden_fields();
		$this->add_name();

		$this->standard_intro_elements('BlaBlaBla');

		$this->add_date_start();
		$this->add_date_end();
		$this->add_header('assignment');
		$this->add_task_latex();
		$this->add_answer_latex();
		$this->add_file_latex();
		$this->add_number_attempt();
		$this->standard_coursemodule_elements();
		$this->add_action_buttons();
		$this->set_data_local();
		$this->init_js();
		layout::init_latex_css();
	}

	protected function add_hidden_fields(): void {
		$element_name = "timecreate";
		$this->_form->addElement('hidden', $element_name);
		$this->_form->setType($element_name, PARAM_RAW);
		$element_name = "is_update";
		$this->_form->addElement('hidden', $element_name);
		$this->_form->setType($element_name, PARAM_RAW);
	}

	protected function add_header(string $key): void {
		$this->_form->addElement('header', $key, get_string($key, 'mod_latex'));
	}

	protected function add_name(): void {
		$this->_form->addElement('text', "name", "Название");
		$this->_form->setType("name", PARAM_TEXT);
		$this->_form->addRule('name', get_string('required'), 'required', null, 'client');
	}

	protected function add_date_start(): void {
		$options = [
			'startyear' => date("Y"),
			'optional' => true,
			'timezone' => 3
		];
		$this->_form->addElement('date_time_selector', "date_start", "Доступно с ", $options);
	}

	protected function add_date_end(): void {
		$options = [
			'startyear' => date("Y"),
			'optional' => true,
			'timezone' => 3
		];
		$this->_form->addElement('date_time_selector', "date_end", "Доступно до ", $options);
		$this->_form->addRule("date_end", null, 'required', null, 'client');
	}

	protected function add_task_latex(): void {
		$this->_form->addElement('text', "task", "Задание", ['class' => 'hidden-input']);
		$this->_form->setType("task", PARAM_TEXT);
		$this->_form->addRule("task", null, 'required', null, 'client');
	}

	protected function add_answer_latex(): void {
		$this->_form->addElement('text', "answer", "Ответ на задание", ['class' => 'hidden-input']);
		$this->_form->setType("answer", PARAM_TEXT);
		$this->_form->addRule("answer", null, 'required', null, 'client');
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

		$this->_form->addElement('filemanager', $element_name, 'files_latex', null, $options);
	}

	protected function add_number_attempt(): void {

		$options = [];
		while (true) {
			$count = count($options);
			if ($count > 10) {
				break;
			}
			$options[] = $count;
		}

		$this->_form->addElement('select', "attemptnumber", "Количество попыток", $options);
		$this->_form->setType("attemptnumber", PARAM_INT);
	}

	protected function set_data_local(): void {
		global $USER;

		$draft_item_id = file_get_submitted_draft_itemid(latex::FILE_SAVE_AREA);

		if ($this->current && $this->current->coursemodule) {
			$cm = get_coursemodule_from_instance(
				'latex',
				$this->current->id,
				$this->current->course,
				false,
				MUST_EXIST
			);
			$context = context_module::instance($cm->id);

			file_prepare_draft_area(
				$draft_item_id,
				$context->id,
				latex::FILE_SAVE_COMPONENT,
				latex::FILE_SAVE_AREA,
				0,
				['subdirs' => 0]
			);
		}

		$this->set_data([
			"is_update" => $this->get_value("is_update"),
			"name" => $this->get_value("name"),
			"intro" => ['text' => $this->get_value("intro"), 'format' => $this->get_value("introformat")],
			"date_start" => $this->get_value("date_start"),
			"date_end" => $this->get_value("date_end"),
			"task" => $this->get_value("task"),
			"answer" => $this->get_value("answer"),
			"attemptnumber" => $this->get_value("attemptnumber"),
			"timecreate" => $this->get_value("timecreate"),
			"files" => $draft_item_id,
		]);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function get_value(string $key): string {
		$key = str_replace("", '', $key);
		if ($this->current && isset($this->current->{$key})) {
			return $this->current->{$key};
		}

		switch ($key) {
			case "attemptnumber":
				$default = '0';
				break;
			case "date_end":
				$default = (string) (time() + 3600 * 24);
				break;
			case "is_update":
				$default = !empty($this->current->id);
				break;
			default:
				$default = '';
				break;
		}

		return $default;
	}

	protected function init_js(): void {
		global $PAGE;
		$params = [];
		foreach ($this->fields_init_latex as $item) {
			$params[] = [
				'id' => $item,
				'value' => $this->_form->getElementValue($item)
			];
		}

		layout::init_latex_js($params);
		$PAGE->requires->js_call_amd($this->js_main_module, 'actionsOnDates');
		$PAGE->requires->js_call_amd($this->js_main_module, 'initUpdate');
	}
}