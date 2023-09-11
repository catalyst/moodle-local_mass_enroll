<?php
// This file is part of the Arup cost centre system
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
 * Upgrade code for local_mass_enroll
 *
 * @package     local_mass_enroll
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2021, Andrew Hancox
 */

defined('MOODLE_INTERNAL') || die();

function local_mass_enroll_clean_guest_enrolments() {
    global $DB;

    $guestuser = core_user::get_user_by_username('guest');

    $count = 0;

    foreach ($DB->get_records('user_enrolments', ['userid' => $guestuser->id]) as $enrolment) {
        $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);
        $plugin = enrol_get_plugin($instance->enrol);
        $plugin->unenrol_user($instance, $guestuser->id);

        $count += 1;
    }

    return $count;
}
