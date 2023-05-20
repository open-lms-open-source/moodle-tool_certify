@tool @tool_certify @openlms
Feature: General certification management tests

  Background:
    Given unnecessary Admin bookmarks block gets deleted
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | 0        | CAT3     |
      | Cat 4 | CAT3     | CAT4     |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
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
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager1  | pmanager      | System       |           |
      | manager2  | pmanager      | Category     | CAT2      |
      | manager2  | pmanager      | Category     | CAT3      |
      | viewer1   | pviewer       | System       |           |

  @javascript
  Scenario: Manager may create a new certification with required settings
    Given I log in as "manager1"
    And I am on all certifications management page

    When I press "Add certification"
    And the following fields match these values:
      | Certification name |             |
      | ID number          |             |
      | Description        |             |
    And I set the following fields to these values:
      | Certification name | Certification 001 |
      | ID number          | CT01              |
    And I press dialog form button "Add certification"
    Then I should see "Certification 001" in the "Full name:" definition list item
    And I should see "CT01" in the "ID number:" definition list item
    And I should see "System" in the "Category:" definition list item
    And I should see "No" in the "Archived:" definition list item
    And I am on all certifications management page
    And "Certification 001" row "Category" column of "management_certifications" table should contain "System"
    And "Certification 001" row "ID number" column of "management_certifications" table should contain "CT01"
    And "Certification 001" row "Public" column of "management_certifications" table should contain "No"

  @javascript @_file_upload
  Scenario: Manager may create a new certifications with all settings
    Given I log in as "manager1"
    And I am on all certifications management page

    When I press "Add certification"
    And the following fields match these values:
      | Certification name |             |
      | ID number          |             |
      | Description        |             |
    And I set the following fields to these values:
      | Certification name | Certification 001 |
      | ID number          | CT01        |
      | Description        | Nice desc   |
    And I upload "admin/tool/certify/tests/fixtures/badge.png" file to "Certification image" filemanager
    And I set the field "Context" to "Cat 2"
    And I set the field "Tags" to "Mathematics, Algebra"
    And I press dialog form button "Add certification"
    Then I should see "Certification 001" in the "Full name:" definition list item
    And I should see "CT01" in the "ID number:" definition list item
    And I should see "Cat 2" in the "Category:" definition list item
    And I should see "No" in the "Archived:" definition list item
    And I should see "Mathematics" in the "Tags:" definition list item
    And I should see "Algebra" in the "Tags:" definition list item
    And I am on certifications management page in "Cat 2"
    And "CT01" row "Certification name" column of "management_certifications" table should contain "Certification 001"
    And "CT01" row "Certification name" column of "management_certifications" table should contain "Mathematics"
    And "CT01" row "Certification name" column of "management_certifications" table should contain "Algebra"
    And "CT01" row "Description" column of "management_certifications" table should contain "Nice desc"
    And "CT01" row "Public" column of "management_certifications" table should contain "No"
    And "CT01" row "Assignments" column of "management_certifications" table should contain "0"

  @javascript
  Scenario: Manager may update basic general settings of an existing certification
    Given I log in as "manager1"
    And I am on all certifications management page
    And I press "Add certification"
    And I set the following fields to these values:
      | Certification name | Certification 001 |
      | ID number          | CT01              |
    And I press dialog form button "Add certification"

    When I press "Edit"
    And I set the following fields to these values:
      | Certification name | Certification 002 |
      | ID number          | CT02              |
    And I press dialog form button "Update certification"
    Then I should see "Certification 002" in the "Full name:" definition list item
    And I should see "CT02" in the "ID number:" definition list item
    And I should see "System" in the "Category:" definition list item
    And I should see "No" in the "Archived:" definition list item

  @javascript @_file_upload
  Scenario: Manager may update all general settings of an existing certification
    Given I log in as "manager1"
    And I am on all certifications management page
    And I press "Add certification"
    And I set the following fields to these values:
      | Certification name | Certification 002 |
      | ID number          | CT02              |
    And I set the field "Context" to "Cat 1"
    And I set the field "Tags" to "Logic"
    And I press dialog form button "Add certification"

    When I press "Edit"
    And I set the following fields to these values:
      | Certification name | Certification 001 |
      | ID number          | CT01              |
      | Description        | Nice desc         |
    And I upload "admin/tool/certify/tests/fixtures/badge.png" file to "Certification image" filemanager
    And I set the field "Context" to "Cat 2"
    And I set the field "Tags" to "Mathematics, Algebra"
    And I press dialog form button "Update certification"
    Then I should see "Certification 001" in the "Full name:" definition list item
    And I should see "CT01" in the "ID number:" definition list item
    And I should see "Cat 2" in the "Category:" definition list item
    And I should see "No" in the "Archived:" definition list item
    And I should see "Mathematics" in the "Tags:" definition list item
    And I should see "Algebra" in the "Tags:" definition list item
