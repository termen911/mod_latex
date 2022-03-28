<?php

namespace mod_latex\lib;

global $CFG;
require_once("{$CFG->libdir}/enrollib.php");
require_once("{$CFG->libdir}/gradelib.php");

use coding_exception;
use dml_exception;
use mod_latex\exception\invalid_parameters_exception;
use mod_latex\services\layout;
use moodle_exception;
use moodle_url;
use stdClass;

class latex_submissions {

	private layout $layout;

	public function __construct() {
		$this->layout = layout::get_instance();
	}

	/**
	 * @param int $latex_id
	 * @param bool $all
	 * @return false|mixed|stdClass
	 * @throws dml_exception
	 */
	public static function get_instance(int $latex_id, bool $all = false) {
		global $DB, $USER;

		$params = [
			'latex' => $latex_id,
			'latest' => 1
		];

		if (!$all) {
			$params['userid'] = $USER->id;
			$submissions = $DB->get_record('latex_submissions', $params);
		} else {
			$submissions = $DB->get_records('latex_submissions', $params);
		}

		if (!$submissions) {
			return null;
		}
		return $submissions;

	}

	/**
	 * @param stdClass $data
	 * @return int
	 * @throws dml_exception|invalid_parameters_exception
	 */
	public function add_instance(stdClass $data): int {
		global $DB;
		$created = $DB->insert_record('latex_submissions', $this->build_object_from_form($data));
		if (is_int($created)) {
			$this->change_latest($created);
			$data->id = $created;
			$this->save_files($data);
			return $created;
		}
		throw new invalid_parameters_exception('Не удалось сохранить ответ');
	}

	/**
	 * @param stdClass $data
	 * @return bool
	 * @throws dml_exception
	 * @throws invalid_parameters_exception
	 */
	public function update_instance(stdClass $data): bool {
		global $DB;

		$update = $this->build_object_from_form($data);

		if (empty($update->id)) {
			throw new invalid_parameters_exception('Не найден идентификатор записи для обновления ответа!');
		}

		if ($is_update = $DB->update_record('latex', $update)) {
			$this->save_files($data);
		}

		return $is_update;
	}

	/**
	 * @return int
	 * @throws dml_exception
	 */
	private function get_attempt_number(): int {
		global $DB, $USER;

		$query = [
			'latex' => $this->layout->get_latex()->id,
			'userid' => $USER->id
		];

		$submissions = $DB->get_records('latex_submissions', $query);
		return count($submissions) + 1;
	}

	/**
	 * @param int $id
	 * @throws dml_exception
	 */
	private function change_latest(int $id): void {
		global $DB, $USER;
		$sql = "UPDATE {latex_submissions} 
				SET latest = 0 
				WHERE id <> {$id} 
				    AND userid = {$USER->id} 
				    AND latex = {$this->layout->get_latex()->id}
				";
		$DB->execute($sql);
	}

	/**
	 * @param object $data
	 * @return stdClass
	 * @throws dml_exception
	 */
	private function build_object_from_form(object $data): stdClass {
		global $USER;

		$result = new stdClass;
		if (!empty($data->instance)) {
			$result->id = (int) $data->instance;
		}
		$result->latex = $this->layout->get_latex()->id;
		$result->userid = $USER->id;
		$result->answer = $data->answer;
		$result->answeraction = $data->answeraction;
		$result->attemptnumber = $this->get_attempt_number();
		$result->latest = 1;
		$result->timecreate = !empty($data->timecreate) ? $data->timecreate : time();
		$result->timemodified = time();
		return $result;
	}

	/**
	 * @param stdClass $data
	 */
	private function save_files(stdClass $data): void {
		file_save_draft_area_files(
			$data->files,
			$this->layout->get_context()->id,
			latex::FILE_SAVE_COMPONENT,
			latex::FILE_SAVE_AREA_SUBMISSIONS,
			$data->id
		);
	}

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	public function view_submission(): void {
		global $OUTPUT, $COURSE;

		$users = enrol_get_course_users_roles($COURSE->id);
		$answers = self::get_count_latest($this->layout->get_id());
		$r = grade_get_grades($COURSE->id, 'mod', 'latex', $this->layout->get_id());
		$url = new moodle_url('/mod/latex/assign.php', [
			'id' => $this->layout->get_id(),
		]);

		$content = [
			'users' => count($users),
			'answers' => $answers,
			'grading' => 0,
			'url' => $url
		];
		echo $OUTPUT->render_from_template('mod_latex/view_submission', $content);

	}

	/**
	 * @return string
	 * @throws moodle_exception
	 * @throws dml_exception
	 */
	public static function get_latest_value(): string {
		global $USER, $DB;
		$layout = layout::get_instance();
		return (string) $DB->get_field('latex_submissions', 'answer', [
			'latex' => $layout->get_latex()->id,
			'userid' => $USER->id,
			'latest' => 1
		]);
	}

	/**
	 * @param int $cm_id
	 * @return int
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	public static function get_count_latest(int $cm_id): int {
		global $DB;
		$layout = layout::get_instance();

		if ($layout->get_latex() === null) {
			$layout->initialize($cm_id);
		}

		return $DB->count_records('latex_submissions', [
			'latex' => $layout->get_latex()->id,
			'latest' => 1
		]);
	}

	/**
	 * @param int $cm_id
	 * @return int
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws moodle_exception
	 */
	public static function get_count_attachments(int $cm_id): int {
		global $DB, $USER;
		$layout = layout::get_instance();

		if ($layout->get_latex() === null) {
			$layout->initialize($cm_id);
		}

		return $DB->count_records('latex_submissions', [
			'latex' => $layout->get_latex()->id,
			'userid' => $USER->id,
		]);
	}
}