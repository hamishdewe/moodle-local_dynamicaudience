<?php

namespace local_dynamicaudience;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
//require_once('rule.php');

use coursecat;
use context_system;
use context_coursecat;
use context;
use moodleform;
use moodle_url;
use Exception;
use get_string;

/**
 * Class audience_form
 */
class audience_form extends moodleform {


    function process($title) {
        global $DB, $OUTPUT;

        if ($this->is_cancelled()) {
            //Handle form cancel operation, if cancel button is present on form
            redirect(new moodle_url('/local/dynamicaudience/index.php'), '', 0);
        } else if ($data = $this->get_data()) {
            $data->description = $data->description_editor['text'];
            $data->descriptionformat = $data->description_editor['format'];
            if ($data->id) {
                cohort_update_cohort($data);
            } else {
                $data->id = cohort_add_cohort($data);
            }
            $this->rules = true;
            $this->set_data($data);
            redirect(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$data->id]), '', 0);
            //$this->display();
        } else {

            // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
            // or on the first display of the form.
            $id = optional_param('id', null, PARAM_INT);
            if ($id) {
                if ($cohort = $DB->get_record('cohort', array('id'=>$id,'component'=>'local_dynamicaudience'), "*")) {
                    $cohort->description_editor['text'] = $cohort->description;
                    $cohort->description_editor['format'] = $cohort->descriptionformat;
                    $this->set_data($cohort);
                    $this->rules = true;
                } else {
                    throw new Exception('Cohort either does not exist, or is not a dynamic audience');
                }
            }
            //Set default data (if any)
            //
            //displays the form
            echo $OUTPUT->header();
            echo $OUTPUT->heading($title);
            $this->display();
            echo $OUTPUT->footer();
        }
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $cohort = $this->_customdata['data'];

        $fields = rule::get_fields();

        $mform->addElement('header', 'audience', 'Audience');

        $mform->addElement('text', 'name', get_string('name', 'cohort'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $options = isset($cohort->contextid)
                      ? $this->get_category_options($cohort->contextid)
                      : $this->get_category_options(context_system::instance()->id);
        $mform->addElement('select', 'contextid', get_string('context', 'role'), $options);

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'cohort'), 'maxlength="254" size="50"');
        $mform->setType('idnumber', PARAM_RAW); // Idnumbers are plain text, must not be changed.

        $mform->addElement('advcheckbox', 'visible', get_string('visible', 'cohort'));
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'visible', 'cohort');

        $mform->addElement('editor', 'description_editor', get_string('description', 'cohort'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        if (!empty($CFG->allowcohortthemes)) {
            $themes = array_merge(array('' => get_string('forceno')), cohort_get_list_of_themes());
            $mform->addElement('select', 'theme', get_string('forcetheme'), $themes);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'component');
        $mform->setDefault('component', 'local_dynamicaudience');
        $mform->setType('component', PARAM_ALPHAEXT);

        if (isset($this->_customdata['returnurl'])) {
            $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']->out_as_local_url());
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        if ($this->get_data()) {
            $mform->addElement('selectgroups', 'rulefield', 'Rule field', $fields);
        }



        $this->add_action_buttons();

        $this->set_data($cohort);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $idnumber = trim($data['idnumber']);
        if ($idnumber === '') {
            // Fine, empty is ok.

        } else if ($data['id']) {
            $current = $DB->get_record('cohort', array('id'=>$data['id']), '*', MUST_EXIST);
            if ($current->idnumber !== $idnumber) {
                if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                    $errors['idnumber'] = get_string('duplicateidnumber', 'cohort');
                }
            }

        } else {
            if ($DB->record_exists('cohort', array('idnumber'=>$idnumber))) {
                $errors['idnumber'] = get_string('duplicateidnumber', 'cohort');
            }
        }

        return $errors;
    }



    protected function get_category_options($currentcontextid) {
        global $CFG;
        require_once($CFG->libdir. '/coursecatlib.php');
        $displaylist = coursecat::make_categories_list('moodle/cohort:manage');
        $options = array();
        $syscontext = context_system::instance();
        if (has_capability('moodle/cohort:manage', $syscontext)) {
            $options[$syscontext->id] = $syscontext->get_context_name();
        }
        foreach ($displaylist as $cid=>$name) {
            $context = context_coursecat::instance($cid);
            $options[$context->id] = $name;
        }
        // Always add current - this is not likely, but if the logic gets changed it might be a problem.
        if (!isset($options[$currentcontextid])) {
            $context = context::instance_by_id($currentcontextid, MUST_EXIST);
            $options[$context->id] = $syscontext->get_context_name();
        }
        return $options;
    }
}
