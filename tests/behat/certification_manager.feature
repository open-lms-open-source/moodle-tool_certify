@tool @tool_certify @openlms
Feature: Certification completion by managers tests

  Background:
    Given unnecessary Admin bookmarks block gets deleted
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
    And the following "courses" exist:
      | fullname | shortname | format | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | topics | CAT1     | 1                | 1                        |
      | Course 2 | C2        | topics | CAT2     | 1                | 1                        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
    And the following "roles" exist:
      | name            | shortname |
      | Certification viewer  | pviewer   |
      | Certification manager | pmanager  |
    And the following "permission overrides" exist:
      | capability                   | permission | role     | contextlevel | reference |
      | tool/certify:view            | Allow      | pviewer  | System       |           |
      | tool/certify:view            | Allow      | pmanager | System       |           |
      | tool/certify:edit            | Allow      | pmanager | System       |           |
      | tool/certify:assign          | Allow      | pmanager | System       |           |
      | tool/certify:delete          | Allow      | pmanager | System       |           |
      | tool/certify:admin           | Allow      | pmanager | System       |           |
      | enrol/programs:view          | Allow      | pmanager | System       |           |
      | enrol/programs:edit          | Allow      | pmanager | System       |           |
      | enrol/programs:admin         | Allow      | pmanager | System       |           |
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager1  | pmanager      | System       |           |
      | viewer1   | pviewer       | System       |           |
    And the following "enrol_programs > programs" exist:
      | fullname    | idnumber | category | sources |
      | Program 001 | PR1      |          | certify |
      | Program 002 | PR2      |          | certify |
    And the following "enrol_programs > program_items" exist:
      | program     | parent     | course   | fullname   | sequencetype     | minprerequisites |
      | Program 001 |            | Course 1 |            |                  |                  |
      | Program 001 |            | Course 2 |            |                  |                  |
    And the following "tool_certify > certifications" exist:
      | fullname          | idnumber | category | program1 | sources |
      | Certification 001 | CT1      |          | PR1      | manual  |
      | Certification 002 | CT2      |          | PR2      | manual  |

  @javascript
  Scenario: Manager may mark the whole certification as complete
    Given I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I click on "Users" "link" in the "#region-main" "css_element"
    And I press "Assign users"
    And I set the following fields to these values:
      | Users                    | Student 1 |
      | timewindowstart[month]   | 11        |
      | timewindowstart[year]    | 2022      |
      | timewindowstart[day]     | 5         |
      | timewindowstart[hour]    | 09        |
      | timewindowstart[minute]  | 00        |
    And I press dialog form button "Assign users"
    And I follow "Student 1"
    And I click on "5/11/22" "link" in the "Program 001" "table_row"

    When I press "Override period dates"
    And I set the following fields to these values:
      | timefrom[enabled]      | 1         |
      | timefrom[month]        | 11        |
      | timefrom[year]         | 2022      |
      | timefrom[day]          | 5         |
      | timefrom[hour]         | 09        |
      | timefrom[minute]       | 00        |
      | timecertified[enabled] | 1         |
      | timecertified[month]   | 11        |
      | timecertified[year]    | 2023      |
      | timecertified[day]     | 1         |
      | timecertified[hour]    | 09        |
      | timecertified[minute]  | 00        |
    And I press dialog form button "Override period dates"
    Then I should see "Valid" in the "Certification status:" definition list item

  @javascript
  Scenario: Manager may mark certification program as completed
    Given I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I click on "Users" "link" in the "#region-main" "css_element"
    And I press "Assign users"
    And I set the following fields to these values:
      | Users                    | Student 1 |
      | timewindowstart[month]   | 11        |
      | timewindowstart[year]    | 2022      |
      | timewindowstart[day]     | 5         |
      | timewindowstart[hour]    | 09        |
      | timewindowstart[minute]  | 00        |
    And I press dialog form button "Assign users"
    And I follow "Student 1"
    And I click on "5/11/22" "link" in the "Program 001" "table_row"
    And I follow "Program 001"

    When I click on "Edit" "link" in the "Program 001" "table_row"
    And I set the following fields to these values:
      | timecompleted[enabled] | 1    |
    And I press dialog form button "Update"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I click on "Users" "link" in the "#region-main" "css_element"
    And I follow "Student 1"
    Then I should see "Valid" in the "Certification status:" definition list item
