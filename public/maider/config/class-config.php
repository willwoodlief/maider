<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname( __FILE__ ) . "/../../../vendor/autoload.php");

/** @noinspection PhpIncludeInspection */
require_once realpath( dirname( __FILE__ ) . "/../../../lib/ErrorLogger.php");

/** @noinspection PhpIncludeInspection */
require_once realpath(dirname( __FILE__ )."/../options/class-options.php");
/** @noinspection PhpIncludeInspection */
require_once realpath(dirname( __FILE__ )."/../class-log.php");

/** @noinspection PhpIncludeInspection */
require_once realpath(dirname( __FILE__ )."/../class-plugins.php");

use Symfony\Component\Yaml\Yaml;


class ConfigException extends \RuntimeException {

}

class Config {

	private $config_meta = [];

	private $config = [];

	private $original_config = [];

	/**
	 * @var Options|null  $options
	 */
	private $options = null;

	/**
	 * @var Plugins|null $plugins
	 */
	private $plugins = null;

	/**
	 * @var Log|null
	 */
	public  $log = null;


	/**
	 * @var array[] ExceptionInfo of anything wrong with the config during construction
	 */
	public $exception_info = [];
	/**
	 * Config constructor.
	 *
	 * @param $config_path
	 * @param string $initial_command
	 * @throws \Exception
	 */
	public function __construct($config_path,$initial_command) {
		$this->exception_info = [];
		$this->log = new Log();
		switch ($initial_command) {
			case 'run': {
				$this->log->log('run',$initial_command,null,null);
				break;
			}
			default: {

			}
		}


		try {
			//load in master definitions  from yaml
			$config_meta_path = dirname( __FILE__ ) . "/config_meta.yaml";

			$this->config_meta     = Yaml::parseFile( $config_meta_path );
			$this->config          = Yaml::parseFile( $config_path );
			$ju                    = JsonHelper::toString( $this->config );
			$this->original_config = JsonHelper::fromString( $ju );

			if (($initial_command === 'list-config-raw') ) {
				return;
			}

			//read in option rules
			$this->options = new Options($this->log);
			$this->plugins = new Plugins($this->log);
			$this->confirm_config();




			switch ($initial_command) {
				case 'run': {
					$this->log->log('init',null,$this->original_config,$this->config);
					break;
				}
				default: {

				}
			}

		} catch (\Exception $e) {
			$this->exception_info[] = ErrorLogger::getExceptionInfo($e);
			$this->log->log('error',null,null,$e);
		}

	}

	public function get_config() {
		return $this->config;
	}

	public function get_options() {
		return $this->options;
	}

	public function get_raw_config() {
		return $this->original_config;
	}


	/**
	 * @return bool
	 * @throws ConfigException
	 */
	protected function confirm_config() {
		$v_valid = true;

		foreach ($this->config as $top_key => $top_node) {
			//see if the top node is a section, else throw
			if (!array_key_exists($top_key,$this->config_meta['sections'])) {
				throw new ConfigException("Unrecognized Section $top_key");
			}
			//get the section info to compare with
			$section_meta = $this->config_meta['sections'][$top_key];
			if (empty($section_meta['handler'])) {
				$section_meta['handler'] = 'config';
			}

			switch ($section_meta['handler']) {
				case 'config': {
					if (!array_key_exists('keys',$section_meta)) {
						throw new ConfigException("No meta keys for $top_key");
					}
					$section_meta_keys = $section_meta['keys'];

					$this->config[$top_key] = $this->confirm_config_section($section_meta_keys,$top_node,$top_key);
					break;
				}
				case 'options': {
					$this->config[$top_key] = $this->options->validate_options($top_node);
					break;
				}
				case 'plugins': {
					$this->config[$top_key] = $this->plugins->validate_plugins($top_node);
					break;
				}
				case 'themes': {
					//not implemented yet
					break;
				}
				default: {
					throw new ConfigException("Found config section $top_key, but no code to process it. Bad section name ?");
				}
			}

		}
		return $v_valid;
	}

