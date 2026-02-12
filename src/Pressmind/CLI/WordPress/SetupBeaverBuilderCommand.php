<?php

namespace Pressmind\CLI\WordPress;

use Pressmind\CLI\AbstractCommand;

/**
 * Setup Beaver Builder Command
 *
 * Configures Beaver Builder with recommended settings for the Travelshop theme:
 * - Enables BB only for post type 'page'
 * - Disables BB templates
 * - Sets default margins and paddings to '0'
 * - Sets up basic security roles
 *
 * Usage:
 *   php cli/setup_beaverbuilder.php
 */
class SetupBeaverBuilderCommand extends AbstractCommand
{
    protected function execute(): int
    {
        Tools::boot(true);

        $this->output->info('enable beaverbuilder only for post type \'page\'');
        update_option('_fl_builder_post_types', ['page']);

        $this->output->info('disable beaver builder template');
        update_option('_fl_builder_enabled_templates', 'disabled');

        $this->output->info('set default margins and paddings to \'0\' in global settings');
        $option = get_option('_fl_builder_settings');
        $option->row_margins = '0';
        $option->row_padding = '0';
        $option->column_margins = '0';
        $option->column_padding = '0';
        update_option('_fl_builder_settings', $option);

        $this->output->info('setup basic security roles, check settings->beaverbuilder->user access');
        update_option('_fl_builder_user_access', unserialize('a:5:{s:14:"builder_access";a:4:{s:13:"administrator";b:1;s:6:"editor";b:1;s:6:"author";b:0;s:11:"contributor";b:0;}s:20:"unrestricted_editing";a:4:{s:13:"administrator";b:1;s:6:"editor";b:1;s:6:"author";b:1;s:11:"contributor";b:0;}s:19:"global_node_editing";a:4:{s:13:"administrator";b:1;s:6:"editor";b:1;s:6:"author";b:1;s:11:"contributor";b:0;}s:13:"builder_admin";a:4:{s:13:"administrator";b:1;s:6:"editor";b:0;s:6:"author";b:0;s:11:"contributor";b:0;}s:22:"template_data_exporter";a:4:{s:13:"administrator";b:1;s:6:"editor";b:0;s:6:"author";b:0;s:11:"contributor";b:0;}}'));

        $this->output->success('done');

        return 0;
    }
}
