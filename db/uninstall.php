<?php
/**
 * Meta link enrolment plugin uninstallation.
 *
 * @package    enrol_guestcohort
 * @copyright  2017 Andrei Bautu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_guestcohort_uninstall() {
    global $CFG, $DB;

    $cohort = enrol_get_plugin('guestcohort');
    $rs = $DB->get_recordset('enrol', array('enrol'=>'guestcohort'));
    foreach ($rs as $instance) {
        $cohort->delete_instance($instance);
    }
    $rs->close();

    return true;
}
