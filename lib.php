<?php

use mod_latex\exception\invalid_parameters_exception;
use mod_latex\lib\latex;

/**
 * @param $latex
 * @return int
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception|invalid_parameters_exception
 */
function latex_add_instance($latex): int {
	return (new latex(context_module::instance($latex->coursemodule)))->add_instance($latex);
}

/**
 * @param $latex
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception|invalid_parameters_exception
 */
function latex_update_instance($latex): bool {
	return (new latex(context_module::instance($latex->coursemodule)))->update_instance($latex);
}

/**
 * @param $id
 * @return mixed
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function latex_delete_instance($id) {
	$cm = get_coursemodule_from_instance('latex', $id, 0, false, MUST_EXIST);
	return (new latex(context_module::instance($cm->id)))->delete_instance($id);
}



/**
 * Indicates API features that the forum supports.
 *
 * @param string $feature
 * @return bool|null True if yes (some features may use other values)
 */
function latex_supports(string $feature): ?bool {
	switch ($feature) {
		case FEATURE_COMPLETION_HAS_RULES:
		case FEATURE_COMPLETION_TRACKS_VIEWS:
		case FEATURE_GRADE_HAS_GRADE:
			return true;
		default:
			return null;
	}
}

function latex_get_completion_state($course, $cm, $userid, $type) {
	return true;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_latex_get_completion_active_rule_descriptions($cm) {
	return [];
}

function mod_latex_pluginfile(
	stdClass $course,
	stdClass $cm,
	context $context,
	string $filearea,
	array $args,
	$forcedownload,
	array $options = []
) {

	global $CFG;

	if ($context->contextlevel !== CONTEXT_MODULE) {
		return false;
	}

	require_login($course, false, $cm);

	if (!has_capability('mod/assign:view', $context)) {
		return false;
	}

	$relative_path = implode('/', $args);
	$full_path = "/{$context->id}/mod_latex/$filearea/$relative_path";

	$fs = get_file_storage();
	if ((!$file = $fs->get_file_by_hash(sha1($full_path))) || $file->is_directory()) {
		return false;
	}
	send_stored_file($file, 0, 0, $forcedownload, $options);
}