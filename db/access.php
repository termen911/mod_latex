<?php

$capabilities = array(

	'mod/latex:addinstance' => [
		'riskbitmask' => RISK_XSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_COURSE,
		'archetypes' => [
			'editingteacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW
		],
		'clonepermissionsfrom' => 'moodle/course:manageactivities'
	]
);