@tool @tool_certify @openlms
Feature: Certifications navigation behat steps test

  Background:
    Given unnecessary Admin bookmarks block gets deleted
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | 0        | CAT3     |
      | Cat 4 | CAT3     | CAT4     |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
      | Course 2 | C2        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
      | viewer2  | Viewer    | 2        | viewer2@example.com  |
      | student1 | Student   | 1        | student1@example.com |
    And the following "roles" exist:
      | name           | shortname |
      | Certification viewer | pviewer   |
    And the following "permission overrides" exist:
      | capability                   | permission | role    | contextlevel | reference |
      | tool/certify:view            | Allow      | pviewer | System       |           |
    And the following "role assigns" exist:
      | user     | role          | contextlevel | reference |
      | manager1 | manager       | System       |           |
      | manager2 | manager       | Category     | CAT1      |
      | viewer1  | pviewer       | System       |           |
      | viewer2  | pviewer       | Category     | CAT1      |
    And the following "tool_certify > certifications" exist:
      | fullname    | idnumber | category | public | archived |
      | Certification 000 | PR0      |          | 0      | 0        |
      | Certification 001 | PR1      | Cat 1    | 1      | 0        |
      | Certification 002 | PR2      | Cat 2    | 0      | 0        |
      | Certification 003 | PR3      |          | 1      | 1        |

  Scenario: Admin navigates to certifications via behat step
    Given I log in as "admin"

    When I am on all certifications management page
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "system"
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "Cat 1"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Admin navigates to certifications the normal way
    Given I log in as "admin"

    When I navigate to "Certifications > Certification management" in site administration
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

    When I select "System (2)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I select "Cat 1 (1)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I select "All certifications (4)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

  Scenario: Full manager navigates to certifications via behat step
    Given I log in as "manager1"

    When I am on all certifications management page
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "system"
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "Cat 1"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Full manager navigates to certifications the normal way
    Given I log in as "admin"

    When I navigate to "Certifications > Certification management" in site administration
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I should not see "Certification 003"

    When I select "System (2)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"
    And I should not see "Certification 003"

    When I select "Cat 1 (1)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"
    And I should not see "Certification 003"

    When I select "All certifications (4)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I should not see "Certification 003"

  Scenario: Category manager navigates to certifications via behat step
    Given I log in as "manager2"

    When I am on certifications management page in "Cat 1"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Category manager navigates to certifications the normal way
    Given I skip tests if "local_navmenu" is not installed
    And the following "local_navmenu > items" exist:
      | itemtype                      |
      | tool_certify_catalogue        |
      | tool_certify_mycertifications |
    And I log in as "manager2"

    When I select "Certification catalogue" from primary navigation
    And I follow "Certification management"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  Scenario: Full viewer navigates to certifications via behat step
    Given I log in as "viewer1"

    When I am on all certifications management page
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "system"
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I am on certifications management page in "Cat 1"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Full viewer navigates to certifications the normal way
    Given I skip tests if "local_navmenu" is not installed
    And the following "local_navmenu > items" exist:
      | itemtype                      |
      | tool_certify_catalogue        |
      | tool_certify_mycertifications |
    And I log in as "viewer1"

    When I select "Certification catalogue" from primary navigation
    And I follow "Certification management"
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"

    When I select "System (2)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should not see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I select "Cat 1 (1)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

    When I select "All certifications (4)" from the "Select category" singleselect
    Then I should see "Certification management"
    And I should see "Certification 000"
    And I should see "Certification 001"
    And I should see "Certification 002"
    And I should not see "Certification 003"
    And I should not see "Certification 003"

  Scenario: Category viewer navigates to certifications via behat step
    Given I log in as "viewer2"

    When I am on certifications management page in "Cat 1"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Category viewer navigates to certifications the normal way
    Given I skip tests if "local_navmenu" is not installed
    And the following "local_navmenu > items" exist:
      | itemtype                      |
      | tool_certify_catalogue        |
      | tool_certify_mycertifications |
    And I log in as "manager2"

    When I select "Certification catalogue" from primary navigation
    And I follow "Certification management"
    Then I should see "Certification management"
    And I should not see "Certification 000"
    And I should see "Certification 001"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  Scenario: Student navigates to Certification catalogue via behat step
    Given I log in as "student1"

    When I am on Certification catalogue page
    Then I should see "Certification catalogue"
    And I should see "Certification 001"
    And I should not see "Certification 000"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  @javascript
  Scenario: Student navigates to Certification catalogue the normal way
    Given I skip tests if "local_navmenu" is not installed
    And the following "local_navmenu > items" exist:
      | itemtype                      |
      | tool_certify_catalogue        |
      | tool_certify_mycertifications |
    And I log in as "student1"

    When I select "Certification catalogue" from primary navigation
    Then I should see "Certification catalogue"
    And I should see "Certification 001"
    And I should not see "Certification 000"
    And I should not see "Certification 002"
    And I should not see "Certification 003"

  Scenario: Student navigates to My certifications via behat step
    Given I log in as "student1"

    When I am on My certifications page
    Then I should see "My certifications"
    And I should see "No assigned certifications found."

  @javascript
  Scenario: Student navigates to My certifications the normal way
    Given I skip tests if "local_navmenu" is not installed
    And the following "local_navmenu > items" exist:
      | itemtype                      |
      | tool_certify_catalogue        |
      | tool_certify_mycertifications |
    And I log in as "student1"

    When I select "My certifications" from primary navigation
    Then I should see "My certifications"
    And I should see "No assigned certifications found."
