<?php

declare(strict_types=1);

namespace DrupalQa\Behat;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Steps drupal-qa adds on top of the Drupal Extension's own.
 *
 * The default behat.yml.dist uses this context as is. A project that wants
 * its own steps extends it:
 *
 * @code
 * class FeatureContext extends \DrupalQa\Behat\FeatureContext {
 * }
 * @endcode
 */
class FeatureContext extends RawDrupalContext {

  /**
   * Saves a screenshot when a step fails on a driver that can take one.
   *
   * The HTTP-only driver used by default cannot, so this is a no-op there.
   *
   * @AfterStep
   */
  public function takeScreenshotAfterFailedStep(AfterStepScope $scope): void {
    if ($scope->getTestResult()->isPassed()) {
      return;
    }
    try {
      $screenshot = $this->getSession()->getDriver()->getScreenshot();
    }
    catch (\Throwable) {
      return;
    }
    $dir = getenv('BEHAT_SCREENSHOT_DIR') ?: sys_get_temp_dir() . '/behat-screenshots';
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }
    $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $scope->getFeature()->getTitle() ?? 'feature');
    file_put_contents(sprintf('%s/%s_line%d.png', $dir, $name, $scope->getStep()->getLine()), $screenshot);
  }

  /**
   * Fails a step whose response came from a CDN bot challenge.
   *
   * A challenged request is a 403 that reads exactly like Drupal denying
   * access, so "anonymous users get 403" would pass without reaching Drupal.
   *
   * @AfterStep
   */
  public function failOnCdnChallenge(AfterStepScope $scope): void {
    try {
      $headers = array_change_key_case($this->getSession()->getResponseHeaders());
    }
    catch (\Throwable) {
      return;
    }
    if (($headers['cf-mitigated'][0] ?? '') === 'challenge') {
      throw new \RuntimeException('The CDN answered this request with a bot challenge, so the test never reached Drupal. Send the site\'s bot-bypass token (x-pantheon-bot-bypass).');
    }
  }

  /**
   * Asserts a CSS selector matches an element on the page.
   *
   * @Then I should see the :selector element
   */
  public function assertElementExists(string $selector): void {
    if ($this->getSession()->getPage()->find('css', $selector) === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }
  }

  /**
   * Asserts a CSS selector matches nothing on the page.
   *
   * @Then I should not see the :selector element
   */
  public function assertElementNotExists(string $selector): void {
    if ($this->getSession()->getPage()->find('css', $selector) !== NULL) {
      throw new \RuntimeException(sprintf('Element "%s" was found but should not exist.', $selector));
    }
  }

}
