<?php


namespace Pressmind\System;


class Requirements
{

    public function getRequirements()
    {
        return [
            'PHP' => [],
            'extensions' => [
                'yaml',
                'curl',
                'bcmath',
                'xml',
                'PDO',
                'imagick'
            ],
            'database' => [],
            'memory' => []
        ];
    }
}
