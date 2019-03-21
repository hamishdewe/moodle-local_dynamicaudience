<?php

namespace local_dynamicaudience;

use html_writer;
use table_sql;
use context_system;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/tablelib.php");

class rule {

    static function get_data_type() {
        global $DB;

        $types = [
            "date" => [
                "user.firstaccess",
                "user.lastaccess",
                "user.lastlogin",
                "user.timemodified",
                "course.startdate",
                "course.enddate",
                "course.timecreated",
                "course.timemodified",
                "course_completions.timeenrolled",
                "course_completions.timestarted",
                "course_completions.timecompleted"
            ],
            "cohort" => [
                "cohort.id"
            ],
            "course" => [
                "course.id",
                "course_completions.complete"
            ],
            "text" => [
                "user.theme",
                "user.auth",
                "user.email",
                "user.institution" ,
                "user.department",
                "user.address",
                "user.city",
                "user.country",
                "user.lang",
                "user.timezone" ,
                "user.description",
                "course.fullname",
                "course.shortname",
                "course.idnumber",
                "course.summary",
                "course.format",
                "course.lang"
            ],
            "bool" => [
                "user.confirmed",
                "user.policyagreed",
                "user.deleted",
                "user.suspended",
                "user.emailstop"
            ]
        ];

        foreach ($DB->get_records('user_info_field', null, 'name') as $field) {
            switch ($field->datatype) {
                case 'text':
                case 'textarea':
                case 'menu':
                case 'multiselect': {
                    $types['text'][] = "user_info_data.fieldid.{$field->id}";
                    break;
                }
                case 'datetime': {
                    $types['date'][] = "user_info_data.fieldid.{$field->id}";
                    break;
                }
                case 'checkbox': {
                    $types['bool'][] = "user_info_data.fieldid.{$field->id}";
                    break;
                }
            }
        }
        return $types;
    }

    static function get_fields() {
        global $DB;

        $custom = [];
        foreach ($DB->get_records('user_info_field', null, 'name') as $field) {
            $custom["user_info_data.fieldid.{$field->id}"] = $field->name;
        }

        return [
            get_string('user') => [
                "user.auth"         => get_string('type_auth', 'core_plugin'),
                "user.confirmed"    => get_string('confirmed', 'core_admin'),
                "user.policyagreed" => get_string('policyagreement'),
                "user.deleted"      => get_string('deleted'),
                "user.suspended"    => get_string('suspended', 'auth'),
                "user.email"        => get_string('email'),
                "user.emailstop"    => get_string('emailstop', 'local_dynamicaudience'),
                "user.institution"  => get_string('institution'),
                "user.department"   => get_string('department'),
                "user.address"      => get_string('address'),
                "user.city"         => get_string('city'),
                "user.country"      => get_string('country'),
                "user.lang"         => get_string('language'),
                "user.theme"        => get_string('theme'),
                "user.timezone"     => get_string('timezone'),
                "user.firstaccess"  => get_string('firstsiteaccess'),
                "user.lastaccess"   => get_string('lastsiteaccess'),
                "user.lastlogin"    => get_string('lastlogin'),
                "user.description"  => get_string('userdescription'),
                "user.timemodified" => get_string('lastmodified')
            ],
            get_string('profilefields', 'core_admin') => $custom,
            get_string('cohort', 'core_cohort') => [
                "cohort.id"         => get_string('criteria_8', 'core_badges')
            ],
            get_string('course') => [
                "course.id"                         => get_string('enrolledin', 'local_dynamicaudience'),
                "course_completions.complete"       => 'Course complete',
                "course_completions.timeenrolled"   => get_string('timeenrolled', 'local_dynamicaudience'),
                "course_completions.timestarted"    => get_string('timestarted', 'local_dynamicaudience'),
                "course_completions.timecompleted"  => get_string('datepassed', 'core_completion')
            ]
        ];
    }

    static function get_operator() {
        return [
            'text' => [
                'eq'                => get_string('operator_eq', 'local_dynamicaudience'),
                'neq'               => get_string('operator_neq', 'local_dynamicaudience'),
                'like'              => get_string('operator_like', 'local_dynamicaudience'),
                'notlike'           => get_string('operator_notlike', 'local_dynamicaudience'),
                'regex'             => get_string('operator_regex', 'local_dynamicaudience')
            ],
            'date' => [
                'date_gte'          => get_string('operator_date_gte', 'local_dynamicaudience'),
                'date_lte'          => get_string('operator_date_lte', 'local_dynamicaudience'),
                'duration_ago'      => get_string('operator_duration_ago', 'local_dynamicaudience'),
                'duration_after'    => get_string('operator_duration_after', 'local_dynamicaudience')
            ],
            'lookup' => [
                'in'                => get_string('operator_in', 'local_dynamicaudience'),
                'nin'               => get_string('operator_nin', 'local_dynamicaudience')
            ],
            'bool' => [
                'bool'                => get_string('operator_bool', 'local_dynamicaudience')
            ]
        ];
    }

