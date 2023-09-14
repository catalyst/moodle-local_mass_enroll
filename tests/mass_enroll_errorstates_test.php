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
 * Mass enrol unit tests checking error states
 *
 * @package   local_mass_enroll
 * @copyright 2023, Andrew Hancox
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mass_enroll_errorstates_test extends advanced_testcase {

    public function errorstates_provider(): array {
        return [
            'withblank' => [
                'file' => 'errorstate_users_by_idnumber_withblank.csv',
                'messagecontains' => 'First column cannot be blank'
            ],
            'withguest' => [
                'file' => 'errorstate_users_by_idnumber_withguest.csv',
                'messagecontains' => 'Cannot enrol the guest user'
            ],
            'deleted' => [
                'file' => 'errorstate_users_by_idnumber_deleted.csv',
                'messagecontains' => 'Cannot enrol deleted user'
            ]
        ];
    }

    /**
     * Tests mass_enroll function
     * @param string $fixturefile Name of .csv in test fixtures directory to use
     * @param string $messagecontains Warning message that should appear
     * @dataProvider errorstates_provider
     * @covers       \local_mass_enroll\mass_enroll
     */
    public function test_mass_enroll_blankidnumber(string $fixturefile, string $messagecontains) {
        global $DB, $CFG;
        require_once(__DIR__ . '/../lib.php');
        require_once($CFG->libdir . '/csvlib.class.php');

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $guestuser = guest_user();

        $users = [];

        for ($i = 1; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['idnumber' => $i]);
        }
        $this->getDataGenerator()->create_user(['idnumber' => 'deleted']);

        $DB->set_field('user', 'idnumber', 'guest', ['id' => $guestuser->id]);
        $DB->set_field('user', 'deleted', true, ['idnumber' => 'deleted']);

        // Import test data.
        $content = file_get_contents(__DIR__ . '/fixtures/' . $fixturefile);
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
        $this->assertStringContainsString($messagecontains, $output);

        // Test the data was corrected created.

        // 1. All users should have exactly 1 enrolment into the course (i.e. no duplicates)
        $courseenrol = current(enrol_get_instances($course->id, true));

        for ($i = 1; $i < 4; $i++) {
            $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $users[$i]->id, 'enrolid' => $courseenrol->id]));
        }
    }
}
