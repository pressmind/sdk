<?php


namespace Pressmind\Log;


use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\Log;
use Pressmind\Registry;
use \DateTime;
use \Exception;

class Writer
{
    const OUTPUT_SCREEN = 'screen';
    const OUTPUT_FILE = 'file';
    const OUTPUT_BOTH = 'both';
    const TYPE_DEBUG = 'DEBUG';
    const TYPE_INFO = 'INFO';
    const TYPE_WARNING = 'WARNING';
    const TYPE_ERROR = 'ERROR';
    const TYPE_FATAL = 'FATAL';

    /**
     * @param $log
     * @param string $output
     * @param string $filename
     * @return mixed|string
     * @throws Exception
     */
    static function write($log, $output = 'screen', $filename = 'messages', $type = 'INFO')
    {
        $log_file_name = $filename;
        if($type != self::TYPE_INFO) {
            $log_file_name .= '_' . strtolower($type);
        }
        $log_text = '';
        if($output == self::OUTPUT_SCREEN || $output == self::OUTPUT_BOTH) {
            $log_text = print_r($log, true);
            if(php_sapi_name() == "cli") {
                echo $log_text . "\n";
            }
        }
        if($output == self::OUTPUT_FILE || $output == self::OUTPUT_BOTH) {
            $config = Registry::getInstance()->get('config');
            $date = new DateTime();
            if($config['logging']['storage'] == 'filesystem') {
                $log_text = '[' . $date->format('Y-m-d H:i:s') . '] ' . print_r($log, true);
                if ($config['logging']['mode'] == 'ALL' || $type == $config['logging']['mode']) {
                    $log_dir = self::getLogFilePath();
                    if (!is_dir($log_dir)) {
                        mkdir($log_dir, 0644, true);
                    }
                    if (file_put_contents($log_dir . DIRECTORY_SEPARATOR . $log_file_name . '.log', $log_text . "\n", FILE_APPEND) == false) {
                        throw new Exception('Failed to write logfile ' . $log_dir . DIRECTORY_SEPARATOR . $filename);
                    }
                }
            } else if($config['logging']['storage'] == 'database') {
                if ($config['logging']['mode'] == 'ALL' || $type == $config['logging']['mode']) {
                    $log_set = new Log();
                    $log_set->type = $type;
                    $log_set->trace = json_encode(self::getTrace());
                    $log_set->category = $filename;
                    $log_set->date = $date;
                    $log_set->text = print_r($log, true);
                    $log_set->create();
                }
            }
        }
        //print_r(json_encode(self::getTrace()));
        return $log_text;
    }

    /**
     * @return string
     */
    static function getLogFilePath() {
        $config = Registry::getInstance()->get('config');
        return isset($config['logging']['log_file_path']) ? str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['logging']['log_file_path']) : APPLICATION_PATH . DIRECTORY_SEPARATOR . 'logs';
    }

    static function getTrace()
    {
        $traces = [];
        foreach (debug_backtrace() as $trace) {
            if($trace['function'] != 'getTrace') {
                $traces[] = $trace['file'] . ':' . $trace['line'] . ' ' . $trace['class'] . $trace['type'] . $trace['function'] . '()';
            }
        }
        return array_reverse($traces);
    }
}