	/**
	 *
	 * @param array $section_meta
	 * @param array $node
	 * @param string $section_name
	 * @throws ConfigException
	 * @return array
	 */
	protected function confirm_config_section($section_meta,$node,$section_name) {
		//make copy of node
		//go through each section meta
			  //if node does not have that key
			                        // if there is a default, then add that key
									// if still not have that key and its required, then throw
				//check key value3
		           //if allowed_values then the value must not exactly match any of the allowed values
		           // it cannot be null or empty
			  //remove processed key from node

		//end of loop if node is not empty then throw

		if (!is_array($node) || empty($node)) {
			throw new ConfigException("Empty config section: $section_name");
		}

		if (!is_array($section_meta) || empty($section_meta)) {
			throw new ConfigException("Empty section meta section: $section_name");
		}



		try {
			$copy_json = JsonHelper::toString($node);
			$copy = JsonHelper::fromString($copy_json);
		} catch(JsonException $j) {
			throw new ConfigException("Section meta section: $section_name, could not be converted to json during processing: ",
				$j->getMessage());
		}


		foreach ($section_meta as $metum) {
			$name = $metum['name'];
			if (!array_key_exists($name,$node)) {
				if (array_key_exists('default',$metum)) {
					$node[$name] = $metum['default'];
				}

				if (array_key_exists('required',$metum)) {
					if ($metum['required'] ) {
						if (!array_key_exists($name,$node) ) {
							throw new ConfigException("Config Section $section_name does not have a required key: $name");
						}
					}
				}
			}

			if (!array_key_exists($name,$node)) { continue; }

			$value = trim(strval( $node[$name])); //convert to string, then trim


			//see if value has to be something
			if (array_key_exists('allowed_values',$metum)) {
				if (array_key_exists('min_secret_length',$metum['allowed_values'] )) {
					$min_size = intval($metum['allowed_values']['min_secret_length']) ;
					if (strlen($value) < $min_size) {
						throw new ConfigException("Config Section $section_name has a key $name which has fewwer than $min_size characters");
					}
				} else {
					$allowed_values = array_keys($metum['allowed_values']);
					$ok_allowed = array_search($value,$allowed_values);

					if ($ok_allowed === false) {
						$list = implode(',',array_keys($metum['allowed_values']));
						throw new ConfigException("Config Section $section_name has a key $name whose value [$value] is not in  [$list] ");
					}
				}

			}

			unset($copy[$name]);
		}

		if (!empty($copy)) {
			$left_overs = implode(',',array_keys($copy));
			throw new ConfigException("Config Section $section_name has left over keys [$left_overs] that are not on the list of accepted keys");
		}

		return $node;
	}

	/**
	 * @param String $secret_input
	 * will return false if allow_remote is true, and the param here matches the secret
	 * @return void
	 */
	public function check_secret_access($secret_input) {

		if (!array_key_exists('security_key',$this->config['this_plugin'])) {
			throw new ConfigException("The secret is not in the config ! Cannot compare secrets");
		}

		if (!array_key_exists('allow_remote',$this->config['this_plugin'])) {
			throw new ConfigException("The allow_remote flag is not in the config ! Cannot decide if remote access granted");
		}

		if (intval($this->config['this_plugin']['allow_remote']) < 1) {
			throw new ConfigException("Remote access is turned off");
		}

		$real_secret = $this->config['this_plugin']['security_key'];

		if (strcmp($real_secret,$secret_input) !== 0) {
			sleep(10); // give brute force attacks a time to cool off
			throw new ConfigException("Secret does not match");
		}

	}

	/**
	 * @throws SecondTryException
	 */
	public function run() {
		$this->options->run_options();
		$this->plugins->run_plugins();
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function get_combined_info() {
		$ret = [];
		$errors = $this->get_error_info();
		$optional = $this->options->get_combined_info();
		$plugable = $this->plugins->get_combined_info();
		$ret = array_merge($ret,$errors,$optional,$plugable);
		return $ret;
	}

	protected function get_error_info() {
		$ret = [];
		foreach ($this->exception_info as $ex) {
			$ret[] = ['title'=> 'This Plugin\'s Configeration','name'=>'Start Up Error',
			               'value'=> $ex['message'],
			               'result' => "This needs to be fixed to allow full functionality of this plugin. Some items may not show up here until addressed",
			               'is_error'=>true];
		}
		return $ret;
	}



}