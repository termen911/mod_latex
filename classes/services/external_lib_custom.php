<?php

namespace mod_latex\services;

use external_api;
use external_description;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use invalid_response_exception;

class external_lib_custom extends external_api {
	/**
	 * @throws invalid_parameter_exception
	 */
	public static function validate_parameters(external_description $description, $params) {

		if ($description instanceof external_value) {
			if (is_array($params) || is_object($params)) {
				throw new invalid_parameter_exception('Scalar type expected, array or object received.');
			}

			if (
				$description->type === PARAM_BOOL
				&& (
					is_bool($params)
					|| $params === 0
					|| $params === 1
					|| $params === '0'
					|| $params === '1'
				)
			) {
				return (bool) $params;
			}

			$debug_info = sprintf(
				'Invalid external api parameter: the value is "%s", the server was expecting "%s" type',
				$params,
				$description->type
			);

			return validate_param($params, $description->type, $description->allownull, $debug_info);

		}

		if ($description instanceof external_json) {
			try {
				return json_encode($params, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				$debug_info = sprintf(
					"Type 'external_raw_array' only arrays accepted.  The bad value is:  '%s'",
					print_r($params, true)
				);
				throw new invalid_parameter_exception($debug_info);
			}
		}

		if ($description instanceof external_single_structure) {
			if (!is_array($params)) {
				$debug_info = sprintf("'Only arrays accepted. The bad value is: '%s'", print_r($params, true));
				throw new invalid_parameter_exception($debug_info);
			}
			$result = [];
			foreach ($description->keys as $key => $subdesc) {
				if (!array_key_exists($key, $params)) {
					if ($subdesc->required === VALUE_REQUIRED) {
						throw new invalid_parameter_exception('Missing required key in single structure: ' . $key);
					}
					if ($subdesc->required === VALUE_DEFAULT) {
						try {
							$result[$key] = static::validate_parameters($subdesc, $subdesc->default);
						} catch (invalid_parameter_exception $e) {
							throw new invalid_parameter_exception($key . " => " . $e->getMessage() . ': ' . $e->debuginfo);
						}
					}
				} else {
					try {
						$result[$key] = static::validate_parameters($subdesc, $params[$key]);
					} catch (invalid_parameter_exception $e) {
						throw new invalid_parameter_exception($key . " => " . $e->getMessage() . ': ' . $e->debuginfo);
					}
				}
				unset($params[$key]);
			}
			if (!empty($params)) {
				throw new invalid_parameter_exception('Unexpected keys (' . implode(', ', array_keys($params)) .
					') detected in parameter array.');
			}
			return $result;

		}

		if ($description instanceof external_multiple_structure) {
			if (!is_array($params)) {
				$debug_info = sprintf("Only arrays accepted. The bad value is:  '%s'", print_r($params, true));
				throw new invalid_parameter_exception($debug_info);
			}
			$result = array();
			foreach ($params as $param) {
				$result[] = static::validate_parameters($description->content, $param);
			}
			return $result;

		}

		throw new invalid_parameter_exception('Invalid external api description');
	}

	/**
	 * @throws invalid_response_exception
	 */
	public static function clean_returnvalue(external_description $description, $response) {

		if ($description instanceof external_value) {
			if (is_array($response) || is_object($response)) {
				throw new invalid_response_exception('Scalar type expected, array or object received.');
			}

			if (
				$description->type === PARAM_BOOL
				&& (
					is_bool($response)
					|| $response === 0
					|| $response === 1
					|| $response === '0'
					|| $response === '1'
				)
			) {
				return (bool) $response;
			}

			$responsetype = gettype($response);

			$debug_info = sprintf(
				'Invalid external api response: the value is "%s" of PHP type "%s", the server was expecting "%s" type',
				$response,
				$responsetype,
				$description->type
			);

			try {
				return validate_param($response, $description->type, $description->allownull, $debug_info);
			} catch (invalid_parameter_exception $e) {
				throw new invalid_response_exception($e->debuginfo);
			}

		}

		if ($description instanceof external_json) {
			try {
				return json_encode($response, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				$debug_info = sprintf(
					"Type 'external_raw_array' only arrays accepted.  The bad value is:  '%s'",
					print_r($response, true)
				);
				throw new invalid_response_exception($debug_info);
			}
		}

		if ($description instanceof external_single_structure) {
			if (!is_array($response) && !is_object($response)) {
				throw new invalid_response_exception('Only arrays/objects accepted. The bad value is: \'' .
					print_r($response, true) . '\'');
			}

			// Cast objects into arrays.
			if (is_object($response)) {
				$response = (array) $response;
			}

			$result = array();
			foreach ($description->keys as $key => $subdesc) {
				if (!array_key_exists($key, $response)) {
					if ($subdesc->required === VALUE_REQUIRED) {
						throw new invalid_response_exception('Error in response - Missing following required key in a single structure: ' .
							$key);
					}
					if (($subdesc instanceof external_value) && $subdesc->required === VALUE_DEFAULT) {
						try {
							$result[$key] = static::clean_returnvalue($subdesc, $subdesc->default);
						} catch (invalid_response_exception $e) {
							throw new invalid_response_exception($key . " => " . $e->getMessage() . ': ' . $e->debuginfo);
						}
					}
				} else {
					try {
						$result[$key] = static::clean_returnvalue($subdesc, $response[$key]);
					} catch (invalid_response_exception $e) {
						throw new invalid_response_exception($key . " => " . $e->getMessage() . ': ' . $e->debuginfo);
					}
				}
				unset($response[$key]);
			}
			return $result;

		}

		if ($description instanceof external_multiple_structure) {
			if (!is_array($response)) {
				throw new invalid_response_exception('Only arrays accepted. The bad value is: \'' .
					print_r($response, true) . '\'');
			}
			$result = array();
			foreach ($response as $param) {
				$result[] = static::clean_returnvalue($description->content, $param);
			}
			return $result;

		}

		throw new invalid_response_exception('Invalid external api response description');
	}

}