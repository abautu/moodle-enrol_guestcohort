<?php
/**
 * Guest Cohort enrolment plugin.
 *
 * @package    enrol_guestcohort
 * @copyright  2017 Andrei Bautu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Guest Cohort enrolment plugin implementation.
 * @author Andrei Bautu
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_guestcohort_plugin extends enrol_plugin {

    /**
     * Enrol a user using a given enrolment instance.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param null $roleid
     * @param int $timestart
     * @param int $timeend
     * @param null $status
     * @param null $recovergrades
     */
    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        // nothing to do, we never enrol here!
        return;
    }

    /**
     * Enrol a user from a given enrolment instance.
     *
     * @param stdClass $instance
     * @param int $userid
     */
    public function unenrol_user(stdClass $instance, $userid) {
        // nothing to do, we never enrol here!
        return;
    }

    /**
     * Attempt to automatically gain temporary guest access to course,
     * calling code has to make sure the plugin and instance are active.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means no guest access, integer means end of cached time
     */
    public function try_guestaccess(stdClass $instance) {
        global $USER, $CFG;
        require($CFG->dirroot.'/cohort/lib.php');

        if(!cohort_is_member($instance->customint1, $USER->id)) {
            return false;
        }

        // Temporarily assign them some guest role for this context
        $context = context_course::instance($instance->courseid);
        load_temp_course_role($context, $CFG->guestroleid);
        return ENROL_MAX_TIMESTAMP;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/guestcohort:config', $context);
    }

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
            $cohort = $DB->get_record('cohort', array('id'=>$instance->customint1));
            if (!$cohort) {
                return get_string('pluginname', 'enrol_'.$enrol);
            }
            $cohortname = format_string($cohort->name, true, array('context'=>context::instance_by_id($cohort->contextid)));
            return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ')';

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/guestcohort:config', $coursecontext)) {
            return false;
        }

        return cohort_get_available_cohorts($coursecontext, 0, 0, 1) ? true : false;
    }

    /**
     * Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param stdClass $course
     * @param stdClass $data form data
     * @return void
     */
    public function course_updated($inserted, $course, $data) {
        // It turns out there is no need for cohorts to deal with this hook, see MDL-34870.
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

        $step->set_mapping('enrol', $oldid, 0);
        if (!$step->get_task()->is_samesite()) {
            // No cohort restore from other sites.
            return;
        }

        if ($DB->record_exists('cohort', array('id'=>$data->customint1))) {
            $instance = $DB->get_record('enrol', array('customint1'=>$data->customint1, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $instanceid = $this->add_instance($course, (array)$data);
            }
        }
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/guestcohort:config', $context);
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Return an array of valid options for the cohorts.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_cohort_options($instance, $context) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');

        $cohorts = array();

        if ($instance->id) {
            if ($cohort = $DB->get_record('cohort', array('id' => $instance->customint1))) {
                $name = format_string($cohort->name, true, array('context' => context::instance_by_id($cohort->contextid)));
                $cohorts = array($instance->customint1 => $name);
            } else {
                $cohorts = array($instance->customint1 => get_string('error'));
            }
        } else {
            $cohorts = array('' => get_string('choosedots'));
            $allcohorts = cohort_get_available_cohorts($context, 0, 0, 0);
            foreach ($allcohorts as $c) {
                $cohorts[$c->id] = format_string($c->name);
            }
        }
        return $cohorts;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $coursecontext
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $coursecontext) {
        global $DB;

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_guestcohort'), $options);

        $options = $this->get_cohort_options($instance, $coursecontext);
        $mform->addElement('select', 'customint1', get_string('cohort', 'cohort'), $options);
        if ($instance->id) {
            $mform->setConstant('customint1', $instance->customint1);
            $mform->hardFreeze('customint1', $instance->customint1);
        } else {
            $mform->addRule('customint1', get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname" => value) of submitted data
     * @param array $files array of uploaded files "element_name" => tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name" => "error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        global $DB;
        $errors = array();

        $params = array(
            'customint1' => $data['customint1'],
            'courseid' => $data['courseid'],
            'id' => $data['id']
        );
        $sql = "customint1 = :customint1 AND courseid = :courseid AND enrol = 'guestcohort' AND id <> :id";
        if ($DB->record_exists_select('enrol', $sql, $params)) {
            $errors['courseid'] = get_string('instanceexists', 'enrol_guestcohort');
        }
        $validstatus = array_keys($this->get_status_options());
        $validcohorts = array_keys($this->get_cohort_options($instance, $context));
        $tovalidate = array(
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'customint1' => $validcohorts,
        );
        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }
}
