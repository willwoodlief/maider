<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname( __FILE__ ) . "/../../../vendor/autoload.php");
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Options {

	private $raw_option_list = [];
	private $raw_unused_list = [];
	private $option_list = [];
	private $unused_list = [];
	private $runnable_list = [];
	/**
	 * @var Log|null
	 */
	public  $log = null;
	/**
	 * Options constructor.
	 * @param Log $log
	 * @throws ParseException
	 */
	public function __construct($log) {

		$this->log = $log;

		$options_yaml_path  = dirname( __FILE__ )."/options.yaml";
		$unused_options_yaml_path  = dirname( __FILE__ )."/unused_options.yaml";

		//load in list from yaml
		$this->raw_option_list = Yaml::parseFile($options_yaml_path);
		$this->raw_unused_list = Yaml::parseFile($unused_options_yaml_path);

		$this->option_list = [];
		foreach ($this->raw_option_list['options'] as $subgroup_name => $subgroup) {
			foreach ($subgroup as  $node) {
				$this->option_list[$node['option_key']] =  $node;
				$this->option_list[$node['option_key']]['category'] = $subgroup_name;
			}
		}

		foreach ($this->raw_unused_list  as $category_name => $category) {
			foreach ($category['options'] as  $node) {
				$this->unused_list[$node['option_key']] =  $node;
				$this->unused_list[$node['option_key']]['category'] = $category_name;
			}
		}
	}

	public function get_options() { return $this->option_list;}
	public function get_unavaliable_options() { return $this->unused_list;}
	public function get_runnable_list() { return $this->runnable_list;}


	/**
	 * Goes through the options and sees if this is a legal option with a legal value
	 * @param array[] $options
	 *   -  each array of:
	 *          string option_key
	 *          mixed  option_value
	 * @example [ ['option_key'=>'gmt_offset', 'option_reset'=>'__RESET__' ], ['option_key'=>'blogname', 'option_value'=>"Will's Blog" ] ]
	 *
	 * @return array
	 */
	public function validate_options($options) {

		$ret = [];

		foreach ($options as $node) {
			if (!array_key_exists('option_key',$node)) {
				throw new ConfigException("each option needs to have an option_key, and an option_value");
			}

			if (!array_key_exists('option_value',$node)) {
				throw new ConfigException("each option needs to have an option_key, and an option_value");
			}

			$key = $node['option_key'];
			$value = trim(strval( $node['option_value'])); //convert to string, then trim ;

			if (array_key_exists($key,$this->option_list)) {
				// check if value
				// but if the value is __RESET__, then just check to make sure the default exists and put that in
				$metum = $this->option_list[$key];

				if (strcmp('__RESET__',$value) === 0 ) {
					if (array_key_exists('default',$metum)) {
						$default = trim(strval($metum['default']));
						if (is_numeric($default) || !empty($default)){
							$value = $metum['default'];
						} else{
							throw new ConfigException("Cannot reset the option $key, because it has an empty default value");
						}
						
					} else {
						throw new ConfigException("Cannot reset the option $key, because it has no default value");
					}
				}
				//see if value has to be something
				if (array_key_exists('allowed_values',$metum)) {

					$allowed_values = array_keys($metum['allowed_values']);
					$ok_allowed = array_search($value,$allowed_values);

					if ($ok_allowed === false) {
						$list = implode(',',array_keys($metum['allowed_values']));
						throw new ConfigException("Option of $key has a value [$value] that is not in is not in  [$list] ");
					}
				}
				$ret[] = ['option_key'=> $key, 'option_value'=>$value];
			} else {
				//see if its on the bad list
				if (array_key_exists($key,$this->unused_list)) {
					$unnode = $this->unused_list[$key];
					if (array_key_exists('category',$unnode)) {
						$category = $unnode['category'];
						throw new ConfigException("Options: $key cannot be processed because its a $category option");
					} else {
						throw new ConfigException("Options: $key is not a registered option");
					}

				}
			}
		}
		$this->runnable_list = $ret;
		return $ret;
	}




	/**
	 * use this function to tell if an option exists
	 * @param string $option_name - the name of the option
	 *
	 * @return bool <p>
	 *  if true , then the option exists
	 * if false it does not exist
	 * </p>
	 */
	function optionExists($option_name) {
		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option_name));
		if (is_object($row)) {
			return true;
		}
		return false;
	}

	/**
	 * This compares the values of the options currently with the set defaults in the options yaml
	 * will throw exception on a non wp install
	 *
	 * This is designed to test on a fresh install of wordpress to see if the defaults are still valid
	 * @return array <p>
	 *   for each mismatch, will have a node of
	 *   array of :
	 *          option_name string - the name of the option
	 *          option_key  string - the name wp uses for the option
	 *          option_value mixed  - the current value of the option
	 *          option_default mixed - the default value of the option
	 * </p>
	 *
	 * @throws \Exception
	 */
	public function do_wp_test_with_defaults() {
		global $wpdb;
		if (empty($wpdb)) {
			throw new \Exception("the wordpress data object is empty. Is this a WP install ?");
		}
		if (! function_exists('get_option')) {
			throw new \Exception(" the WP function get_option is not defined here");
		}

		$ret = [];

		foreach ($this->option_list as $opt) {
			$option_value = get_option($opt['option_key'],'this option does not exist');
			if (strcmp($option_value,'this option does not exist') === 0) {
				continue;
			}
			switch ($opt['data_type']) {
				case 'String': {
					$str_value = strval($option_value);
					if (strcmp($str_value,$opt['default']) !== 0) {
						$ret[] = [
							'option_name'=> $opt['name'] ,
							'option_key' => $opt['option_key'] ,
							'option_value' => $str_value ,
							'option_default' => $opt['default']
						];
					}
					break;
				}
				case 'Integer': {

					if ($option_value != $opt['default']) {
						$ret[] = [
							'option_name'=> $opt['name'] ,
							'option_key' => $opt['option_key'] ,
							'option_value' => $option_value ,
							'option_default' => $opt['default']
						];
					}
					break;
				}
				default:
					throw new \Exception("Did not expect the set option type to be anything but a string or integer. Update the code to work with newly added options in the yaml");
			}

		}
		return $ret;
	}

	/**
	 * @throws SecondTryException
	 */
	public function run_options() {
		foreach ($this->runnable_list as $r) {
			try {
				$name = $r['option_key'];
				$value = $r['option_value'];
				$sanitized_value = sanitize_option($name,$value);
				$old_value = get_option($name);
				if ($old_value == $sanitized_value) {
					$this->log->log('option',$name,$value,['is_changed'=> false]);
					continue;
				}
				$update_status = update_option( $name , $value );
				if ($old_value !== false) {
					if ($update_status === false) {
						throw new \Exception("Could not update the option [$name]");
					}
				}
				$this->log->log('option',$name,$value,['is_changed'=> true,'old_value' =>$old_value,'update_option_returned'=>$update_status]);
			} catch (\Exception $e) {
				$this->log->log('error','option',$name,$e);
			}
		}
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function get_combined_info() {
		$options_to_show = $this->get_runnable_list();
		$logs = $this->log->get_log_results();

		$logged_options = [];
		//will show the last log for each option, in case there are multiple runs
		foreach ($logs as $log) {
			$action = $log['action'];
			$log_option_name = $log['name'];
			$log_option_value = $log['value'];
			$log_option_result = $log['result'];
			if ($action === 'option') {
				if ($log['result']['is_changed']) {
					$result = "Changed";
				} else {
					$result = "Same Value";
				}
				$logged_options[$log_option_name] = ['title'=> 'Option','name'=>$log_option_name,
				                                     'value'=> $log_option_value, 'result' => $result,'is_error'=>false];
			}
			elseif (($action === 'error') && ($log_option_name === 'option')) {

				$logged_options[$log_option_name] = ['title'=> 'Option','name'=>$log_option_name,
				                                     'value'=> $log_option_value,
				                                     'result' => $log_option_result['message'],
				                                     'is_error'=>true];
			} else {
				continue;
			}


		}


		//make hash coordinating the logs and the options
		$combined = [];
		foreach ($options_to_show as $op) {
			$key = $op['option_key'];
			$value = $op['option_value'];
			$b_new = false;
			if (array_key_exists($key,$logged_options)) {
				$logged_value =  $logged_options[$key]['value'];
				if (is_numeric($logged_value)) {
					$logged_value = strval($logged_value);
				}
				if (is_numeric($value)) {
					$value = strval($value);
				}
				if ($logged_value === $value) {
					$combined[] = $logged_options[$key];
				} else {
					//log was for earlier value of the option
					$b_new = true;
				}
			} else {
				$b_new = true;
			}

			if ($b_new) {
				$combined[] = ['title'=> 'Option','name'=>$key,
				               'value'=> $value,
				               'result' => "Not Run Yet",
				               'is_error'=>false];
			}
		}

		return $combined;
	}


}