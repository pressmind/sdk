<?php
namespace Pressmind\Image\Processor\Adapter;


use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Log\Writer;
use Pressmind\Storage\File;

class ImageMagickCLI implements AdapterInterface
{
    public function process($config, $file, $derivative_name)
    {
        if(!empty(exec('which convert'))) {

            if ($config->crop == true) {
                $command = 'convert - -resize ' . $config->max_width . '^x' . $config->max_height . '^ -gravity ' . $config->horizontal_crop . ' -crop ' . $config->max_width . 'x' . $config->max_height . '+0+0 -';
            } else {
                $command = 'convert - -resize ' . $config->max_width . '^x' . $config->max_height . '^ -';
            }

            Writer::write($command, WRITER::OUTPUT_FILE, 'image_processor', Writer::TYPE_INFO);

            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["file", "/tmp/error-output.txt", "a"],
            ];

            $path_info = pathinfo($file->name);
            $new_name = $path_info['filename'] . '_' . $derivative_name . '.' . $path_info['extension'];
            $derivative_file = new File($file->getBucket());
            $derivative_file->name = $new_name;

            $process = proc_open($command, $descriptorspec, $pipes);

            if (is_resource($process)) {
                fwrite($pipes[0], $file->content); // file_get_contents('php://stdin')
                fclose($pipes[0]);

                $derivative_file->content = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $return_value = proc_close($process);
            } else {
                Writer::write('proc_open() failed', WRITER::OUTPUT_FILE, 'image_processor', Writer::TYPE_ERROR);
            }

            return $derivative_file;

        } else {
            Writer::write('convert (imagemagick) is not installed on system: "which convert" returned null', WRITER::OUTPUT_FILE, 'image_processor', Writer::TYPE_ERROR);
        }
    }
}
