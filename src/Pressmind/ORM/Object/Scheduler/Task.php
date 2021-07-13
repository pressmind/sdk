<?php


namespace Pressmind\ORM\Object\Scheduler;


use DateTime;
use Error;
use Exception;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Scheduler\Task\Method;

/**
 * Class Task
 * @package Pressmind\ORM\Object\Scheduler
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property string $schedule
 * @property DateTime $last_run
 * @property boolean $active
 * @property boolean $running
 * @property integer $error_count
 * @property string $class_name
 * @property string $construct_parameters
 * @property Method[] $methods
 */
class Task extends AbstractObject
{
    protected $_disable_cache_permanently = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_scheduler_task',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'schedule' => [
                'title' => 'schedule',
                'name' => 'schedule',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'last_run' => [
                'title' => 'last_run',
                'name' => 'last_run',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'running' => [
                'title' => 'running',
                'name' => 'running',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'error_count' => [
                'title' => 'error_count',
                'name' => 'error_count',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'class_name' => [
                'title' => 'class_name',
                'name' => 'class_name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'construct_parameters' => [
                'title' => 'construct_parameters',
                'name' => 'construct_parameters',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'methods' => [
                'title' => 'methods',
                'name' => 'methods',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_task',
                    'class' => Method::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ],
    ];

    /**
     * @return string
     * @throws Exception
     */
    public function run()
    {
        $return = '';
        $this->running = true;
        $this->update();
        $classname = $this->class_name;
        try {
            $obj = new $classname();
            foreach ($this->methods as $method) {
                $parameters = is_array(json_decode($method->parameters, true)) ? json_decode($method->parameters, true) : [];
                Writer::write('Processing task "' . $method->name . '"', Writer::OUTPUT_FILE, 'scheduler');
                try {
                    Writer::write('Calling method "' . $method->name . '"', Writer::OUTPUT_FILE, 'scheduler');
                    Writer::write('Parameters "' . print_r($parameters, true) . '"', Writer::OUTPUT_FILE, 'scheduler');
                    $return = call_user_func_array([$obj, $method->name], array_values($parameters));
                    Writer::write('Task "' . $method->name . '" returned: ' . $return, Writer::OUTPUT_FILE, 'scheduler');
                } catch (Exception $e) {
                    Writer::write('Error while processing task ' . $method->name . ' in job' . $this->name . ': ' . $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
                    $this->error_count++;
                } catch (Error $e) {
                    Writer::write('Error while processing task ' . $method->name . ' in job' . $this->name . ': ' . $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
                    $this->error_count++;
                }
            }
        } catch (Exception $e) {
            Writer::write('Error while processing job' . $this->name . ': ' . $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
            $this->error_count++;
            $return = $e->getMessage();
        } catch (Error $e) {
            Writer::write('Error while processing job' . $this->name . ': ' . $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
            $this->error_count++;
            $return = $e->getMessage();
        }
        $this->running = false;
        $this->last_run = new DateTime();
        $this->update();
        return $return;
    }

    public function failOverCheck()
    {
        $schedule = json_decode($this->schedule);
        $now = new DateTime();
        $diff = $now->diff($this->last_run);
        $total_minutes_diff = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        if($total_minutes_diff >= $schedule->max_running_time_in_minutes) {
            $this->running = false;
            $this->update();
            return 'Task is in running state for ' . $total_minutes_diff . ' minutes. Reset running to false';
        }
        return false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function shallRun() {
        if($this->running === true) {
            return false;
        }
        $schedule = json_decode($this->schedule);
        switch ($schedule->type) {
            case 'Minutely': {
                return $this->_checkMinutelySchedule($schedule->value);
            }
            case 'Daily': {
                return $this->_checkDailySchedule($schedule->time, $schedule->value);
            }
        }
        return false;
    }

    /**
     * @param $period
     * @return bool
     * @throws Exception
     */
    private function _checkMinutelySchedule($period)
    {
        $origin = $this->last_run;
        $target = new DateTime();
        return $target->diff($origin)->i >= intval($period);
    }

    /**
     * @param $time
     * @param $value
     * @return bool
     * @throws Exception
     */
    private function _checkDailySchedule($time, $value) {
        if(strtolower($time) == 'fixed') {
            $origin = new DateTime();
            $target = DateTime::createFromFormat('H:i', $value);
            $diff = $target->diff($origin);
            return ($diff->h * 60) + $diff->i == 0;
        }
        return false;
    }
}
