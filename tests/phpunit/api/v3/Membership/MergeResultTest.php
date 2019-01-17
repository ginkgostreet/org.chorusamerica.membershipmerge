<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests expectations wrt api.membership.merge's result.
 *
 * @group headless
 */
class api_v3_Membership_MergeResultTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * @var TestDataProvider
   */
  private $data;

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
   * Called before each test.
   */
  public function setUp() {
    parent::setUp();

    $this->data = new TestDataProvider();
  }

  /**
   * Tests that the signature of return values matches the expected signature.
   */
  public function testReturnSignature() {
    $apiResult = civicrm_api3('membership', 'merge', ['contact_id' => $this->data->individualContactId]);

    $firstMerge = array_shift($apiResult['values']);
    $this->assertInternalType('int', $firstMerge['membership_organization_contact_id']);
    $this->assertInternalType('int', $firstMerge['remaining_membership_id']);
    $this->assertInternalType('array', $firstMerge['deleted_membership_ids']);

    $firstDeletedMembershipId = array_shift($firstMerge['deleted_membership_ids']);
    $this->assertInternalType('int', $firstDeletedMembershipId);
  }

}
