<?php

use Civi\Test\HeadlessInterface;

/**
 * Tests expectations wrt api.membership.merge's result.
 *
 * @group headless
 */
class api_v3_Membership_MergeResultTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface {

  /**
   * @var int
   *   The ID of the 'End User' membership type.
   */
  private $endUserMembershipTypeId;

  /**
   * @var int
   *   The ID of the individual contact who holds a membership.
   */
  private $individualContactId;

  /**
   * @var int
   *   The ID of the membership expected to survive a merge.
   */
  private $individualSurvivingMembershipId;

  /**
   * @var array
   *   The IDs of the memberships expected to be deleted in a merge.
   */
  private $individualSquashedMembershipIds = array();

  /**
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   * See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->callback(array($this, 'populateDb'), 'populateDb')
      ->apply();
  }

  /**
   * Prepares the database with sample data used by the tests.
   */
  public function populateDb() {
    // In a bare DB, no membership types have been created yet.
    $this->endUserMembershipTypeId = civicrm_api3('MembershipType', 'create', [
      'duration_interval' => 1,
      'duration_unit' => 'year',
      'financial_type_id' => 'Member Dues',
      'member_of_contact_id' => 1, // Default organization
      'name' => 'Ender User',
      'period_type' => 'rolling',
    ])['id'];

    $this->individualContactId = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Pat',
      'last_name' => 'Member',
    ])['id'];

    $this->individualSurvivingMembershipId = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->individualContactId,
      'join_date' => '2019-01-01',
      'membership_type_id' => $this->endUserMembershipTypeId,
    ])['id'];

    $this->individualSquashedMembershipIds[] = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->individualContactId,
      'join_date' => '2016-06-06',
      'membership_type_id' => $this->endUserMembershipTypeId,
    ])['id'];
  }

  /**
   * Tests that the signature of return values matches the expected signature.
   */
  public function testReturnSignature() {
    $apiResult = civicrm_api3('membership', 'merge', ['contact_id' => $this->individualContactId]);

    $firstMerge = array_shift($apiResult['values']);
    $this->assertInternalType('int', $firstMerge['membership_organization_contact_id']);
    $this->assertInternalType('int', $firstMerge['remaining_membership_id']);
    $this->assertInternalType('array', $firstMerge['deleted_membership_ids']);

    $firstDeletedMembershipId = array_shift($firstMerge['deleted_membership_ids']);
    $this->assertInternalType('int', $firstDeletedMembershipId);
  }

}
