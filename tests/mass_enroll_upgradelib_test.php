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
 * @copyright 2023, Andrew Hancox
 */

namespace local_mass_enroll;

use advanced_testcase;
use context_course;
use csv_import_reader;

/**
 * Mass enrol unit tests
 *
 * @package   local_mass_enroll
 * @copyright 2023, Andrew Hancox
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mass_enroll_upgradelib_test extends advanced_testcase {
    public function test_local_mass_enroll_clean_guest_enrolments() {
        global $CFG, $DB;
        $this->resetAfterTest();

        require_once("$CFG->dirroot/local/mass_enroll/db/upgradelib.php");

        $guestuser = \core_user::get_user_by_username('guest');

        for ($i = 0; $i<3;$i++) {
            $course = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user($guestuser->id, $course->id, 'student');
        }

        $this->assertEquals(3, $DB->record_exists('user_enrolments', array('userid'=>$guestuser->id)));

        local_mass_enroll_clean_guest_enrolments();

        $this->assertEquals(0, $DB->record_exists('user_enrolments', array('userid'=>$guestuser->id)));

    }
}
