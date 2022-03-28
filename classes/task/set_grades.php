<?php

namespace mod_latex\task;

global $CFG;

use coding_exception;
use completion_info;
use context_module;
use core\task\scheduled_task;
use dml_exception;
use grade_grade;
use grade_item;
use mod_latex\lib\latex;
use stdClass;
use text_progress_trace;

require_once($CFG->libdir . '/gradelib.php');
require_once("$CFG->libdir/completionlib.php");

class set_grades extends scheduled_task {

	private string $mod_name = 'latex';

	private text_progress_trace $trace;
	private bool $stop = false;

	private context_module $context;
	private array $students;
	private array $students_id;
	private array $latex;
	private array $submissions;

	private stdClass $current_module;
	private int $current_instance;
	private int $current_course_id;
	private int $current_student_id;
	private stdClass $current_course_module;

	/**
	 * @return string
	 */
	public function get_name(): string {
		return "Выставить оценки за задания по математике автоматически";
	}

	/**
	 * @throws coding_exception
	 * @throws dml_exception
	 */
	public function execute(): void {
		$this->trace = new text_progress_trace();
		$this->trace->output('Start set grades');

		//Получить список заданий по которым необходимо выставить оценки
		$this->get_latex_list();
		foreach ($this->latex as $module) {

			$this->current_module = $module;
			$this->current_instance = $module->id;
			$this->current_course_id = $module->course;

			$this->get_context();
			//Получить всех студентов на курсе
			$this->get_students();
			//Получить ответы всех студентов
			$this->get_students_submissions();
			foreach ($this->students_id as $student_id) {
				$this->current_student_id = $student_id;
				//Получить оценки по всем студентам на курсе
				$this->install_grades();
			}
			latex::set_is_grade($module->id);
		}
		$this->trace->output('End set grades');
	}

	private function get_latex_list(): void {
		if (!$this->stop) {
			try {
				$this->trace->output('Start get grades courses');
				$this->latex = latex::get_is_grated();
				$this->trace->output('End get grades courses');
			} catch (dml_exception $e) {
				$this->trace->output('Error: mod/latex/task get_all_courses(): ' . $e->getMessage());
				$this->stop = true;
			}
		}
		$this->trace->output('Найдено - ' . count($this->latex) . ' заданий для оценивания');
	}

	/**
	 * @throws coding_exception
	 */
	private function get_students(): void {
		if (!$this->stop) {
			$this->students = enrol_get_course_users($this->current_course_id);
			$this->students_id = array_column($this->students, 'id');
			$this->trace->output('Найдено - ' . count($this->students) . ' студентов в задание ' . $this->current_module->name);
		}
	}

	/**
	 * @throws coding_exception
	 */
	private function get_context(): void {
		$this->current_course_module =
			get_coursemodule_from_instance($this->mod_name, $this->current_instance, $this->current_course_id);
		$this->context = context_module::instance($this->current_course_module->id);
	}

	private function get_students_submissions(): void {
		global $DB;
		$_students_id = implode(',', $this->students_id);
		$sql = "SELECT * FROM {latex_submissions} WHERE latex = {$this->current_instance} AND userid IN ({$_students_id})";
		$this->submissions = $DB->get_records_sql($sql);
		$this->trace->output('Найдено - ' . count($this->submissions) . ' ответов на задание ' . $this->current_module->name);
	}

	private function install_grades() {

		$grades_item = $this->get_grades_item();

		if (empty($grades_item->id)) {
			$this->stop = true;
			return;
		}

		$get_grade_user = $this->get_grade_user($grades_item->id);
		$this->trace->output('Найдено - ' . count($this->submissions) . ' ответов на задание ' . $this->current_module->name);
		if (empty($get_grade_user->id)) {
			$this->stop = true;
			return;
		}

		$get_grade_user->rawgrade = $this->get_user_source();
		$get_grade_user->finalgrade = $this->get_user_source();
		$get_grade_user->update();
		$this->set_completion();
	}

	private function get_user_source(): int {
		$user_answer = '';
		foreach ($this->submissions as $submission) {
			if ((int) $submission->userid === $this->current_student_id) {
				$user_answer = $submission->answer;
				break;
			}
		}
		if ($user_answer === $this->current_module->answer) {
			return 100;
		}
		return 0;
	}

	/**
	 * @return bool|grade_item
	 */
	private function get_grades_item() {

		$params = [
			'courseid' => $this->current_course_id,
			'itemname' => $this->current_module->name,
			'itemtype' => 'mod',
			'itemnumber' => 0,
			'itemmodule' => $this->mod_name,
			'iteminstance' => $this->current_instance,
		];

		return grade_item::fetch($params);
	}

	/**
	 * @param int $grade_item_id
	 * @return grade_grade
	 */
	private function get_grade_user(int $grade_item_id): grade_grade {

		$params = [
			'itemid' => $grade_item_id,
			'userid' => $this->current_student_id,
		];

		return grade_grade::fetch($params);
	}


	private function set_completion(): void {
		//TODO получить предыдущее состояния для обновления (нужно учитывать просмотр)
		$completion = new completion_info(get_course($this->current_course_id));
		$data = new stdClass();
		$data->id = false;
		$data->coursemoduleid = $this->current_course_module->id;
		$data->userid = $this->current_student_id;
		$data->completionstate = 1;
		$data->viewed = 1;
		$data->overrideby = null;
		$data->timemodified = time();
		$completion->internal_set_data($this->current_course_module
			, $data);
	}
}