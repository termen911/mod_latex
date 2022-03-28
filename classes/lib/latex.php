<?php

namespace mod_latex\lib;

global $CFG;

use cm_info;
use coding_exception;
use context_course;
use context_module;
use core_completion\api as completion_api;
use dml_exception;
use mod_latex\exception\invalid_parameters_exception;
use moodle_exception;
use stdClass;

require_once($CFG->libdir . '/gradelib.php');

class latex {

	public const FILE_SAVE_COMPONENT = 'mod_latex';
	public const FILE_SAVE_AREA = 'latex_task';
	public const FILE_SAVE_AREA_SUBMISSIONS = 'latex_submissions';

	/** @var stdClass */
	private stdClass $instance;

	/** @var context_module */
	private context_module $context;

	/** @var stdClass */
	private stdClass $course;

	/** @var cm_info|null */
	private ?cm_info $cm;

	public function get_cm(): cm_info {
		return $this->cm;
	}

	/**
	 * @param cm_info|null $cm
	 * @throws coding_exception
	 */
	public function set_cm(?cm_info $cm): void {
		if ($cm instanceof cm_info) {
			$this->cm = $cm;
		} else if (($this->context instanceof context_module) && $this->context->contextlevel === CONTEXT_MODULE) {
			try {
				$this->cm = (get_fast_modinfo($this->get_course()))->get_cm($this->context->instanceid);
			} catch (moodle_exception $e) {
				$this->cm = cm_info::create(null);
			}
		} else {
			$this->cm = cm_info::create(null);
		}
	}

	/**
	 * @param stdClass|null $course
	 * @throws dml_exception|moodle_exception
	 */
	public function set_course(?stdClass $course): void {
		if ($course instanceof stdClass) {
			$this->course = $course;
		} else if ($this->context instanceof context_module) {
			$this->course = get_course($this->context->get_parent_context()->instanceid);
		} else if ($this->context instanceof context_course) {
			$this->course = get_course($this->context->instanceid);
		}

		if (!$this->course) {
			throw new moodle_exception("Не удалось определить курс для указанного задания!", 'mod_latex');
		}
	}

	public function get_course(): stdClass {
		return $this->course;
	}

	/**
	 * @param context_module $cm_context
	 * @param cm_info|null $cm
	 * @param stdClass|null $course
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	public function __construct(context_module $cm_context, ?cm_info $cm = null, ?stdClass $course = null) {
		$this->context = $cm_context;
		$this->set_course($course);
		$this->set_cm($cm);
	}

	/**
	 * @param stdClass $data
	 * @return int
	 * @throws invalid_parameters_exception
	 * @throws dml_exception
	 */
	public function add_instance(stdClass $data): int {
		global $DB;

		$base_create = $this->build_object_from_form($data);

		$return_id = $DB->insert_record('latex', $base_create);
		$this->instance = $DB->get_record('latex', ['id' => $return_id], '*', MUST_EXIST);
		$this->course = $this->get_course_by_id($data->course);

		$this->save_intro_draft_files($data);

		$latex_grade = latex_grade::get_instance();
		$latex_grade->course_id = (int) $this->instance->course;
		$latex_grade->item_instance = (int) $this->instance->id;
		$latex_grade->item_number = 0;
		$latex_grade->add_grade_item($this->instance->name);
		return (int) $return_id;
	}

	/**
	 * @param stdClass $data
	 * @return bool
	 * @throws dml_exception|invalid_parameters_exception
	 */
	public function update_instance(stdClass $data): bool {
		global $DB;
		$update = $this->build_object_from_form($data);
		$result = $DB->update_record('latex', $update);
		$this->instance = $DB->get_record('latex', ['id' => $update->id], '*', MUST_EXIST);
		$this->save_intro_draft_files($data);
		completion_api::update_completion_date_event($this->cm->id, 'latex', $this->instance, null);

		$latex_grade = latex_grade::get_instance();
		$latex_grade->course_id = (int) $this->instance->course;
		$latex_grade->item_instance = (int) $this->instance->id;
		$latex_grade->item_number = 0;
		$latex_grade->update_grade_grades();

		return $result;
	}

	/**
	 * @param $id
	 * @return bool
	 * @throws dml_exception
	 * @throws invalid_parameters_exception
	 */
	public function delete_instance($id): bool {
		global $DB;

		$result = true;

		$fs = get_file_storage();
		if (!$fs->delete_area_files($this->context->id)) {
			$result = false;
		}

		$DB->delete_records('latex_submissions', ['latex' => $id]);

		$latex_grade = latex_grade::get_instance();
		$latex_grade->course_id = (int) $this->get_course()->id;
		$latex_grade->item_instance = (int) $id;
		$latex_grade->item_number = 0;
		if (!$latex_grade->delete_grade_grades()) {
			$result = false;
		}

		$DB->delete_records('latex', ['id' => $id]);

		return $result;
	}

	/**
	 * @param stdClass $data
	 */
	protected function save_intro_draft_files(stdClass $data): void {
		if (isset($data->files)) {
			file_save_draft_area_files(
				$data->files,
				$this->context->id,
				self::FILE_SAVE_COMPONENT,
				self::FILE_SAVE_AREA,
				0
			);
		}
	}

	/**
	 * @param object $data
	 * @return stdClass
	 */
	private function build_object_from_form(object $data): stdClass {
		$result = new stdClass;
		if ((int) $data->instance) {
			$result->id = (int) $data->instance;
		}
		$result->name = $data->name;
		$result->intro = $data->intro;
		$result->course = $data->course;
		$result->introformat = $data->introformat;
		$result->date_start = $data->date_start;
		$result->date_end = $data->date_end;
		$result->answer = $data->answer;
		$result->task = $data->task;
		$result->isgrade = 0;
		$result->attemptnumber = $data->attemptnumber;
		$result->timecreate = $data->timecreate !== "" ? $data->timecreate : time();
		$result->timemodified = time();
		return $result;
	}

	/**
	 * @param int $id
	 * @return object|null
	 * @throws dml_exception
	 */
	public function get_course_by_id(int $id): ?object {
		global $DB;
		return $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
	}

	/**
	 * @return array
	 * @throws dml_exception
	 */
	public static function get_is_grated(): array {
		global $DB;
		$sql = "SELECT * FROM {latex} WHERE isgrade = 0 AND date_end < UNIX_TIMESTAMP()";
		return $DB->get_records_sql($sql);
	}

	/**
	 * @return string[]
	 */
	public static function get_mod_and_name(): array {
		return explode('_', self::FILE_SAVE_COMPONENT);
	}

	/**
	 * @param $id
	 * @return bool
	 * @throws dml_exception
	 */
	public static function set_is_grade($id): bool {
		global $DB;
		return $DB->set_field('latex', 'isgrade', '1', ['id' => $id]);
	}

	public static function get_users_in_courses(): array {

	}
}