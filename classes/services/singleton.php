<?php

namespace mod_latex\services;

trait singleton {

	private static self $instance;

	public static function get_instance(): self {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct(){}
	private function __clone(){}
}