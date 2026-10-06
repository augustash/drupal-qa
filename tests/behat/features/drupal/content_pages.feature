@smoke @drupal-qa @content
Feature: Pages load
  The front page loads and a missing page is a real 404.

  Scenario: The front page loads
    Given I am an anonymous user
    When I go to the homepage
    Then I should get a 200 HTTP response

  Scenario: A missing page returns 404
    Given I am an anonymous user
    When I go to "/drupal-qa-page-that-does-not-exist"
    Then I should get a 404 HTTP response
