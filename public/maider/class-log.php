<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname(__FILE__) . '/../../lib/JsonHelper.php');
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname(__FILE__) . '/../../lib/ErrorLogger.php');

class Log {

	public $row_id = null;
	/**

	 */
	public function __construct() {
		$this->row_id = null;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public static function get_log_table_name() {
		global $wpdb;
		if (empty($wpdb)) { throw new \Exception("Global Wordpress Database is not defined");}
		$table_start = $wpdb->base_prefix. PLUGIN_NAME. '_';
		return $table_start.'logs';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public static function get_log_table_create_sql() {
		global $wpdb;
		if (empty($wpdb)) { throw new \Exception("Global Wordpress Database is not defined");}
		$charset_collate = $wpdb->get_charset_collate();
		$table_start = self::get_log_table_name();

		//do response table

		$sql = "CREATE TABLE `{$table_start}logs` (
              id int NOT NULL AUTO_INCREMENT,
              run_id int default null,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              action_name varchar(20) not null comment 'values are install|run|option|plugin|theme|error',
              op_name varchar(255) default null ,
              op_value text default null,
              op_result text default null,
              PRIMARY KEY  (id),
              KEY created_at_key (created_at),
              KEY action_name_key (action_name),
              KEY op_name_key (op_name)
              ) $charset_collate;";

		return $sql;
	}

	/**
	 * @param $action
	 * @param $op_name
	 * @param $op_value
	 * @param $op_result
	 *
	 * @return int
	 * @throws SecondTryException
	 */
	public function log($action,$op_name,$op_value,$op_result) {
		global $wpdb,$is_wp_init_called;
		if (!$is_wp_init_called) {return 0;}
		if (empty($wpdb)) { return 0;}
		try {

			$b_save_id = false;
			switch ($action) {
				case 'run': {
					$b_save_id = true;
				}
				case 'error': {
					if ($op_result instanceof \Exception) {
						$op_result = ErrorLogger::getExceptionInfo($op_result);
					}
				}
				case 'init':
				case 'option':
				case 'plugin':
				case 'theme': {
					break;
				}
				default: {
					throw new \Exception("Illegal Log action of [$action]");
				}
			}

			$insert_result = $wpdb->insert(
				self::get_log_table_name(),
				array(
					'run_id' =>  $this->row_id,
					'action_name' =>  $action,
					'op_name' => JsonHelper::toStringAgnostic($op_name),
					'op_value' =>  JsonHelper::toStringAgnostic($op_value),
					'op_result' => JsonHelper::toStringAgnostic($op_result)
				),
				array(
					'%d','%s','%s','%s','%s'
				)
			);

			if ($wpdb->last_error) {
				throw new \Exception($wpdb->last_error );
			}

			if ($insert_result === false) {
				throw new \Exception("could not create Log row in the database, but error is not showing in WPDB class" );
			}
			$insert_id = $wpdb->insert_id;
			if ($b_save_id) {
				$this->row_id = $insert_id;
			}


			return $insert_id;
		} catch (\Exception $e) {
			try {
				$info['outer_exception'] = ErrorLogger::getExceptionInfo($e);
				$foolproof = print_r($info,true);
				throw new SecondTryException($foolproof);
			} catch (\Exception $r) {
				throw new SecondTryException($r->getMessage());
			}
		}
	}

	/**
	 * @return array|null|object
	 * @throws \Exception
	 */
	public function get_log_results() {
		global $wpdb;
		$ret = [];
		$table_name = self::get_log_table_name();
		/** @noinspection SqlResolve */
		$res = $wpdb->get_results(
			" 
            select id,run_id,created_at, unix_timestamp(created_at) as created_at_timestamp,
              action_name,op_name,op_value,op_result
            from $table_name 
            where 1 order by id;
            ");

		if ($wpdb->last_error) {
			throw new \Exception($wpdb->last_error );
		}

		if (empty($res)) { return $ret;}
		foreach ($res as $row) {
			$action = $row->action_name;
			$ts = $row->created_at_timestamp;
			$value = JsonHelper::fromString($row->op_value,false);
			$result = JsonHelper::fromString($row->op_result,false);
			$ret[] = [
			'id' => $row->id,
			'run_id' => $row->run_id,
			'action' => $action,
			'timestamp' => $ts,
			'install_date_time' => $row->created_at,
			'name' => $row->op_name,
			'value' => $value,
			'result' => $result
			];
		}
		return $ret;
	}

	/**
	 * @throws \Exception
	 */
	public function clear_logs() {
		global $wpdb;
		$table_name = self::get_log_table_name();
		$wpdb->query("TRUNCATE $table_name");
	}
}