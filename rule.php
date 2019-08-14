<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local plugin "Profile field based cohort membership" - Main entry point
 *
 * @package   local_profilecohort
 * @copyright 2016 Davo Smith, Synergy Learning UK on behalf of Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 global $PAGE, $CFG, $OUTPUT, $DB;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once('./classes/rules_form.php');


admin_externalpage_setup('local_dynamicaudience');

require_capability('moodle/cohort:manage', context_system::instance());

$id = optional_param('id', null, PARAM_INT);
$cohortid = optional_param('cohortid', null, PARAM_INT);

$title = get_string('pluginname', 'local_dynamicaudience');
if ($id) {
  $heading = get_string('editrule', 'local_dynamicaudience');
} else {
  $heading = get_string('addrule', 'local_dynamicaudience');
}
$PAGE->set_title("{$title}: {$heading}");
$PAGE->set_heading($heading);



$mform = new \local_dynamicaudience\rules_form();
$mform->process();

if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$cohortid]), '', 0);
} else if ($data = $mform->get_data()) {
  echo $OUTPUT->header();
  $mform->display();
  echo $OUTPUT->footer();
} else {

    echo $OUTPUT->header();

    $mform->display();
    echo $OUTPUT->footer();
}
