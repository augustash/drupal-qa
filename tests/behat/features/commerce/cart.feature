@smoke @drupal-qa @commerce
Feature: Shopping cart
  The cart page works for visitors and customers.

  Scenario: Anonymous visitors can open the cart
    Given I am an anonymous user
    When I go to "/cart"
    Then I should get a 200 HTTP response

  @api
  Scenario: A new customer sees an empty cart
    Given I am logged in as a user with the "authenticated" role
    When I go to "/cart"
    Then I should see "cart is empty"