    static function describe($rule) {
      global $DB;

      $form_data_type = rule::get_data_type();
      $form_fields = rule::get_fields();
      $form = [];


        $table = explode('.', $rule->field)[0];
        $field = explode('.', $rule->field)[1];
        $operator = get_string("operator_{$rule->operator}", 'local_dynamicaudience');
        $expected = '';
        switch ($rule->operator) {
            case 'eq':
            case 'neq':
            case 'like':
            case 'notlike':
            case 'regex': {
                $expected = "'{$rule->expected}'";
                break;
            }
            case 'date_gte':
            case 'date_lte': {
                $expected = userdate($rule->expected);
                break;
            }
            case 'duration_ago':
            case 'duration_after': {
                if ($rule->expected % 604800 == 0) {
                    $expected = $rule->expected / 604800 . " " . get_string('weeks');
                } else if ($rule->expected % 86400 == 0) {
                    $expected = $rule->expected / 86400 . " " . get_string('days');
                } else if ($rule->expected % 3600 == 0) {
                    $expected = $rule->expected / 3600 . " " . get_string('hours');
                } else if ($rule->expected % 60 == 0) {
                    $expected = $rule->expected / 60 . " " . get_string('minutes');
                } else {
                    $expected = $rule->expected . " " . get_string('seconds');
                }
                break;
            }
            case 'in':
            case 'nin': {
              switch ($rule->field) {
                case 'cohort.id':
                case 'course.id': {
                  $names = [];
                  if ($table == 'course') {
                    foreach ($DB->get_fieldset_select($table, 'fullname', "id in ({$rule->expected})") as $name) {
                      $names[] = "'{$name}'";
                    }
                  } else {
                    foreach ($DB->get_fieldset_select($table, 'name', "id in ({$rule->expected})") as $name) {
                      $names[] = "'{$name}'";
                    }
                  }


                  $expected = '(' . implode(', ', $names) . ') ' . "({$rule->expected})";
                  break;
                }
                case "course_completions.complete": {
                  $names = [];
                  foreach ($DB->get_fieldset_select('course', 'fullname', "id in ({$rule->expected})") as $name) {
                    $names[] = "'{$name}'";
                  }
                  $expected = '(' . implode(', ', $names) . ') ' . "({$rule->expected})";
                  break;
                }
                default: {
                  $expected = "({$rule->expected})";
                }
              }

              break;
            }
            case 'bool': {
                $expected = boolval($rule->expected) == true ? 'yes' : 'no';
            }
        }
        return "{$table}.{$field} {$operator} {$expected}";
    }

    static function append_clause_params($rule, &$joins, &$where, &$params) {
        global $DB;

        $tojoin = false;
        $rule->field = str_replace('user.', 'usr.', $rule->field);

        // Return early from special cases
        $parts = explode('.', $rule->field);
        $table = isset($parts[0]) ? $parts[0] : null;
        $field = isset($parts[1]) ? $parts[1] : null;
        $fieldid = isset($parts[2]) ? $parts[2] : null;
        switch ($table) {
            case 'course': {

                $e = "enrol_{$rule->id}";
                $ue = "user_enrolments_{$rule->id}";
                $now = time();
                if ($rule->operator == 'in') {
                    $joins[] = "{enrol} {$e} ON {$e}.courseid IN ($rule->expected)";
                    $joins[] = "{user_enrolments} {$ue} ON {$ue}.userid = usr.id AND {$ue}.enrolid = {$e}.id
                                AND {$ue}.timestart <= {$now} AND ({$ue}.timeend = 0 OR {$ue}.timeend > {$now})
                                AND {$ue}.status = 0";
                } else {
                    $where[] = "(SELECT count(id) FROM {enrol} {$e} ON {$e}.courseid IN ($rule->expected)
                                JOIN {user_enrolments} {$ue} ON {$ue}.userid = usr.id AND {$ue}.enrolid = {$e}.id
                                AND {$ue}.timestart <= {$now} AND ({$ue}.timeend = 0 OR {$ue}.timeend > {$now})
                                AND {$ue}.status = 0) = 0";
                }
                return;
            }
            case 'user_info_data': {
                $t = "user_info_data_{$rule->id}";
                $tojoin = "{user_info_data} {$t} ON {$t}.userid = usr.id AND {$t}.fieldid = {$fieldid}";
                break;
            }
            case 'cohort': {
                $t = "cohort_members_{$rule->id}";
                if ($rule->operator == 'in') {
                    $joins[] = "{cohort_members} {$t} ON {$t}.userid = usr.id AND {$t}.cohortid IN ({$rule->expected})";
                } else {
                    $where[] = "(SELECT count(id) FROM {cohort_members} WHERE userid = usr.id AND cohortid IN ({$rule->expected})) = 0";
                }
                break;
            }
            case 'course_completions': {
              $t = "course_completions_{$rule->id}";
              $timecompleted = '';
              if ($rule->operator == 'in') {
                $timecompleted = '> 0';
              } else {
                $timecompleted = '< 1';
              }
              $joins[] = "{course_completions} {$t} ON {$t}.userid = usr.id AND {$t}.course IN ({$rule->expected}) AND {$t}.timecompleted {$timecompleted}";
              break;
            }
        }


