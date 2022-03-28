<?php
/**
 */

use mod_latex\external\keyboard_configurations;

defined('MOODLE_INTERNAL') || die();

$functions = [
	'keyboard_configurations' => [
		'classname' => keyboard_configurations::class,
		'methodname' => 'get_configs',
		'description' => '',
		'type' => 'read',
		'ajax' => true,
	],
];

