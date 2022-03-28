<?php

namespace mod_latex\services;

use coding_exception;
use completion_info;
use context_module;
use core_user;
use dml_exception;
use file_storage;
use grade_grade;
use grade_item;
use mod_latex\lib\latex;
use mod_latex\lib\latex_submissions;
use moodle_exception;
use moodle_url;
use stdClass;

global $CFG;

require_once("$CFG->libdir/completionlib.php");

class layout {

	use singleton;

	public const IS_EDIT = 'edit';

	private int $id;
	private string $active;
	private file_storage $file_storage;
	private ?stdClass $cm;
	private stdClass $course;
	private stdClass $latex;
	/**
	 * @var stdClass|null
	 */
	private ?stdClass $submissions;
	private context_module $context;

	private bool $is_teacher = false;

	/**
	 * @return bool
	 */
	public function is_teacher(): bool {
		return $this->is_teacher;
	}

	/**
	 * @throws coding_exception
	 */
	public function set_is_teacher(): void {
		$this->is_teacher = has_capability('mod/assign:addinstance', $this->context->get_course_context());
	}

	/**
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function get_active(): int {
		return $this->active;
	}

	/**
	 * @return stdClass|null
	 */
	public function get_cm(): ?stdClass {
		return $this->cm;
	}

	/**
	 * @return stdClass
	 */
	public function get_course(): stdClass {
		return $this->course;
	}

	/**
	 * @return stdClass
	 */
	public function get_latex(): stdClass {
		return $this->latex;
	}

	/**
	 * @return stdClass|null
	 */
	public function get_submissions(): ?stdClass {
		return $this->submissions;
	}

	/**
	 * @return int
	 */
	public function get_submissions_id(): int {
		if (!empty($this->submissions->id)) {
			return $this->submissions->id;
		}
		return 0;
	}

	/**
	 * @return bool
	 */
	public function is_edit(): bool {
		return $this->active === self::IS_EDIT;
	}

	/**
	 * @return context_module
	 */
	public function get_context(): context_module {
		return $this->context;
	}

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	public function initialize(int $_id, string $_active): void {
		global $DB;

		$this->id = $_id;
		$this->active = $_active;
		$this->file_storage = get_file_storage();

		$this->context = context_module::instance($this->get_id());
		$this->set_is_teacher();

		if (!$this->cm = get_coursemodule_from_id('latex', $this->get_id())) {
			throw new moodle_exception("Не удалось найти модуль курса для указанного задания!", 'mod_latex');
		}

		if (!$this->course = $DB->get_record('course', ['id' => $this->get_cm()->course])) {
			throw new moodle_exception("Не удалось найти курс для указанного задания!", 'mod_latex');
		}

		if (!$this->latex = $DB->get_record('latex', ['id' => $this->get_cm()->instance])) {
			throw new moodle_exception("Не удалось найти задание!", 'mod_latex');
		}

		$this->submissions = latex_submissions::get_instance($this->latex->id);

		$this->set_completion_view();

		$this->prepare();
	}

	protected function set_completion_view(): void {
		global $USER;
		if ($this->active === '') {
			$completion = new completion_info($this->get_course());
			$data = new stdClass();
			$data->id = false;
			$data->coursemoduleid = $this->get_id();
			$data->userid = $USER->id;
			$data->completionstate = 0;
			$data->viewed = 1;
			$data->overrideby = null;
			$data->timemodified = time();
			$completion->internal_set_data($this->get_cm(), $data);
		}
	}

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	protected function prepare(): void {
		$this->get_attached_files();
		$this->rebuild_intro();
		$this->convert_date($this->latex->date_end_convert, (int) $this->latex->date_end);
		$this->is_attempt();
		$this->get_grades_info();

		//Режим редактирования
		$this->latex->is_edit = $this->is_edit();
		$this->latex->link_edit = new moodle_url("/mod/latex/view.php", ['id' => $this->get_id(), 'active' => self::IS_EDIT]);
		$this->latex->link_text = is_null($this->submissions) ? 'Дать ответ ответ' : 'Изменить ответ';
		//Информация об ответе пользователя
		$this->latex->submissions = is_null($this->submissions) ? false : $this->submissions;
		if ($this->latex->submissions) {
			$this->submissions->files = $this->get_submissions_file();
		}

		$this->show_submissions_list();
	}

