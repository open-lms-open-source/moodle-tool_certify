@tool @tool_certify @openlms @tool_certificate
Feature: Issuing of certificates for certification completion

  Background:
    Given I skip tests if "tool_certificate" is not installed
    And unnecessary Admin bookmarks block gets deleted
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | viewer1  | Viewer    | 1        | viewer1@example.com  |
      | student1 | Student   | 1        | student1@example.com |
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
      | tool/certify:admin           | Allow      | pmanager | System       |           |
      | tool/certificate:manage      | Allow      | pmanager | System       |           |
      | moodle/site:configview       | Allow      | pmanager | System       |           |
      | tool/certificate:issue       | Allow      | pmanager | System       |           |
      | tool/certificate:viewallcertificates | Allow| pmanager | System       |           |
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager1  | pmanager      | System       |           |
      | viewer1   | pviewer       | System       |           |
    And the following "enrol_programs > programs" exist:
      | fullname    | idnumber | category | sources |
      | Program 000 | PR0      |          | certify |
      | Program 001 | PR1      |          | certify |
    And the following "tool_certify > certifications" exist:
      | fullname          | idnumber | category | program1 | sources |
      | Certification 000 | CT0      |          | PR0      | manual  |
      | Certification 001 | CT1      |          | PR1      | manual  |

  @javascript
  Scenario: Manager may assign certificate template to a certification
    Given I log in as "manager1"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I follow "New certificate template"
    And I set the field "Name" to "Certificate 1"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I follow "New certificate template"
    And I set the field "Name" to "Certificate 2"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I am on all certifications management page
    And I follow "Certification 000"
    And I click on "Period settings" "link" in the "#region-main" "css_element"

    When I click on "Update certificate template" "link"
    And the following fields match these values:
      | Certificate template | Not set       |
    And I set the following fields to these values:
      | Certificate template | Certificate 1 |
    And I press dialog form button "Update certificate template"
    Then I should see "Certificate 1" in the "Certificate template:" definition list item

    When I click on "Update certificate template" "link"
    And the following fields match these values:
      | Certificate template | Certificate 1 |
    And I set the following fields to these values:
      | Certificate template | Certificate 2 |
    And I press dialog form button "Update certificate template"
    Then I should see "Certificate 2" in the "Certificate template:" definition list item

    When I click on "Update certificate template" "link"
    And the following fields match these values:
      | Certificate template | Certificate 2 |
    And I set the following fields to these values:
      | Certificate template | Certificate 1 |
    And I press dialog form button "Update certificate template"
    Then I should see "Certificate 1" in the "Certificate template:" definition list item

    When I click on "Update certificate template" "link"
    And I set the following fields to these values:
      | Certificate template | Not set       |
    And I press dialog form button "Update certificate template"
    Then I should see "Not set" in the "Certificate template:" definition list item

  @javascript
  Scenario: User is issued a certification certificate
    Given I log in as "manager1"
    And I navigate to "Certificates > Manage certificate templates" in site administration
    And I follow "New certificate template"
    And I set the field "Name" to "Certificate 1"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I am on all certifications management page
    And I follow "Certification 000"
    And I click on "Period settings" "link" in the "#region-main" "css_element"

    And I click on "Update certificate template" "link"
    And I set the following fields to these values:
      | Certificate template | Certificate 1 |
    And I press dialog form button "Update certificate template"
    And I should see "Certificate 1" in the "Certificate template:" definition list item

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
    And I click on "5/11/22" "link" in the "Program 000" "table_row"
    And I press "Override period dates"
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
    And I should see "Valid" in the "Certification status:" definition list item

    And I log out

    When I run the "tool_certify\task\cron" task
    And I log in as "student1"
    And I follow "Profile" in the user menu
    And I click on "//a[contains(.,'My certificates') and contains(@href,'tool/certificate')]" "xpath_element"
    Then I should see "Certificate 1"
