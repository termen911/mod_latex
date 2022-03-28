<?php

namespace mod_latex\lib;

use grade_grade;
use grade_item;
use mod_latex\exception\invalid_parameters_exception;

class latex_grade {
	//
	public int $course_id;
	//
	public int $item_number = 0;
	//
	public int $item_instance;

	private static latex_grade $instance;

	private grade_item $grade_item;

	public static function get_instance(): latex_grade {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @throws invalid_parameters_exception
	 */
	public function get_grade_item(): grade_item {

		$this->valid_params();

		if (!empty($this->grade_item)){
			return $this->grade_item;
		}

		[$itemtype, $itemmodule] = latex::get_mod_and_name();

		$this->grade_item = grade_item::fetch([
			'courseid' => $this->course_id,
			'itemtype' => $itemtype,
			'itemnumber' => $this->item_number,
			'itemmodule' => $itemmodule,
			'iteminstance' => $this->item_instance,
		]);
		return $this->grade_item;
	}

	/**
	 * @param string $name
	 * @return grade_item|null
	 * @throws invalid_parameters_exception
	 */
	public function add_grade_item(string $name): ?grade_item {

		$this->valid_params();

		[$itemtype, $itemmodule] = latex::get_mod_and_name();
		$_grade_item = new grade_item();
		$_grade_item->courseid = $this->course_id;
		$_grade_item->itemname = $name;
		$_grade_item->itemtype = $itemtype;
		$_grade_item->itemnumber = $this->item_number;
		$_grade_item->itemmodule = $itemmodule;
		$_grade_item->iteminstance = $this->item_instance;

		if ($_grade_item->insert() === false) {
			throw new invalid_parameters_exception("Ошибка при создание элемента оценивания");
		}

		return $this->get_grade_item();
	}

	/**
	 * @throws invalid_parameters_exception
	 */
	public function update_grade_grades(): void {
		global $USER;

		$this->get_grade_item();

		$grade_grade = grade_grade::fetch_all([
			'itemid' => $this->grade_item->id
		]);

		foreach ($grade_grade as $item) {
			$item->rawgrade = null;
			$item->finalgrade = null;
			$item->aggregationstatus = 'used';
			$item->timemodified = time();
			$item->usermodified = $USER->id;
			$item->update();
		}
	}

	/**
	 * @throws invalid_parameters_exception
	 */
	public function delete_grade_grades(): bool {
		$this->get_grade_item();
		$this->get_grade_item()->delete_all_grades();
		$this->get_grade_item()->delete();
		return true;
	}

	/**
	 * @return void
	 * @throws invalid_parameters_exception
	 */
	private function valid_params(): void {
		//if (empty($this->course_id) || empty($this->item_number) || empty($this->item_instance)) {
		//	$message = "На задан один из обязательных параметров - course_id, item_number, item_instance";
		//	throw new invalid_parameters_exception($message);
		//}
	}
}