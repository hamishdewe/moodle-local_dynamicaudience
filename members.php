<?php

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

global $PAGE, $CFG, $OUTPUT, $DB;

admin_externalpage_setup('local_dynamicaudience');

require_capability('moodle/cohort:manage', context_system::instance());

$title = get_string('pluginname', 'local_dynamicaudience');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$recalculate = optional_param('recalculate', false, PARAM_BOOL);
$sesskey = optional_param('sesskey', null, PARAM_ALPHANUM);
$query = optional_param('search', '', PARAM_TEXT);
$PAGE->set_url(new moodle_url('/local/dynamicaudience/members.php', ['id'=>$id]));
$manager = new \local_dynamicaudience\rules_form(null, ['id'=>$id]);
$manager->process();
$cohort = $DB->get_record('cohort', ['id'=>$id]);

if ($cohort && $recalculate && $sesskey == sesskey()) {
  \local_dynamicaudience\audience::try_add_users($id);
}

echo $OUTPUT->header();
echo $OUTPUT->heading("{$title} members: {$cohort->name}");

// Add search form.
$search  = html_writer::start_tag('form', array('id'=>'searchcohortquery', 'method'=>'get', 'class' => 'form-inline search-cohort'));
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::empty_tag('input', ['type'=>'hidden', 'name'=>'id', 'value'=>$id]);
$search .= html_writer::empty_tag('input', array('id' => 'cohort_search_q', 'type' => 'text', 'name' => 'search',
        'value' => $query, 'class' => 'form-control m-r-1'));
$search .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('searchmembers', 'local_dynamicaudience'),
        'class' => 'btn btn-secondary'));
$search .= html_writer::end_div();
$search .= html_writer::end_tag('form');
echo $search;

$rulecount = $DB->count_records('local_dynamicaudience_rules', ['cohortid'=>$id]);

$view_ruleset = new single_button(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$id]), "View ruleset ($rulecount)", 'get');
echo $OUTPUT->render($view_ruleset);
$recalculate = new single_button(new moodle_url('/local/dynamicaudience/members.php', ['id'=>$id,'recalculate'=>true,'sesskey'=>sesskey()]), "Recalculate members", 'get');
echo $OUTPUT->render($recalculate);

\local_dynamicaudience\audience::member_sql($id, $query);

echo $OUTPUT->footer();
