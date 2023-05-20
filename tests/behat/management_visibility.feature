@tool @tool_certify @openlms
Feature: Certification visibility management tests

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
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
      | student5 | Student   | 5        | student5@example.com |
    And the following "cohort members" exist:
      | user     | cohort |
      | student1 | CH1    |
      | student2 | CH1    |
      | student3 | CH1    |
      | student2 | CH2    |
    And the following "roles" exist:
      | name                  | shortname |
      | Certification viewer  | pviewer   |
      | Certification manager | pmanager  |
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
      | manager1  | pmanager      | System       |           |
      | manager2  | pmanager      | Category     | CAT2      |
      | manager2  | pmanager      | Category     | CAT3      |
      | viewer1   | pviewer       | System       |           |

  @javascript
  Scenario: Manager may update certification visibility settings
    Given the following "tool_certify > certifications" exist:
      | fullname          | idnumber | category |
      | Certification 000 | CT0      |          |
      | Certification 001 | CT1      | Cat 1    |
      | Certification 002 | CT2      | Cat 2    |
      | Certification 003 | CT3      | Cat 3    |
    And I log in as "manager1"
    And I am on all certifications management page
    And "Certification 000" row "Public" column of "management_certifications" table should contain "No"
    And "Certification 001" row "Public" column of "management_certifications" table should contain "No"
    And "Certification 002" row "Public" column of "management_certifications" table should contain "No"
    And "Certification 003" row "Public" column of "management_certifications" table should contain "No"

    When I follow "Certification 000"
    And I click on "Visibility settings" "link" in the "#region-main" "css_element"
    And I press "Edit"
    And the following fields match these values:
      | Public             | No             |
      | Visible to cohorts |                |
    And I set the following fields to these values:
      | Public             | Yes            |
    And I press dialog form button "Update certification"
    Then I press "Edit"
    And the following fields match these values:
      | Public             | Yes            |
    And I press dialog form button "Cancel"
    And I am on all certifications management page
    And "Certification 000" row "Public" column of "management_certifications" table should contain "Yes"

    When I click on "No" "link" in the "Certification 001" "table_row"
    And I press "Edit"
    And I set the following fields to these values:
      | Visible to cohorts | Cohort 1 |
    And I press dialog form button "Update certification"
    Then I should see "Cohort 1"
    And I press "Edit"
    And I set the following fields to these values:
      | Visible to cohorts | Cohort 2 |
    And I press dialog form button "Update certification"
    And I should see "Cohort 2"
    And I am on all certifications management page
    And "Certification 001" row "Public" column of "management_certifications" table should contain "No"

    When I follow "Certification 002"
    And I click on "Visibility settings" "link" in the "#region-main" "css_element"
    And I press "Edit"
    And I set the following fields to these values:
      | Visible to cohorts | Cohort 2, Cohort 1 |
    And I press dialog form button "Update certification"
    Then I should see "Cohort 1"
    And I should see "Cohort 2"

    When I am on Certification catalogue page
    Then I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"
    And I log out

    When I log in as "student1"
    And I am on Certification catalogue page
    Then I should see "Certification 000"
    And I should not see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    Then I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I log out

    When I log in as "student3"
    And I am on Certification catalogue page
    Then I should see "Certification 000"
    And I should not see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I log out

    When I log in as "student4"
    And I am on Certification catalogue page
    Then I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"
    And I log out
