<?php

if (!defined('MOODLE_INTERNAL')) {
	die();
}

global $CFG;

use mod_latex\forms\create_latex;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/latex/lib.php');

class mod_latex_mod_form extends moodleform_mod {
	use create_latex;
}