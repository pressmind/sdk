<?php


namespace Pressmind\Log;


use DateTime;
use Exception;
use Pressmind\ORM\Object\Log;
use Pressmind\Registry;

class Service
{
    /**
     * @var array
     */
    private $_config;

    /**
     * @throws Exception
     */
    public function cleanUp()
    {
        $this->_config = Registry::getInstance()->get('config')['logging'];
        switch($this->_config['storage']) {
            case 'filesystem':
                $this->_cleanUpFilesystem();
                break;
            case 'database':
                $this->_cleanUpDatabase();
                break;
        }
        return 'Log cleanup for ' . $this->_config['storage'] . ' has run';
    }

    private function _cleanUpFilesystem()
    {

    }

    /**
     * @throws Exception
     */
    private function _cleanUpDatabase()
    {
        $date = new DateTime();
        $date->modify('-' . $this->_config['lifetime'] . ' seconds');
        $filters = [
            'date' => ['<', $date->format('Y-m-d H:i:s')]
        ];
        if(is_array($this->_config['keep_log_types'])) {
            $filters['type'] = ['not in', implode(',', $this->_config['keep_log_types'])];
        }
        $logs = Log::listAll($filters);
        foreach ($logs as $log) {
            $log->delete();
        }
    }
}
