<?php

namespace Wpify\WpifyWooCore\Managers;

class ModulesManager {
	private $modules = [];

	public function add_module( $id, $module ) {
		$this->modules[ $id ] = $module;
	}

	/**
	 * @return array
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	public function get_module_by_id( $id ) {
		return $this->modules[ $id ] ?? null;
	}
}