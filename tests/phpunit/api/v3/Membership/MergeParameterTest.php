<?php

use Civi\Test\HeadlessInterface;

/**
 * Tests expectations wrt api.membership.merge's parameters.
 *
 * @group headless
 */
class api_v3_Membership_MergeParameterTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface {

  /**
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   * See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Tests that an exception is thrown if the contact_id parameter is passed as
   * an array. Per CA2-367, the API is intentionally limited to dealing with
   * only one contact at a time.
   */
  public function testContactIdAsArrayThrowsException() {
    $this->setExpectedException(CiviCRM_API3_Exception::class);
    civicrm_api3('Membership', 'Merge', [
      'contact_id' => ['IN' => [1, 2, 3]],
    ]);
  }

  /**
   * Tests that an exception is thrown if the required contact_id parameter is
   * not supplied.
   */
  public function testNoContactIdThrowsException() {
    $this->setExpectedException(CiviCRM_API3_Exception::class);
    civicrm_api3('Membership', 'Merge', []);
  }

  /**
   * Tests that an exception is thrown if the contact_id is not int-like.
   */
  public function testNonIntLikeContactIdThrowsException() {
    $this->setExpectedException(CiviCRM_API3_Exception::class);
    civicrm_api3('Membership', 'Merge', [
      'contact_id' => 'banana',
    ]);
  }

}
