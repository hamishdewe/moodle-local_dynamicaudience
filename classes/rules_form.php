<?php

namespace local_dynamicaudience;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
//require_once('rule.php');

use coursecat;
use context_system;
use context_coursecat;
use context;
use moodleform;
use moodle_url;
use Exception;
use single_button;

/**
 * Class audience_form
 */
class rules_form extends moodleform {


  // if ($id && $action == 'delete' && $sesskey == sesskey()) {
  //   // Delete requested
  //   if ($confirm && $confirm == md5($id . $sesskey)) {
  //     // do the delete
  //   } else {
  //     echo $OUTPUT->header();
  //     echo $OUTPUT->heading(get_string('deleteuser', 'admin'));
  //
  //     $optionsyes = array('action'=>$delete, 'confirm'=>md5($id . $sesskey), 'sesskey'=>sesskey());
  //     $deleteurl = new moodle_url($returnurl, $optionsyes);
  //     $deletebutton = new single_button($deleteurl, get_string('delete'), 'post');
  //
  //     echo $OUTPUT->confirm(get_string('deletecheckfull', '', "'$fullname'"), $deletebutton, $returnurl);
  //     echo $OUTPUT->footer();
  //     die;
  //   }
  // }

    function process() {
        global $DB, $OUTPUT;

        $id = optional_param('id', null, PARAM_INT);
        $cohortid = optional_param('cohortid', null, PARAM_INT);
        $action = optional_param('action', null, PARAM_ALPHA);
        $sesskey = optional_param('sesskey', null, PARAM_ALPHA);
        $confirm = optional_param('confirm', null, PARAM_RAW);
        if ($cohortid && $id && $action && $action == 'delete' && $sesskey && $sesskey = sesskey()) {
            // delete the rule
            if ($rule = $DB->get_record('local_dynamicaudience_rules', ['id'=>$id])) {
              if ($confirm && $confirm == md5($id . $sesskey)) {
                $DB->delete_records('local_dynamicaudience_rules', ['id'=>$id]);
                $event = \core\event\cohort_updated::create(['objectid'=>$rule->cohortid,'context'=>\context_system::instance()]);
                $event->trigger();
                redirect(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$rule->cohortid]));
              } else {
                echo $OUTPUT->header();
                echo $OUTPUT->heading(get_string('deleterule', 'local_dynamicaudience'));

                $optionsyes = array('id'=>$id,'cohortid'=>$cohortid,'action'=>'delete', 'confirm'=>md5($id . $sesskey), 'sesskey'=>sesskey());
                $deleteurl = new moodle_url('/local/dynamicaudience/rule.php', $optionsyes);
                $deletebutton = new single_button($deleteurl, get_string('delete'), 'post');

                $returnurl = new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$cohortid]);
                echo $OUTPUT->confirm(get_string('confirmdeleterule', 'local_dynamicaudience'), $deletebutton, $returnurl);
                echo $OUTPUT->footer();
                die;
              }

            } else {
              throw new Exception('Invalid request');
            }
        }

        if ($this->is_cancelled()) {
            //Handle form cancel operation, if cancel button is present on form
            redirect(new moodle_url('/local/dynamicaudience/rules.php'), '', 0);
        } else if ($rule = $this->get_data()) {
            // cohortid, field, operator, expected
            $item = new \stdClass();
            $item->cohortid = $cohortid;
            $item->field = $rule->rule['field'];
            if (isset($rule->rule['operator_date'])) {
                $item->operator = $rule->rule['operator_date'];
            } else if (isset($rule->rule['operator_lookup'])) {
                $item->operator = $rule->rule['operator_lookup'];
            } else if (isset($rule->rule['operator_text'])) {
                $item->operator = $rule->rule['operator_text'];
            } else if (isset($rule->rule['operator_bool'])) {
                $item->operator = $rule->rule['operator_bool'];
            }

            $durationkey = "rule[data_duration]";
            if (isset($rule->rule['data_text'])) {
                $item->expected = $rule->rule['data_text'];
            } else if (isset($rule->$durationkey) && ($item->operator == 'duration_gt' || $item->operator == 'duration_lt')) {
                $item->expected = $rule->$durationkey;
            } else if (isset($rule->rule['data_bool'])) {
                $item->expected = $rule->rule['data_bool'];
            } else if (!empty($rule->rule['data_course']) ) {
                $item->expected = implode(',', array_values($rule->rule['data_course']));
            } else if (!empty($rule->rule['data_cohort'])) {
                $item->expected = implode(',', array_values($rule->rule['data_cohort']));
            } else if (isset($rule->rule['data_date'])) {
                $item->expected = $rule->rule['data_date'];
            }

            if ($id) {
              $item->id = $id;
              $DB->update_record('local_dynamicaudience_rules', $item);
            } else {
              $DB->insert_record('local_dynamicaudience_rules', $item);
            }
            $event = \core\event\cohort_updated::create(['objectid'=>$item->cohortid,'context'=>\context_system::instance()]);
            $event->trigger();
            redirect(new moodle_url("/local/dynamicaudience/ruleset.php", ['cohortid'=>$item->cohortid]));
        } else {
            // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
            // or on the first display of the form.

            //Set default data (if any)
            //
            //displays the form
            //$this->display();
        }
    }

    /**
     * Form definition.
     */
    protected function definition() {
      global $DB;
        $mform = $this->_form;

        $id = optional_param('id', null, PARAM_INT);
        $cohortid = optional_param('cohortid', null, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $id);

        $mform->addElement('hidden', 'cohortid');
        $mform->setType('cohortid', PARAM_INT);
        $mform->setDefault('cohortid', $cohortid);
        //$mform->addElement('hidden', 'rulefield-select');

        $operator = rule::get_operator();
        $data_type = rule::get_data_type();
        $fields = rule::get_fields();
        $group = [];
        // Choose field
        $group[] = $mform->createElement('selectgroups', 'field', '', $fields);
        // Choose operator
        $group[] = $mform->createElement('select', 'operator_text', '', $operator['text']);
        $group[] = $mform->createElement('select', 'operator_date', '', $operator['date']);
        $group[] = $mform->createElement('select', 'operator_lookup', '', $operator['lookup']);
        $group[] = $mform->createElement('select', 'operator_bool', '', $operator['bool']);
        // Define value
        $data_text = $mform->createElement('text', 'data_text', ''); // not date, cohort, bool, course || yes text
        $mform->setType('rule[data_text]', PARAM_TEXT);
        $group[] = $data_text;
        $group[] = $mform->createElement('duration', 'data_duration', ''); // not text, cohort, bool, course || yes date
        $group[] = $mform->createElement('date_selector', 'data_date', ''); // not text, cohort, bool, course || yes date
        $group[] = $mform->createElement('selectyesno', 'data_bool', ''); // not text, cohort, course, date || yes bool
        $course_select = $mform->createElement('course', 'data_course', null, ['multiple'=>true,'placeholder'=>'Course']);
        $group[] = $course_select;
        $cohort_select = $mform->createElement('cohort', 'data_cohort', null, ['multiple'=>true,'placeholder'=>'Cohort','exclude'=>[$cohortid]]);
        $group[] = $cohort_select;

        $mform->addGroup($group, 'rule', ''); //get_string('rule', 'local_dynamicaudience'));
        $course_select->setMultiple('rule[data_course]');
        $cohort_select->setMultiple('rule[data_cohort]');

        // Control display of text operators/value
        $mform->hideIf('rule[operator_text]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[operator_text]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[operator_text]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[operator_text]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[data_text]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[data_text]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[data_text]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_text]', 'rule[field]', 'in', $data_type['course']);
        // Control display of date operators
        $mform->hideIf('rule[operator_date]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[operator_date]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[operator_date]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[operator_date]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[operator_duration]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[operator_duration]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[operator_duration]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[operator_duration]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[data_date]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[data_date]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[data_date]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_date]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[data_duration]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[data_duration]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[data_duration]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_duration]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[data_date]', 'rule[operator_date]', 'in', array('duration_ago','duration_after'));
        $mform->hideIf('rule[data_duration]', 'rule[operator_date]', 'in', array('date_gte','date_lte'));
        // Control display of lookup operators
        $mform->hideIf('rule[operator_lookup]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[operator_lookup]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[operator_lookup]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_course][]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[data_course][]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[data_course][]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_course][]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[data_cohort][]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[data_cohort][]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[data_cohort][]', 'rule[field]', 'in', $data_type['bool']);
        $mform->hideIf('rule[data_cohort][]', 'rule[field]', 'in', $data_type['course']);
        // Control display of bool operators
        $mform->hideIf('rule[operator_bool]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[operator_bool]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[operator_bool]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[operator_bool]', 'rule[field]', 'in', $data_type['course']);
        $mform->hideIf('rule[data_bool]', 'rule[field]', 'in', $data_type['text']);
        $mform->hideIf('rule[data_bool]', 'rule[field]', 'in', $data_type['date']);
        $mform->hideIf('rule[data_bool]', 'rule[field]', 'in', $data_type['cohort']);
        $mform->hideIf('rule[data_bool]', 'rule[field]', 'in', $data_type['course']);
        if (isset($id) && $rule = $DB->get_record('local_dynamicaudience_rules', ['id'=>$id])) {
          $mform->setDefault('rule[field]', $rule->field);
          // find datatype from field
          switch ($rule->field) {
            case "user.firstaccess":
            case "user.lastaccess":
            case "user.lastlogin":
            case "user.timemodified":
            case "course.startdate":
            case "course.enddate":
            case "course.timecreated":
            case "course.timemodified":
            case "course_completions.timeenrolled":
            case "course_completions.timestarted":
            case "course_completions.timecompleted": {
              switch ($rule->operator) {
                case "duration_ago":
                case "duration_after": {
                  $mform->setDefault('rule[operator_duration]', $rule->operator);
                  $mform->setDefault('rule[data_duration]', $rule->expected);
                  break;
                }
                default: {
                  $mform->setDefault('rule[operator_date]', $rule->operator);
                  $mform->setDefault('rule[data_date]', $rule->expected);
                }
              }
              break;
            }
            case "cohort.id": {
              $mform->setDefault('rule[operator_lookup]', $rule->operator);
              break;
            }
            case "course.id": {
              $mform->setDefault('rule[operator_lookup]', $rule->operator);
              break;
            }
            case "user.theme":
            case "user.auth":
            case "user.email":
            case "user.institution":
            case "user.department":
            case "user.address":
            case "user.city":
            case "user.country":
            case "user.lang":
            case "user.timezone":
            case "user.description":
            case "course.fullname":
            case "course.shortname":
            case "course.idnumber":
            case "course.summary":
            case "course.format":
            case "course.lang":
            case "course.calendartype": {
              $mform->setDefault('rule[operator_text]', $rule->operator);
              $mform->setDefault('rule[data_text]', $rule->expected);
              break;
            }
            case "user.confirmed":
            case "user.policyagreed":
            case "user.deleted":
            case "user.suspended":
            case "user.emailstop": {
              $mform->setDefault('rule[operator_bool]', $rule->operator);
              $mform->setDefault('rule[data_bool]', $rule->expected);
              break;
            }
          }
        }


        $this->add_action_buttons(false);

    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        return $errors;
    }
}