	/**
	 * @throws coding_exception
	 */
	protected function get_attached_files(): void {
		$this->latex->files = [];
		//Получаем прикрепленные файлы к заданию
		$files = $this->file_storage->get_area_files(
			$this->context->id,
			'mod_latex',
			latex::FILE_SAVE_AREA,
			0,
			'timemodified',
			false
		);
		foreach ($files as $file) {
			//Формируем адреса для каждого файла
			$this->latex->files[] = [
				'url' => moodle_url::make_pluginfile_url(
					$file->get_contextid(),
					$file->get_component(),
					$file->get_filearea(),
					$file->get_itemid(),
					$file->get_filepath(),
					$file->get_filename()
				),
				'name' => $file->get_filename()
			];
		}
	}

	/**
	 * @param null $id
	 * @return array
	 * @throws coding_exception
	 */
	protected function get_submissions_file($id = null): array {
		$_files = [];
		if (!is_null($this->submissions) || $id) {
			if (is_null($id)) {
				$this->submissions->files = [];
			}

			$files = $this->file_storage->get_area_files(
				$this->context->id,
				'mod_latex',
				latex::FILE_SAVE_AREA_SUBMISSIONS,
				$id ?? $this->submissions->id,
				'timemodified',
				false
			);

			foreach ($files as $file) {
				//Формируем адреса для каждого файла
				$_files[] = [
					'url' => moodle_url::make_pluginfile_url(
						$file->get_contextid(),
						$file->get_component(),
						$file->get_filearea(),
						(int) $file->get_itemid() === 0 ? null : $file->get_itemid(),
						$file->get_filepath(),
						$file->get_filename()
					),
					'name' => $file->get_filename()
				];
			}

		}
		return $_files;
	}

	protected function rebuild_intro(): void {
		//перестраиваем отображения информации по адресам в intro
		$this->latex->intro = file_rewrite_pluginfile_urls(
			$this->latex->intro,
			'pluginfile.php',
			$this->context->id,
			'mod_latex',
			'intro',
			null
		);
	}

	/**
	 * @throws coding_exception
	 */
	protected function convert_date(?string &$set_field, int $value): void {
		//Форматируем дату
		$format = get_string('strftimedatetimeshort', 'core_langconfig');
		$set_field = userdate($value, $format, 3);
	}

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	private function is_attempt(): void {
		$this->latex->attempt_time_end = false;
		if ((int) $this->latex->date_end < time()) {
			$this->latex->attempt_time_end = true;
		}
		if ((int) $this->latex->attemptnumber === 0) {
			$this->latex->is_attempt = true;
			$this->latex->attempt_info = 'Не ограничено';
		} else {
			$attemptsleft = (int) $this->latex->attemptnumber - latex_submissions::get_count_attachments($this->get_id());
			$this->latex->is_attempt = $attemptsleft !== 0;
			$this->latex->attempt_info = $attemptsleft;
		}
		if ($this->is_teacher()) {
			$this->latex->is_attempt = true;
			$this->latex->attempt_info = 'Не ограничено (Вы учитель на данном курсе)';
		}
	}