        $paramid = "rid_{$rule->id}";
        switch ($rule->operator) {
            case 'eq': {
              $fragment = "= '{$rule->expected}'";
              $items = explode(",", $rule->expected);
              if (count($items) > 1) {
                $fragment = "IN (";
                foreach ($items as $k=>$v) {
                  $items[$k] = "'" . trim($v) . "'";

                }
                $fragment .= implode(",", $items);
                $fragment .= ")";
              }
              //var_dump($fragment); die;
              if ($tojoin) {
                  $joins[] = "{$tojoin} AND {$t}.data {$fragment}";
              } else {
                  $where[] = "{$rule->field} {$fragment}";
              }
              break;
            }
            case 'neq': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND {$t}.data != '{$rule->expected}'";
                } else {
                    $where[] = "{$rule->field} != '{$rule->expected}'";
                }
                break;
            }
            case 'regex': {
              if ($tojoin) {
                // TODO: this is for MYSQL only!
                $joins[] = "{$tojoin} AND {$t}.data REGEXP '{$rule->expected}'";
              } else {
                $where[] = "{$rule->field} REGEXP '{$rule->expected}'";
              }
              break;
            }
            case 'like': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND " . "{$t}.data LIKE '%{$rule->expected}%'";
                } else {
                    $where[] = "{$rule->field} LIKE '%{$rule->expected}%'";
                }
                break;
            }
            case 'notlike': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND " . "{$t}.data NOT LIKE '%{$rule->expected}%'";
                } else {
                    $where[] = "{$rule->field} NOT LIKE '%{$rule->expected}%'";
                }
                break;
            }
            case 'end': {
                $position = $DB->sql_position("{$rule->expected}", $field);
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND {$position} > 0";
                } else {
                    $where[] = "{$position} > 0";
                }
                break;
            }
            case 'start': {
                $substr = $DB->sql_substr($rule->field, 0, strlen($rule->expected));
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND {$substr} = {$rule->expected}";
                } else {
                    $where[] = "{$substr} = {$rule->expected}";
                }
                break;
            }
            case 'date_gte': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND CAST({$t}.data AS integer) >= {$rule->expected}";
                } else {
                    $where[] = "{$rule->field} >= {$rule->expected}";
                }
                break;
            }
            case 'date_lte': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND CAST({$t}.data AS integer) <= {$rule->expected}";
                } else {
                    $where[] = "{$rule->field} <= {$rule->expected}";
                }
                break;
            }
            case 'duration_ago': {
                $time = time() - $rule->expected;
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND CAST({$t}.data AS integer) >= {$time}";
                } else {
                    $where[] = "{$rule->field} <= {$time}";
                }
                break;
            }
            case 'duration_after': {
                $time = time() + $rule->expected;
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND CAST({$t}.data AS integer) >= {$time}";
                } else {
                    $where[] = "{$rule->field} >= {$time}";
                }
                break;
            }
            case 'bool': {
                if ($tojoin) {
                    $joins[] = "{$tojoin} AND {$t}.data = {$rule->expected}";
                } else {
                    $where[] = "{$rule->field} = {$rule->expected}";
                }
                break;
            }
        }
    }

    static function table_sql($cohortid) {
        global $CFG;

        $table = new rules_table('local_dynamicaudience_rules-table');
        $table->downloadable = false;
        $table->set_sql("id, cohortid, field, operator, expected, '' as action", "{local_dynamicaudience_rules}", "cohortid = {$cohortid}");
        $table->define_baseurl("$CFG->wwwroot/local/dynamicaudience/rules.php", ['id'=>$cohortid]);
        $table->out(20, true);
    }
}

class rules_table extends table_sql {

    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns(['description', 'action']);
        $this->define_headers([get_string('description'), get_string('edit')]);
    }

    function col_description($record) {
        return rule::describe($record);
    }

    function col_action($record) {
      global $OUTPUT;
        // $delete_url = new moodle_url('/local/dynamicaudience/rules.php', ['id'=>$record->cohortid,'ruleid'=>$record->id,'action'=>'delete','sesskey'=>sesskey()]);
        // return html_writer::tag('a', html_writer::tag('i', '', ['class'=>'fa fa-trash']), ['href'=>$delete_url->out(false)]);

        $buttons = [];
        $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/rule.php', ['id'=>$record->id,'cohortid'=>$record->cohortid]),
          $OUTPUT->pix_icon('t/edit', get_string('edit')),
          array('title' => get_string('edit')));
        $buttons[] = html_writer::link(new moodle_url('/local/dynamicaudience/rule.php', ['cohortid'=>$record->cohortid,'id'=>$record->id,'action'=>'delete','sesskey'=>sesskey()]),
          $OUTPUT->pix_icon('t/delete', get_string('delete')),
          array('title' => get_string('delete')));
        return implode('', $buttons);
    }
}
