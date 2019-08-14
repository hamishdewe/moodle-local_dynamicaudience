<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settingspage = new admin_externalpage('local_dynamicaudience', new lang_string('pluginname', 'local_dynamicaudience'),
                                           new moodle_url('/local/dynamicaudience/index.php'), 'moodle/site:config');
    $ADMIN->add('accounts', $settingspage);
}
