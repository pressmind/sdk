<?php

namespace Pressmind\CLI\WordPress;

use Pressmind\CLI\AbstractCommand;

/**
 * Regenerate Images Command
 *
 * Regenerates WordPress attachment image derivatives (thumbnails).
 *
 * Usage:
 *   php cli/regenerate-images.php --all
 *   php cli/regenerate-images.php --id=123
 *   php cli/regenerate-images.php --help
 */
class RegenerateImagesCommand extends AbstractCommand
{
    /**
     * Optional callback to set up theme thumbnail sizes before processing.
     * @var callable|null
     */
    private $themeCallback = null;

    /**
     * Sets the theme callback for thumbnail size setup.
     * Call before run() to inject Travelshop-specific ThemeActivation.
     */
    public function setThemeCallback(callable $callback): void
    {
        $this->themeCallback = $callback;
    }

    protected function execute(): int
    {
        if ($this->hasOption('help') || $this->hasOption('h')) {
            $this->printHelp();
            return 0;
        }

        // Show registered image sizes
        $this->output->writeln('This script regenerates this image formats:', null);
        foreach (RegenerateImages::getAllImageSizes() as $name => $size) {
            $this->output->writeln(' - ' . $name . ' ' . $size['width'] . 'x' . $size['height'], null);
        }

        // Ask for confirmation unless non-interactive
        if (!$this->isNonInteractive()) {
            if (!$this->output->prompt('Regenerate these image sizes?', false)) {
                $this->output->info('aborted by user');
                return 0;
            }
        }

        $postId = null;
        $idOption = $this->getOption('id');

        if ($idOption !== null && $idOption !== true) {
            $postId = (int) $idOption;
            $this->output->writeln('regenerate image derivates from attachment/post id ' . $postId, null);
        } else if ($this->hasOption('all')) {
            $this->output->writeln('regenerate all image derivates', null);
        } else {
            $this->printHelp();
            return 0;
        }

        RegenerateImages::run($postId, $this->themeCallback);

        return 0;
    }

    private function printHelp(): void
    {
        $helptext = "usage:\n";
        $helptext .= "--id=<ID>  example: php regenerate-images.php --id=123   regenerates only the specified wordpress attachment image derivates\n";
        $helptext .= "--all      example: php regenerate-images.php --all       regenerate image derivates from the whole media library\n";
        $this->output->write($helptext, null);
    }
}