	/**
	 * @throws coding_exception
	 */
	private function get_grades_info(): void {
		global $USER;

		$params = [
			'courseid' => $this->get_course()->id,
			'itemtype' => 'mod',
			'itemmodule' => 'latex',
			'iteminctance' => $this->get_latex()->id,
			'userid' => $USER->id,
		];
		$grade_item = new grade_item($params);

		$params['itemid'] = $grade_item->id;
		$grade_grade = new grade_grade($params);

		$this->latex->grade = new stdClass();
		$this->latex->grade->is_show = !$this->latex->is_attempt || $this->latex->attempt_time_end;
		$this->latex->grade->is_grade = !is_null($grade_grade->finalgrade);
		$this->latex->grade->grade = (int) $grade_grade->finalgrade;
		$this->latex->grade->feedback = $grade_grade->feedback;
		$this->latex->grade->grade_max = (int) $grade_grade->get_grade_max();
		$this->latex->grade->is_change = (int) $grade_grade->rawgrade !== (int) $grade_grade->finalgrade;
		$this->latex->grade->grade_set_time = $this->latex->date_end + (600);
		$this->convert_date($this->latex->grade->grade_set_time_convert, (int) $this->latex->grade->grade_set_time);
	}

	private function show_submissions_list() {
		if ($this->is_teacher()) {
			$this->latex->is_teacher = 1;

			$roles_id = get_archetype_roles('student');
			$roles_id = array_keys($roles_id);

			$params = [
				'courseid' => $this->get_course()->id,
				'itemtype' => 'mod',
				'itemnumber' => 0,
				'iteminstance' => $this->get_latex()->id,
				'itemmodule' => 'latex',
			];
			$grade_item = grade_item::fetch($params);
			$this->latex->link_edit_grade = new moodle_url('/grade/report/singleview/index.php', [
				'id' => $this->get_course()->id,
				'item' => 'grade',
				'group' => '',
				'itemid' => $grade_item->id,
			]);

			$users_roles = enrol_get_course_users_roles($this->get_course()->id);
			$all_submissions = latex_submissions::get_instance($this->latex->id, true);

			$_res = [];
			foreach ($users_roles as $roles) {
				foreach ($roles as $key => $user) {

					if (!in_array($key, $roles_id, true)) {
						continue;
					}

					$params = [
						'itemid' => $grade_item->id,
						'userid' => $user->userid,
					];

					$grade_grade = grade_grade::fetch($params);

					$_data = new stdClass();
					$_data->user_id = $user->userid;
					$_data->user_name = fullname(core_user::get_user($user->userid));
					$_data->user_profile = new moodle_url('/user/profile.php', ['id' => $user->userid]);
					$_data->answer = 'Нет ответа';
					$_data->answer_step = 'Нет шагов решения';
					$_data->grade = (int)$grade_grade->finalgrade;
					$_data->is_grade = !is_null($grade_grade->finalgrade);
					$_data->grade_max = (int)$grade_grade->get_grade_max();
					$_data->files = [];
					$_data->date = '-';

					$array_key = time();

					foreach ($all_submissions as $all_submission) {
						if ((int) $user->userid === (int) $all_submission->userid) {
							$array_key = $all_submission->timemodified;
							$_data->answer = $all_submission->answer;
							$_data->answer_step = $all_submission->answeraction;
							$_data->files = $this->get_submissions_file($all_submission->id);
							$this->convert_date($_data->date, $all_submission->timemodified);
						}
					}
					$_res[$array_key] = $_data;
				}
			}
			ksort($_res);
			$this->latex->submissions_list = array_values($_res);
		}

	}

	public static function init_latex_css(): void {
		global $PAGE;
		$PAGE->requires->css_theme(new moodle_url('/mod/latex/scss/mathlive-fonts.css'));
		$PAGE->requires->css_theme(new moodle_url('/mod/latex/scss/latex.css'));
	}

	public static function init_latex_js(array $fields_id): void {
		global $PAGE;

		foreach ($fields_id as $item) {
			$params = ["#id_{$item['id']}", $item['value'], empty($item['read']) ? false : $item['read']];
			$PAGE->requires->js_call_amd('mod_latex/main', 'initLatex', $params);
		}
	}

}