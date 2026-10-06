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
   * Names of the users this context created, deleted after each scenario.
   *
   * @var string[]
   */
  protected array $createdUsers = [];

  /**
   * Logs in as a new user with a role, created on the site through Drush.
   *
   * Drupal Extension's "I am logged in as a user with the :role role" cannot
   * create a user through its Drush driver from 5.3 on (its field checks need
   * the local API driver), and CI tests a remote environment. This creates
   * the user with drush user:create and logs in with a one-time login link,
   * so it needs nothing but Drush access to the site.
   *
   * @Given I am logged in as a new user with the :role role
   */
  public function loginAsNewUserWithRole(string $role): void {
    $drush = $this->getDriver('drush');
    $name = 'drupal_qa_' . bin2hex(random_bytes(4));
    $drush->drush('user:create', [escapeshellarg($name)], [
      'mail' => escapeshellarg($name . '@example.com'),
      'password' => escapeshellarg(bin2hex(random_bytes(16))),
    ]);
    $this->createdUsers[] = $name;
    if (!in_array(strtolower($role), ['authenticated', 'authenticated user'], TRUE)) {
      $drush->drush('user:role:add', [escapeshellarg($role), escapeshellarg($name)]);
    }

    $link = trim($drush->drush('user:login', [], ['name' => escapeshellarg($name), 'no-browser' => NULL]));
    $path = parse_url((string) strtok($link, "\n"), PHP_URL_PATH);
    if (!is_string($path) || !str_contains($path, '/user/reset/')) {
      throw new \RuntimeException(sprintf('drush user:login returned no login link for %s: %s', $name, $link));
    }
    $this->visitPath($path);
  }

  /**
   * Deletes the users this context created.
   *
   * @AfterScenario
   */
  public function deleteCreatedUsers(): void {
    foreach ($this->createdUsers as $name) {
      try {
        $this->getDriver('drush')->drush('user:cancel', [escapeshellarg($name)], ['delete-content' => NULL, 'yes' => NULL]);
      }
      catch (\Throwable) {
        // A user left behind is untidy, not a test failure.
      }
    }
    $this->createdUsers = [];
  }

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
