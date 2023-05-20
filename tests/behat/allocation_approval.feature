@tool @tool_certify @openlms
Feature: Certification approval assignments tests

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
      | username  | firstname | lastname  | email                 |
      | manager1  | Manager   | 1         | manager1@example.com  |
      | manager2  | Manager   | 2         | manager2@example.com  |
      | viewer1   | Viewer    | 1         | viewer1@example.com   |
      | student1  | Student   | 1         | student1@example.com  |
      | student2  | Student   | 2         | student2@example.com  |
      | student3  | Student   | 3         | student3@example.com  |
      | student4  | Student   | 4         | student4@example.com  |
      | student5  | Student   | 5         | student5@example.com  |
      | allocator | Certification   | Allocator | allocator@example.com |
    And the following "cohort members" exist:
      | user     | cohort |
      | student1 | CH1    |
      | student2 | CH1    |
      | student3 | CH1    |
      | student2 | CH2    |
      | student4 | CH2    |
    And the following "roles" exist:
      | name              | shortname |
      | Certification viewer    | pviewer   |
      | Certification manager   | pmanager  |
      | Certification allocator | allocator |
    And the following "permission overrides" exist:
      | capability                   | permission | role      | contextlevel | reference |
      | tool/certify:view            | Allow      | pviewer   | System       |           |
      | tool/certify:view            | Allow      | pmanager  | System       |           |
      | tool/certify:edit            | Allow      | pmanager  | System       |           |
      | tool/certify:delete          | Allow      | pmanager  | System       |           |
      | tool/certify:assign        | Allow      | pmanager  | System       |           |
      | moodle/cohort:view           | Allow      | pmanager  | System       |           |
      | tool/certify:view            | Allow      | allocator | System       |           |
      | tool/certify:assign        | Allow      | allocator | System       |           |
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager1  | pmanager      | System       |           |
      | manager2  | pmanager      | Category     | CAT2      |
      | manager2  | pmanager      | Category     | CAT3      |
      | viewer1   | pviewer       | System       |           |
      | allocator | allocator     | Category     | CAT1      |
    And the following "enrol_programs > programs" exist:
      | fullname    | idnumber | category | sources |
      | Program 000 | PR0      |          | certify |
      | Program 001 | PR1      | Cat 1    | certify |
      | Program 002 | PR2      | Cat 2    | certify |
      | Program 003 | PR3      | Cat 3    | certify |
    And the following "tool_certify > certifications" exist:
      | fullname          | idnumber | category | program1 | cohorts  | public |
      | Certification 000 | CT0      |          | PR0      | Cohort 2 | 0      |
      | Certification 001 | CT1      | Cat 1    | PR1      |          | 1      |
      | Certification 002 | CT2      | Cat 2    | PR2      |          | 0      |
      | Certification 003 | CT3      | Cat 3    | PR3      |          | 0      |

  @javascript
  Scenario: Allocator approves student assignment request for a certification
    When I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I follow "Assignment settings"
    And I click on "Update Requests with approval" "link"
    And I set the following fields to these values:
      | Active             | Yes |
      | Allow new requests | No  |
    And I press dialog form button "Update"
    Then I should see "Active; Requests are not allowed" in the "Requests with approval:" definition list item
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    And I follow "Certification 001"
    And I should not see "Request access"
    And I log out

    When I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I follow "Assignment settings"
    And I click on "Update Requests with approval" "link"
    And I set the following fields to these values:
      | Allow new requests | Yes |
    And I press dialog form button "Update"
    Then I should see "Active; Requests are allowed" in the "Requests with approval:" definition list item
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    And I follow "Certification 001"
    And I press "Request access"
    And I press dialog form button "Cancel"
    And I press "Request access"
    And I press dialog form button "Request access"
    Then I should see "Access request pending"
    And I log out

    When I log in as "allocator"
    And I am on certifications management page in "Cat 1"
    And I follow "Certification 001"
    And I click on "Requests" "link" in the "#region-main" "css_element"
    And I click on "Approve request" "link" in the "Student 2" "table_row"
    And I press dialog form button "Approve request"
    Then I should not see "Student 2"
    And I follow "Users"
    And "Student 2" row "Source" column of "certification_assignments" table should contain "Requests with approval"
    And I log out

    When I log in as "student2"
    And I am on My certifications page
    And "Certification 001" row "Certification status" column of "my_certifications" table should contain "Not certified"
    And I log out

    When I log in as "allocator"
    And I am on certifications management page in "Cat 1"
    And I follow "Certification 001"
    And I follow "Users"
    And I click on "Delete assignment" "link" in the "Student 2" "table_row"
    And I press dialog form button "Delete assignment"
    Then I should not see "Student 2"
    And I log out

    When I log in as "student2"
    And I am on My certifications page
    And I should not see "Certification 001"
    And I log out

  @javascript
  Scenario: Allocator rejects student assignment request for a certification
    Given I log in as "manager1"
    And I am on all certifications management page
    And I follow "Certification 001"
    And I follow "Assignment settings"

    When I click on "Update Requests with approval" "link"
    And I set the following fields to these values:
      | Active | Yes |
    And I press dialog form button "Update"
    Then I should see "Active" in the "Requests with approval:" definition list item
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    And I follow "Certification 001"
    And I press "Request access"
    And I press dialog form button "Request access"
    Then I should see "Access request pending"
    And I log out

    When I log in as "allocator"
    And I am on certifications management page in "Cat 1"
    And I follow "Certification 001"
    And I click on "Requests" "link" in the "#region-main" "css_element"
    And I click on "Reject request" "link" in the "Student 2" "table_row"
    And I set the following fields to these values:
      | Rejection reason | Sorry mate! |
    And I press dialog form button "Reject request"
    Then I should see "Student 2"
    And I follow "Users"
    And I should not see "Student 2"
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    And I follow "Certification 001"
    Then I should see "Access request was rejected"
    And I log out

    When I log in as "allocator"
    And I am on certifications management page in "Cat 1"
    And I follow "Certification 001"
    And I click on "Requests" "link" in the "#region-main" "css_element"
    And I click on "Delete request" "link" in the "Student 2" "table_row"
    And I press dialog form button "Delete request"
    Then I should not see "Student 2"
    And I log out

    When I log in as "student2"
    And I am on Certification catalogue page
    And I follow "Certification 001"
    And I press "Request access"
    And I press dialog form button "Request access"
    Then I should see "Access request pending"
    And I log out
