<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname(__FILE__) . '/../public/maider/class-log.php');

/**
 * Fired during plugin deactivation

 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	/**
	 * @throws \Exception
	 */
	public static function deactivate() {
		//clear logs during deactivation
		$log = new Log();
		$log->clear_logs();
	}

}
