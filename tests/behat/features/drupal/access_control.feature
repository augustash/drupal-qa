@smoke @drupal-qa @access
Feature: Access control
  Administration pages are closed to visitors and open to administrators.

  Scenario: Anonymous visitors cannot reach structure
    When I go to "/admin/structure"
    Then I should get a 403 HTTP response

  Scenario: Anonymous visitors cannot reach configuration
    When I go to "/admin/config"
    Then I should get a 403 HTTP response

  Scenario: Anonymous visitors cannot list people
    When I go to "/admin/people"
    Then I should get a 403 HTTP response

  @api
  Scenario: Administrators can reach administration
    Given I am logged in as a new user with the "administrator" role
    When I go to "/admin"
    Then I should get a 200 HTTP response
