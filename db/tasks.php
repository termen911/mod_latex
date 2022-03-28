<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
	[
		'classname' => \mod_latex\task\set_grades::class,
		'blocking' => 0,
		'minute' => '*',
		'hour' => '*',
		'day' => '*',
		'dayofweek' => '*',
		'month' => '*'
	]
];