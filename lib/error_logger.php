<?php
namespace maider;

class ErrorLoggerQuietException extends \Exception {}

class SecondTryException extends \Exception {}


class  ErrorLogger {

    //anytime an error is saved, the new id is written here
    public static $last_error_id = null;



    /**
     * @param object|\Exception $e an exception
     * @return array <p>
     *      'hostname' => the name of the machine the exception occurred on
     *      'machine_id' => sometimes, different machines will have the same hostname, particularly in a load balancing situation
     *      'caller_ip_address' => the ip address of the browser caller, else will be null
     *      'message' => the exception message
     *      'class_of_exception' => the name of the exception class
     *      'code_of_exception' => exception code
     *      'file' => the file the exception occurred in
     *      'line' => line number in the file of the exception
     *      'trace_as_json' => array of the trace, its called this because its converted to json in the database
     *      'class' => if the exception occurred inside a class, it will be listed here
     *      'function' => if the exception occurred inside a function, it will be listed here
     *      'trace_as_string' => the trace in an easier to read string format
     *      'chained' => an array of exceptions chained to this one, with the same info as above
     * </p>
     */

    public static function getExceptionInfo($e) {
        $ret = self::get_call_info();

        $ret['message'] = $e->getMessage();
        $ret['class_of_exception'] = get_class($e);
        $ret['code_of_exception'] = $e->getCode();
        $ret['file'] = $e->getFile();
        $ret['line'] = $e->getLine();

        $trace = $e->getTrace();
        $ret['trace_as_json'] = self::formaldehyde_remove_recursion($trace);



        if(isset($trace[0]) && isset($trace[0]['class']) && $trace[0]['class'] != '') {
            $ret['class'] =  $trace[0]['class'];
        } else {
            $ret['class'] = null;
        }

        if(isset($trace[0]) && isset($trace[0]['function']) && $trace[0]['function'] != '') {
            $ret['function'] =  $trace[0]['function'];
        } else {
            $ret['function'] = null;
        }



        $ret['trace_as_string'] = $e->getTraceAsString();

        $ret['chained'] = [];
        //do chained exceptions
        $f = $e->getPrevious();
        while($f  ) {
            $ret['chained'][] = self::getExceptionInfo($f);
            $f = $f->getPrevious();
        }


        return $ret;
    }

	/**

	 * @return array of info to help see who called this script
	 */
	public static function get_call_info()
	{
		$ret = [];
		$ret['hostname'] = gethostname();
		$ret['machine_id'] = ErrorLogger::getMachineID();


		$isCLI = (php_sapi_name() == 'cli');

		$ret['server'] = $_SERVER;

		if ($isCLI) {
			$ret['argv'] = $_SERVER['argv'];
		} else {
			$ret['request_method'] = $_SERVER['REQUEST_METHOD'];
			$ret['post'] = $_POST;
			$ret['get'] = $_GET;
			$ret['cookies'] = $_COOKIE;
		}


		$ret['caller_ip_address'] = $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : null;
		return $ret;
	}

    /**
     * Gets an identifying id for a machine on all platforms
     * mac and linux have ifconfig
     * windows has ipconfig
     * @return string
     */
    public static function getMachineID()
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                ob_start();
                system('ipconfig /all');
                $mycom = ob_get_contents(); // Capture the output into a variable
                ob_clean();
                $findme = "Physical";
                $pos = strpos($mycom, $findme);
                if ($pos === false) {
                    $pos = 25;
                }
                return substr($mycom, ($pos + 36), 17);
            } else {
                $ifconfig = shell_exec("ifconfig ");
                $valid_mac = "([0-9A-F]{2}[:-]){5}([0-9A-F]{2})";
                preg_match("/" . $valid_mac . "/i", $ifconfig, $ifconfig);
                if (isset($ifconfig[0])) {
                    return trim(strtoupper($ifconfig[0]));
                }
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

    }



    /**
     * Send the output from a backtrace to string that has newlines
     * @param string $message Optional message that will be added before the backtrace
     * @return string
     * @author  http://php.net/manual/en/function.debug-backtrace.php , comment
    */
    public static function back_trace_as_string_with_newlines($message = '') {
        $ret = '';
        $trace = debug_backtrace();
        if ($message) {
            $ret .= $message;
            $ret.= "\n";
        }
        $caller = array_shift($trace);
        $function_name = $caller['function'];
        $ret .= (sprintf('%s: Called from %s:%s', $function_name, $caller['file'], $caller['line']));
        $ret.= "\n";
        foreach ($trace as $entry_id => $entry) {
            $entry['file'] = $entry['file'] ? : '-';
            $entry['line'] = $entry['line'] ? : '-';
            if (empty($entry['class'])) {
                $ret .= (sprintf('%3s. %s() %s:%s', $entry_id + 1, $entry['function'], $entry['file'], $entry['line']));
                $ret.= "\n";
            } else {
                $ret .= (sprintf('%3s. %s->%s() %s:%s', $entry_id + 1, $entry['class'], $entry['function'], $entry['file'], $entry['line']));
                $ret.= "\n";
            }
        }

        return trim($ret);
    }


	/**
	 * recursion manager
	 *
	 * stored log, stack trace, or getTrace, could contain recursions.
	 * This callback aim is to remove recursions from a generic var.
	 * This function is based on native serialize one.
	 *
	 * @param   mixed   generic variable to manage
	 * @return  mixed   same var without recursion problem
	 */
	static function formaldehyde_remove_recursion($o){
		static  $replace;
		if(!isset($replace)) {
			/** @noinspection PhpDeprecationInspection */
			$replace = @create_function( '$m', '$r="\x00{$m[1]}ecursion_";return \'s:\'.strlen($r.$m[2]).\':"\'.$r.$m[2].\'";\';' );
		}
		if(is_array($o) || is_object($o)){
			$re = '#(r|R):([0-9]+);#';
			$serialize = serialize($o);
			if(preg_match($re, $serialize)){
				$last = $pos = 0;
				while(false !== ($pos = strpos($serialize, 's:', $pos))){
					$chunk = substr($serialize, $last, $pos - $last);
					if(preg_match($re, $chunk)){
						$length = strlen($chunk);
						$chunk = preg_replace_callback($re, $replace, $chunk);
						$serialize = substr($serialize, 0, $last).$chunk.substr($serialize, $last + ($pos - $last));
						$pos += strlen($chunk) - $length;
					}
					$pos += 2;
					$last = strpos($serialize, ':', $pos);
					$length = substr($serialize, $pos, $last- $pos);
					$last += 4 + $length;
					$pos = $last;
				}
				$serialize = substr($serialize, 0, $last).preg_replace_callback($re, $replace, substr($serialize, $last));
				$o = unserialize($serialize);
			}
		}
		return $o;
	}
}


