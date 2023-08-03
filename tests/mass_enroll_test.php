<?php
// This file is part of Moodle - http://moodle.org/
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
 * Mass enrol unit tests
 *
 * @package   local_mass_enroll
 * @copyright 2023 Catalyst IT Australia
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mass_enroll_test extends advanced_testcase {

    /**
     * Provides to test_mass_enroll
     */
    public function mass_enroll_provider(): array {
        return [
            'by email' => [
                'file' => 'users_by_email.csv',
                'method' => 'email'
            ],
            'by username' => [
                'file' => 'users_by_username.csv',
                'method' => 'username'
            ],
            'by idnumber' => [
                'file' => 'users_by_idnumber.csv',
                'method' => 'idnumber'
            ]
        ];
    }

    /**
     * Tests mass_enroll function
     * @param string $fixturefile Name of .csv in test fixtures directory to use
     * @param string $method Identifier method (email, username or idnumber)
     * @dataProvider mass_enroll_provider
     * @covers \local_mass_enroll\mass_enroll
     */
    public function test_mass_enroll(string $fixturefile, string $method) {
        global $DB, $CFG;
        require_once(__DIR__.'/../lib.php');
        require_once($CFG->libdir . '/csvlib.class.php');

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        // Setup the test users.
        // Note the mnethostid is set explicitly so that the [mnhethostid, username] DB index is unique.
        $user1 = $this->getDataGenerator()->create_user(['mnethostid' => 1]);
        $user2 = $this->getDataGenerator()->create_user(['mnethostid' => 2]);
        $user3 = $this->getDataGenerator()->create_user(['mnethostid' => 3]);
        $user3duplicate = $this->getDataGenerator()->create_user(['mnethostid' => 4]);

        // Set their properties correctly based on the test method.
        // Note this done directly via the DB, since some of them
        // are not strictly valid (e.g. duplicate accounts with same username).
        $updateparams = function($userid, $i) use ($method): array {
            $data = [
                'email' => "user{$i}@test.com",
                'username' => "user{$i}",
                'idnumber' => $i
            ];

            return [
                'id' => $userid,
                $method => $data[$method]
            ];
        };

        $DB->update_record('user', $updateparams($user1->id, 1));
        $DB->update_record('user', $updateparams($user2->id, 2));
        $DB->update_record('user', $updateparams($user3->id, 3));
        $DB->update_record('user', $updateparams($user3duplicate->id, 3));

        // Import test data.
        $content = file_get_contents(__DIR__ . '/fixtures/' . $fixturefile);
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');
        $cir->load_csv_content($content, 'UTF-8', 'comma');

        // Fake the moodle form data.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $mformdata = (object) [
            'roleassign' => $studentrole->id,
            'firstcolumn' => $method,
            'creategroups' => true,
            'creategroupings' => true,
            'mailreport' => false
        ];

        // Run the import.
        mass_enroll($cir, $course, context_course::instance($course->id), $mformdata);

        // Test the data was corrected created.

        // 1. All users should have exactly 1 enrolment into the course (i.e. no duplicates)
        $courseenrol = current(enrol_get_instances($course->id, true));
        $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $user1->id, 'enrolid' => $courseenrol->id]));
        $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $user2->id, 'enrolid' => $courseenrol->id]));
        $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $user3->id, 'enrolid' => $courseenrol->id]));
        $this->assertCount(1, $DB->get_records('user_enrolments', ['userid' => $user3duplicate->id,
            'enrolid' => $courseenrol->id]));

        // 2. Two groups should have been auto created in the course.
        $group1 = groups_get_group_by_name($course->id, 'group1');
        $group2 = groups_get_group_by_name($course->id, 'group2');

        $this->assertNotEmpty($group1);
        $this->assertNotEmpty($group2);

        // 3. Ensure the right number of people got added to each group.
        // Note with group 1, three users appear in the CSV however the third user has a duplicate account,
        // so actual count will be 4.
        $this->assertCount(4, groups_get_members($group1));
        $this->assertCount(1, groups_get_members($group2));

        // 4. Ensure user 2 got added to both groups.
        $this->assertCount(2, $DB->get_records('groups_members', ['userid' => $user2->id]));
    }
}
