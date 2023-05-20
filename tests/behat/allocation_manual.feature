@tool @tool_certify @openlms
Feature: Manual certification assignment tests

  Background:
    Given unnecessary Admin bookmarks block gets deleted
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | 0        | CAT3     |
      | Cat 4 | CAT3     | CAT4     |
    And the following "cohorts" exist:
      | name     | idnumber |
      | Cohort 1 | CH1      |
      | Cohort 2 | CH2      |
      | Cohort 3 | CH3      |
    And the following "enrol_programs > programs" exist:
      | fullname    | idnumber | category | public | sources  |
      | Program 000 | PR0      |          | 0      | certify  |
      | Program 001 | PR1      | Cat 1    | 0      | certify  |
      | Program 002 | PR2      | Cat 2    | 0      | certify  |
      | Program 003 | PR3      | Cat 3    | 0      | certify  |
    And the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | manager  | Site      | Manager  | manager@example.com  | m        |
      | manager1 | Manager   | 1        | manager1@example.com | m1       |
      | manager2 | Manager   | 2        | manager2@example.com | m2       |
      | viewer1  | Viewer    | 1        | viewer1@example.com  | v1       |
      | student1 | Student   | 1        | student1@example.com | s1       |
      | student2 | Student   | 2        | student2@example.com | s2       |
      | student3 | Student   | 3        | student3@example.com | s3       |
      | student4 | Student   | 4        | student4@example.com | s4       |
      | student5 | Student   | 5        | student5@example.com | s5       |
    And the following "cohort members" exist:
      | user     | cohort |
      | student1 | CH1    |
      | student2 | CH1    |
      | student3 | CH1    |
      | student2 | CH2    |
    And the following "roles" exist:
      | name            | shortname |
      | certification viewer  | pviewer   |
      | certification manager | pmanager  |
    And the following "permission overrides" exist:
      | capability                   | permission | role     | contextlevel | reference |
      | tool/certify:view            | Allow      | pviewer  | System       |           |
      | tool/certify:view            | Allow      | pmanager | System       |           |
      | tool/certify:edit            | Allow      | pmanager | System       |           |
      | tool/certify:delete          | Allow      | pmanager | System       |           |
      | tool/certify:assign          | Allow      | pmanager | System       |           |
      | moodle/cohort:view           | Allow      | pmanager | System       |           |
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager   | manager       | System       |           |
      | manager1  | pmanager      | System       |           |
      | manager2  | pmanager      | Category     | CAT2      |
      | manager2  | pmanager      | Category     | CAT3      |
      | viewer1   | pviewer       | System       |           |
    And the following "tool_certify > certifications" exist:
      | fullname          | idnumber | category | program1 |
      | Certification 000 | CT0      |          | PR0      |
      | Certification 001 | CT1      | Cat 1    | PR1      |
      | Certification 002 | CT2      | Cat 2    | PR2      |
      | Certification 003 | CT3      | Cat 3    | PR3      |

  @javascript
  Scenario: Manager may assign users manually to certification
    Given I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 000"
    And I click on "Assignment settings" "link" in the "#region-main" "css_element"
    And I click on "Update Manual assignment" "link"
    And I set the following fields to these values:
      | Active | Yes |
    And I press dialog form button "Update"
    And I should see "Active" in the "Manual assignment:" definition list item
    And I click on "Users" "link" in the "#region-main" "css_element"

    When I press "Assign users"
    And I set the following fields to these values:
      | Users | Student 1, Student 5 |
    And I press dialog form button "Assign users"
    Then "Student 1" row "Source" column of "certification_assignments" table should contain "Manual assignment"
    And "Student 5" row "Source" column of "certification_assignments" table should contain "Manual assignment"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should not see "Student 4"

    When I press "Assign users"
    And I set the following fields to these values:
      | Cohort | Cohort 2 |
    And I press dialog form button "Assign users"
    Then "Student 1" row "Source" column of "certification_assignments" table should contain "Manual assignment"
    And "Student 2" row "Source" column of "certification_assignments" table should contain "Manual assignment"
    And "Student 5" row "Source" column of "certification_assignments" table should contain "Manual assignment"
    And I should not see "Student 3"
    And I should not see "Student 4"

    When I click on "Delete assignment" "link" in the "Student 2" "table_row"
    And I press dialog form button "Cancel"
    Then "Student 2" row "Source" column of "certification_assignments" table should contain "Manual assignment"

    When I click on "Delete assignment" "link" in the "Student 2" "table_row"
    And I press dialog form button "Delete assignment"
    Then I should not see "Student 2"

  @javascript @tool_olms_tenant
  Scenario: Tenant manager may assign users manually to certification
    Given tenant support was activated
    And the following "tool_olms_tenant > tenants" exist:
      | name     | idnumber | category |
      | Tenant 1 | TEN1     | CAT1     |
      | Tenant 2 | TEN2     | CAT2     |
    And the following "users" exist:
      | username | firstname | lastname | email                | tenant   |
      | tu1      | Tenant 1  | Student  | tu1@example.com      | TEN1     |
      | tu2      | Tenant 2  | Student  | tu2@example.com      | TEN2     |
    And I log in as "manager"

    And I am on all certifications management page
    And I follow "Certification 000"
    And I click on "Assignment settings" "link" in the "#region-main" "css_element"
    And I click on "Update Manual assignment" "link"
    And I set the following fields to these values:
      | Active | Yes |
    And I press dialog form button "Update"
    And I should see "Active" in the "Manual assignment:" definition list item
    And I click on "Users" "link" in the "#region-main" "css_element"
    When I press "Assign users"
    And I set the following fields to these values:
      | Users | Student 1 |
    And I press dialog form button "Assign users"
    Then "Student 1" row "Source" column of "certification_assignments" table should contain "Manual assignment"

    And I am on all certifications management page
    And I follow "Certification 001"
    And I click on "Assignment settings" "link" in the "#region-main" "css_element"
    And I click on "Update Manual assignment" "link"
    And I set the following fields to these values:
      | Active | Yes |
    And I press dialog form button "Update"
    And I should see "Active" in the "Manual assignment:" definition list item
    And I click on "Users" "link" in the "#region-main" "css_element"

    When I press "Assign users"
    And I set the following fields to these values:
      | Users | Student 1 |
    And I press dialog form button "Assign users"
    And "Student 1" row "Source" column of "certification_assignments" table should contain "Manual assignment"

    And I click on "Select a tenant" "link"
    And I set the following fields to these values:
      | Tenant      | Tenant 1         |
    And I press dialog form button "Switch"

    And I am on all certifications management page
    And I follow "Certification 000"
    And I click on "Users" "link" in the "#region-main" "css_element"

    When I press "Assign users"
    And I set the following fields to these values:
      | Users | Tenant 1 Student |
    And I press dialog form button "Assign users"
    Then "Tenant 1 Student" row "Source" column of "certification_assignments" table should contain "Manual assignment"

    And I am on all certifications management page
    And I follow "Certification 001"
    And I click on "Users" "link" in the "#region-main" "css_element"

    When I press "Assign users"
    And I set the following fields to these values:
      | Users | Tenant 1 Student |
    And I press dialog form button "Assign users"
    Then "Tenant 1 Student" row "Source" column of "certification_assignments" table should contain "Manual assignment"
