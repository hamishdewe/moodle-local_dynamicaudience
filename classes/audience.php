<?php

namespace local_dynamicaudience;

use html_writer;
use table_sql;
use context_system;
use moodle_url;
use core\plugininfo\enrol;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/tablelib.php");
require_once("{$CFG->dirroot}/cohort/lib.php");

/**
 * Class profilecohort
 * @package local_profilecohort
 * @copyright 2016 Davo Smith, Synergy Learning UK on behalf of Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience {

    public static function member_sql($cohortid, $query) {
        global $CFG, $DB;

        $table = new member_table('local_dynamicaudience_members-table');
        $table->downloadable = false;
        $where = "member.cohortid = :p_cohortid";
        $params = ['p_cohortid'=>$cohortid];
        if ($query) {
          $fullname = $DB->sql_fullname();
          $like = $DB->sql_like($fullname, ":p_query", false, false);
          $where .= " and {$like}";
          $params['p_query'] = "%{$query}%";
        }
        $fullname = $DB->sql_fullname('usr.firstname', 'usr.lastname');
        $table->set_sql(
          "usr.id, member.timeadded, {$fullname} 'name'",
          "{cohort_members} as member join {user} as usr on usr.id = member.userid",
          $where,
          $params);
        $table->define_baseurl(new moodle_url("/local/dynamicaudience/members.php", ['id'=>$cohortid]));
        $table->out(20, true);

    }

    public static function get_dynamicaudiences() {
        global $DB;

        return $DB->get_records('cohort', ['component'=>'local_dynamicaudience']);
    }

    public static function get_affected_dynamicaudiences($table) {
        global $DB;

        $like = $DB->sql_like('field', ':table');
        $sql = "select distinct cohortid from {local_dynamicaudience_rules} where {$like}";
        $params = ['table'=>"%{$table}%"];
        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }

    public static function is_dynamic($cohortid) {
        global $DB;

        return $DB->record_exists('cohort', ['id'=>$cohortid,'component'=>'local_dynamicaudience']);
    }

    public static function observe_all($event) {

        switch ($event->eventname) {
            // Cohort changed, process membership of cohort
            case '\core\event\cohort_created':
            case '\core\event\cohort_deleted':
            case '\core\event\cohort_updated': {
                if (self::is_dynamic($event->objectid)) {
                    self::try_add_users($event->objectid);
                }
                break;
            }
            // Possible target changed, affected user is relateduser
            case '\core\event\cohort_member_added':
            case '\core\event\cohort_member_removed':
            case '\core\event\course_completed':
            case '\core\event\user_updated':
            case '\core\event\user_deleted':
            case '\core\event\user_created':
            case '\core\event\user_enrolment_created':
            case '\core\event\user_enrolment_updated':
            case '\core\event\user_enrolment_deleted':
            case '\core\event\user_info_field_deleted':
            case '\core\event\user_info_field_updated': {
                foreach (self::get_affected_dynamicaudiences($event->objecttable) as $cohort) {
                    // Possible circular reference when doing cohort member add/remove
                    if (($event->eventname == '\core\event\cohort_member_added' || $event->eventname == '\core\event\cohort_member_removed') && $event->objectid == $cohort->cohortid) {
                        break;
                    } else {
                        self::try_add_user($cohort->cohortid, $event->relateduserid);
                    }
                }
                break;
            }
            // Possible target changed, userid is unknown
            case '\core\event\course_deleted':
            case '\core\event\course_restored':
            case '\core\event\course_updated':
            case '\core\event\enrol_instance_updated':
            case '\core\event\enrol_instance_created': {
                //echo "<div>Possible target changed, userid is unknown</div>";
                foreach (self::get_affected_dynamicaudiences($event->target) as $cohort) {
                    self::try_add_users($cohort->cohortid);
                }
                break;
            }
        }
    }

    static function try_add_users($cohortid) {
        global $DB;

        $sql = "select distinct usr.id from {user} usr";

        $joins = [];
        $where = [];
        $rules = $DB->get_records('local_dynamicaudience_rules', ['cohortid'=>$cohortid]);
        $params = [];
        foreach ($rules as $rule) {
            rule::append_clause_params($rule, $joins, $where, $params);
        }

        foreach ($joins as $join) {
            $sql .= " JOIN {$join}";
        }
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $members = $DB->get_records('cohort_members', ['cohortid'=>$cohortid]);
        //var_dump($sql); var_dump($params); die;
        $currentmembers = $DB->get_records_sql($sql, $params);
        // remove members if not in currentmembers list
        foreach ($members as $member) {
            if (!isset($currentmembers[$member->userid])) {
                cohort_remove_member($cohortid, $member->userid);
            }
        }
        // add all the current members. Function does sanity check so don't bother doing it here again
        // Only add if there are rules set
        if (count($rules) > 0) {
            foreach ($currentmembers as $member) {
                cohort_add_member($cohortid, $member->id);
            }
        } else {
            foreach ($members as $member) {
                cohort_remove_member($cohortid, $member->userid);
            }
        }

        return $sql;
    }

    static function try_add_user($cohortid, $userid) {
        global $DB;

        if (!$DB->record_exists('cohort', ['id'=>$cohortid])) {
          return;
        }

        $sql = "select distinct usr.id from {user} usr";

        $joins = [];
        $where = ["usr.id = {$userid}"];
        $rules = $DB->get_records('local_dynamicaudience_rules', ['cohortid'=>$cohortid]);
        $params = [];
        foreach ($rules as $rule) {
            rule::append_clause_params($rule, $joins, $where, $params);
        }

        foreach ($joins as $join) {
            $sql .= " JOIN {$join}";
        }
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $matches = $DB->get_records_sql($sql, $params);
        if (count($matches) > 0 and count($rules) > 0) {
            cohort_add_member($cohortid, $userid);
        } else {
            cohort_remove_member($cohortid, $userid);
        }

        return $sql;
    }

    public function process() {
        $action = optional_param('action', null, PARAM_ALPHA);
        $id = optional_param('id', null, PARAM_INT);
        $sesskey = optional_param('sesskey', null, PARAM_RAW);
        $returnurl = new moodle_url('/local/dynamicaudience/index.php');
        if ($action && $id && $sesskey && $sesskey == sesskey()) {
          switch ($action) {
            case 'delete': {
              $this->delete($id);
              redirect($returnurl);
            }
            case 'hide': {
              $this->hide($id);
              redirect($returnurl);
            }
            case 'show': {
              $this->show($id);
              redirect($returnurl);
            }
          }
        }
    }

    public function delete($id) {
        global $DB;

        require_capability('moodle/cohort:manage', context_system::instance());
        if ($cohort = $DB->get_record('cohort', ['id'=>$id])) {
            cohort_delete_cohort($cohort);
        }
    }

    public function hide($id) {
      global $DB;

      require_capability('moodle/cohort:manage', context_system::instance());
      if ($cohort = $DB->get_record('cohort', ['id'=>$id])) {
        if ($cohort->visible) {
          $record = (object)array('id' => $id, 'visible' => 0, 'contextid' => 1);
          cohort_update_cohort($record);
        }
      }
    }

    public function show($id) {
      global $DB;

      require_capability('moodle/cohort:manage', context_system::instance());
      if ($cohort = $DB->get_record('cohort', ['id'=>$id])) {
        if (!$cohort->visible) {
          $record = (object)array('id' => $id, 'visible' => 1, 'contextid' => 1);
          cohort_update_cohort($record);
        }
      }
    }

    public function table_sql() {
        global $CFG, $PAGE, $OUTPUT, $DB;

        $title = get_string('pluginname', 'local_dynamicaudience');

        $download = optional_param('download', '', PARAM_ALPHA);
        $query = optional_param('search', '', PARAM_TEXT);

        $table = new audience_table('local_dynamicaudience-table');
        $table->downloadable = true;
        $table->is_downloading($download, 'dynamic-audience', 'audiences');


        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data
            // Print the page header
            $PAGE->set_title($title);
            $PAGE->set_heading($title);

            echo $OUTPUT->header();

            // Add search form.
            $search  = html_writer::start_tag('form', array('id'=>'searchcohortquery', 'method'=>'get', 'class' => 'form-inline search-cohort'));
            $search .= html_writer::start_div('m-b-1');
            $search .= html_writer::label(get_string('searchcohort', 'cohort'), 'cohort_search_q', true,
                    array('class' => 'm-r-1')); // No : in form labels!
            $search .= html_writer::empty_tag('input', array('id' => 'cohort_search_q', 'type' => 'text', 'name' => 'search',
                    'value' => $query, 'class' => 'form-control m-r-1'));
            $search .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('search', 'cohort'),
                    'class' => 'btn btn-secondary'));
            $search .= html_writer::end_div();
            $search .= html_writer::end_tag('form');
            echo $search;

            echo html_writer::tag('a', get_string('addcohort', 'cohort'), ['class'=>'btn btn-secondary', 'href'=>"$CFG->wwwroot/cohort/edit.php?contextid=1"]);
            echo html_writer::span('&nbsp;');
            echo html_writer::tag('a', get_string('addaudience', 'local_dynamicaudience'), ['class'=>'btn btn-primary', 'href'=>"$CFG->wwwroot/local/dynamicaudience/edit.php"]);

            echo html_writer::start_div();

        }

        // Work out the sql for the table.
        $where = "component is not null";
        $params = [];
        if ($query) {
          $params = ['like_name'=>"%{$query}%", 'like_idnumber'=>"%{$query}%"];
          $likename = $DB->sql_like('name', ":like_name", false, false);
          $likeidnumber = $DB->sql_like('idnumber', ":like_idnumber", false, false);
          $where .= " and ({$likename} or {$likeidnumber})";
        }
        //$table->set_sql("id, name, idnumber, visible, timecreated, timemodified, theme, '' as action", "{cohort}", "component = 'local_dynamicaudience'");
        $table->set_sql(
          "id, name, idnumber, description, visible, '' as memberscount, timecreated, timemodified, component, '' as action",
          "{cohort}",
          $where,
        $params);

        $table->define_baseurl("$CFG->wwwroot/local/dynamicaudience/index.php");

        $table->out(20, true);

        if (!$table->is_downloading()) {
            echo html_writer::end_div();
            echo $OUTPUT->footer();
        }
    }
}

class audience_table extends table_sql {

    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns(['name', 'idnumber', 'description', 'memberscount', 'timecreated', 'timemodified', 'component', 'action']);
        $this->define_headers([
          get_string('name', 'cohort'),
          get_string('idnumber', 'cohort'),
          get_string('description', 'cohort'),
          get_string('memberscount', 'cohort'),
          get_string('tableheader_created', 'local_dynamicaudience'),
          get_string('tableheader_modified', 'local_dynamicaudience'),
          get_string('component', 'cohort'),
          get_string('edit', 'core')]);
    }

    function col_component($record) {
      switch ($record->component) {
        case '':
          return get_string('nocomponent', 'cohort');
        default:
          return get_string('pluginname', $record->component);
      }
    }

    function col_name($record) {
      global $OUTPUT;

      $record->contextid = 1;
      $tmpl = new \core_cohort\output\cohortname($record);
      return $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));
    }

    function col_idnumber($record) {
      global $OUTPUT;

      $record->contextid = 1;
      $tmpl = new \core_cohort\output\cohortidnumber($record);
      return $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));
    }

    function col_memberscount($record) {
      global $DB;

      return $DB->count_records('cohort_members', array('cohortid'=>$record->id));
    }

    function col_action($record) {
        global $OUTPUT;

        $buttons = [];

        switch ($record->component) {
          case 'local_dynamicaudience': {
            $baseurl = new moodle_url('/local/dynamicaudience/index.php');
            $urlparams = array('id' => $record->id, 'returnurl' => $baseurl->out_as_local_url());
            $showhideurl = new moodle_url('/local/dynamicaudience/index.php', $urlparams + array('sesskey' => sesskey()));
            if ($record->visible) {
              $showhideurl->param('action', 'hide');
              $visibleimg = $OUTPUT->pix_icon('t/hide', get_string('hide'));
              $buttons[] = html_writer::link($showhideurl, $visibleimg, array('title' => get_string('hide')));
            } else {
              $showhideurl->param('action', 'show');
              $visibleimg = $OUTPUT->pix_icon('t/show', get_string('show'));
              $buttons[] = html_writer::link($showhideurl, $visibleimg, array('title' => get_string('show')));
            }
            $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/index.php', ['id'=>$record->id,'action'=>'delete','sesskey'=>sesskey()]),
              $OUTPUT->pix_icon('t/delete', get_string('delete')),
              array('title' => get_string('delete')));
            $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/edit.php', ['id'=>$record->id]),
              $OUTPUT->pix_icon('t/edit', get_string('edit')),
              array('title' => get_string('edit')));
              $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/ruleset.php', ['cohortid'=>$record->id]),
                  $OUTPUT->pix_icon('e/numbered_list', get_string('rules', 'local_dynamicaudience')),
                  array('title' => get_string('assign', 'core_cohort')));
            $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/members.php', ['id'=>$record->id]),
                $OUTPUT->pix_icon('i/users', get_string('assign', 'core_cohort')),
                array('title' => get_string('assign', 'core_cohort')));
            break;
          }
          case '': {
            $baseurl = new moodle_url('/local/dynamicaudience/index.php');
            $urlparams = array('id' => $record->id, 'returnurl' => $baseurl->out_as_local_url());
            $showhideurl = new moodle_url('/cohort/edit.php', $urlparams + array('sesskey' => sesskey()));
            if ($record->visible) {
              $showhideurl->param('hide', 1);
              $visibleimg = $OUTPUT->pix_icon('t/hide', get_string('hide'));
              $buttons[] = html_writer::link($showhideurl, $visibleimg, array('title' => get_string('hide')));
            } else {
              $showhideurl->param('show', 1);
              $visibleimg = $OUTPUT->pix_icon('t/show', get_string('show'));
              $buttons[] = html_writer::link($showhideurl, $visibleimg, array('title' => get_string('show')));
            }
            $buttons[] = html_writer::link(new moodle_url('/cohort/edit.php', $urlparams + array('delete' => 1)),
              $OUTPUT->pix_icon('t/delete', get_string('delete')),
              array('title' => get_string('delete')));
            $buttons[] = html_writer::link(new moodle_url('/cohort/edit.php', $urlparams),
              $OUTPUT->pix_icon('t/edit', get_string('edit')),
              array('title' => get_string('edit')));
              $buttons[] = html_writer::link(new moodle_url('/cohort/assign.php', $urlparams),
                $OUTPUT->pix_icon('i/users', get_string('assign', 'core_cohort')),
                array('title' => get_string('assign', 'core_cohort')));
            break;
          }
          default: {
            $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/index.php', ['id'=>$record->id,'action'=>'delete','sesskey'=>sesskey()]),
              $OUTPUT->pix_icon('t/delete', get_string('delete')),
              array('title' => get_string('delete')));
          }
        }
        return implode('', $buttons);
    }

    function col_timecreated($record) {
        if ($record->timecreated) {
            return $this->to_userdate($record->timecreated);
        } else {
            return '';
        }
    }

    function col_timemodified($record) {
        if ($record->timecreated) {
            return $this->to_userdate($record->timecreated);
        } else {
            return '';
        }
    }

    function to_userdate($date) {
        return userdate($date);
    }

}

class member_table extends table_sql {

    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns(['name', 'timeadded']);
        $this->define_headers([get_string('tableheader_name', 'local_dynamicaudience'), get_string('tableheader_added', 'local_dynamicaudience')]);
    }

    function col_name($record) {
      global $OUTPUT;
      $url = new moodle_url('/user/profile.php', ['id'=>$record->id]);
      return html_writer::link($url->out(), $record->name);
    }

    function col_timeadded($record) {
        if ($record->timeadded) {
            return $this->to_userdate($record->timeadded);
        } else {
            return '';
        }
    }

    function to_userdate($date) {
        return userdate($date);
    }

}
