<?php



require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('./classes/rules_form.php');
require_once('./classes/rule.php');

global $PAGE, $CFG, $OUTPUT, $DB;

admin_externalpage_setup('local_dynamicaudience');

require_capability('moodle/cohort:manage', context_system::instance());

$cohortid = required_param('cohortid', PARAM_INT);

$title = get_string('pluginname', 'local_dynamicaudience');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$cohortid]));

if (! $cohort = $DB->get_record('cohort', ['id'=>$cohortid])) {
  throw new Exception('Cohort does not exist');
}

echo $OUTPUT->header();
echo $OUTPUT->heading("{$title} rules: {$cohort->name}");

$new_rule = new single_button(new moodle_url('/local/dynamicaudience/rule.php', ['cohortid'=>$cohortid]), 'Add rule', 'get');
echo $OUTPUT->render($new_rule);

$membercount = $DB->count_records('cohort_members', ['cohortid'=>$cohortid]);
$view_members = new single_button(new moodle_url('/local/dynamicaudience/members.php', ['id'=>$cohortid]), "View members ({$membercount})", 'get');
echo $OUTPUT->render($view_members);

\local_dynamicaudience\rule::table_sql($cohortid);

echo $OUTPUT->footer();
