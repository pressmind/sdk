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
        foreach (Task::listAll(['active' => 1, 'running' => 0]) as $task) {
            $this->_addTask($task);
        }
    }

    /**
     * @throws Exception
     */
    public function walk() {
        Writer::write('Starting scheduled jobs for ' . count($this->_getTasks()) . ' tasks', Writer::OUTPUT_FILE, 'scheduler');
        $i=0;
        foreach ($this->_getTasks() as $task) {
            $i++;
            Writer::write('Job ' . $i . '("' . $task->getValue('name') . '") starting ...', Writer::OUTPUT_FILE, 'scheduler');
            try {
                Writer::write('Job ' . $i . '("' . $task->getValue('name') . '") completed with response: ' . $task->run(), Writer::OUTPUT_FILE, 'scheduler');
            } catch (Exception $e) {
                Writer::write('Job ' . $i . '("' . $task->getValue('name') . '") failed: ' .  $e->getMessage(), Writer::OUTPUT_FILE, 'scheduler', Writer::TYPE_ERROR);
            }
        }
    }
}
