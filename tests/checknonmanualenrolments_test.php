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

namespace local_mass_enroll;

use advanced_testcase;
use context_course;
use csv_import_reader;

/**
 * Mass enrol unit tests checking checknonmanualenrolments option is respected
 *
 * @package   local_mass_enroll
 * @copyright 2023, Andrew Hancox
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checknonmanualenrolments_test extends advanced_testcase {

    /**
     * Provides to test_mass_enroll
     */
    public function checknonmanualenrolments_provider(): array {
        return [
            'true' => [
                'checknonmanualenrolments' => true,
            ],
            'false' => [
                'checknonmanualenrolments' => false,
            ]
        ];
    }

    /**
     * Tests mass_enroll function
     * @param bool $checknonmanualenrolments checknonmanualenrolments config setting
     * @dataProvider checknonmanualenrolments_provider
     * @covers       \local_mass_enroll\mass_enroll
     */
    public function test_checknonmanualenrolments($checknonmanualenrolments) {
        global $DB, $CFG;
        require_once(__DIR__ . '/../lib.php');
        require_once($CFG->libdir . '/csvlib.class.php');

        $this->resetAfterTest();

        set_config('checknonmanualenrolments', $checknonmanualenrolments, 'local_mass_enroll');

        $selfplugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $alreadyenrolleduser = $this->getDataGenerator()->create_user(['idnumber' => 'alreadyenrolleduser']);

        $course = $this->getDataGenerator()->create_course();
        $instance1 = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'self'), '*', MUST_EXIST);
        $selfplugin->enrol_user($instance1, $alreadyenrolleduser->id, $studentrole->id);

        $users = [];

        for ($i = 1; $i < 4; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['idnumber' => $i]);
        }

        // Import test data.
        $content = file_get_contents(__DIR__ . '/fixtures/checknonmanualenrolments.csv');
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');
        $cir->load_csv_content($content, 'UTF-8', 'comma');

        // Fake the moodle form data.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $mformdata = (object)[
            'roleassign' => $studentrole->id,
            'firstcolumn' => 'idnumber',
            'creategroups' => true,
            'creategroupings' => true,
            'mailreport' => false,
            'purgegroupsbeforecreating' => false
        ];

        // Run the import.
        $output = mass_enroll($cir, $course, context_course::instance($course->id), $mformdata);

        // Test the data was corrected created.

        // 1. All users should have exactly 1 enrolment into the course (i.e. no duplicates)
        $courseenrol = current(enrol_get_instances($course->id, true));

        for ($i = 1; $i < 4; $i++) {
            $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $users[$i]->id, 'enrolid' => $courseenrol->id]));
        }

        // 2. This user should have one or two enrolments based on the parameters the test runs with
        $this->assertCount($checknonmanualenrolments ? 1 : 2, $DB->get_records('user_enrolments', ['userid' => $alreadyenrolleduser->id]));
    }
}
