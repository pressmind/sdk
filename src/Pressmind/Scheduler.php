<?php


namespace Pressmind;


use DateTime;
use Exception;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Scheduler\Task;

class Scheduler
{
    /**
     * @var Task[]
     */
    private $_tasks = [];

    public function __construct() {
        $this->_findTasks();
    }

    private function _addTask($pTask) {
        array_push($this->_tasks,$pTask);
    }

    private function _getTasks() {
        return $this->_tasks;
    }

    private function _findTasks() {
        /** @var Task $task */
        foreach (Task::listAll(['active' => 1, 'running' => 0]) as $task) {
            if($task->shallRun()) {
                $this->_addTask($task);
            }
        }
        Writer::write('Found ' . count($this->_getTasks()) . ' jobs', Writer::OUTPUT_FILE, 'scheduler');
    }

    /**
     * @throws Exception
     */
    public function walk() {
        if(count($this->_getTasks()) > 0) {
            Writer::write('Processing of ' . count($this->_getTasks()) . ' jobs started', Writer::OUTPUT_FILE, 'scheduler');
        }
        $i=0;
        foreach ($this->_getTasks() as $task) {
            $i++;
            Writer::write('Job ' . $i . '("' . $task->name . '") starting ...', Writer::OUTPUT_FILE, 'scheduler');
            try {
                $response = $task->run();
                Writer::write('Job ' . $i . '("' . $task->name . '") completed with response: ' . $response, Writer::OUTPUT_FILE, 'scheduler');
            } catch (Exception $e) {
                Writer::write('Job ' . $i . '("' . $task->name . '") failed: ' .  $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
            }
        }
    }
}
