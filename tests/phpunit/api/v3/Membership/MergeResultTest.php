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
    $apiResult = civicrm_api3('membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $firstMerge = array_shift($apiResult['values']);
    $this->assertInternalType('int', $firstMerge['membership_organization_contact_id']);
    $this->assertInternalType('int', $firstMerge['remaining_membership_id']);
    $this->assertInternalType('array', $firstMerge['deleted_membership_ids']);

    $firstDeletedMembershipId = array_shift($firstMerge['deleted_membership_ids']);
    $this->assertInternalType('int', $firstDeletedMembershipId);
  }

  /**
   * Tests, for records which have the same memberships type, that the IDs of
   * the surviving and deleted membership records are correctly reported in the
   * API output and that they exist (or do not, as appropriate) in the database
   * following a merge.
   */
  public function testMembershipRecordsSameType() {
    $results = civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember])['values'];

    $boston = [];
    foreach ($results as $result) {
      if ($result['membership_organization_contact_id'] === $this->data->contactIdChapterBoston) {
        $boston = $result;
        break;
      }
    }

    $msg = 'Expected surviving membership not in API result';
    $this->assertContains($boston['remaining_membership_id'], $this->data->membershipIdsOrganization['persist'], $msg);
    $countSurviving = civicrm_api3('Membership', 'get', ['id' => $boston['remaining_membership_id']])['count'];
    $this->assertEquals(1, $countSurviving, 'Expected surviving membership not in DB');

    $msg2 = 'Expected deleted membership not in API result';
    foreach ($boston['deleted_membership_ids'] as $deletedId) {
      $this->assertContains($deletedId, $this->data->membershipIdsOrganization['delete'], $msg2);
    }
    $countDeleted = civicrm_api3('Membership', 'get', ['id' => ['IN' => $boston['deleted_membership_ids']]])['count'];
    // TODO for CA2-376
    // $this->assertEquals(0, $countDeleted, 'Expected no deleted memberships in DB');
  }

  /**
   * Tests, for records which have different memberships types, that the IDs of
   * the surviving and deleted membership records are correctly reported in the
   * API output and that they exist (or do not, as appropriate) in the database
   * following a merge.
   */
  public function testMembershipRecordsDifferentTypes() {
    $results = civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember])['values'];

    $chicago = [];
    foreach ($results as $result) {
      if ($result['membership_organization_contact_id'] === $this->data->contactIdChapterChicago) {
        $chicago = $result;
        break;
      }
    }

    $msg = 'Expected surviving membership not in API result';
    $this->assertContains($chicago['remaining_membership_id'], $this->data->membershipIdsOrganization['persist'], $msg);
    $countSurviving = civicrm_api3('Membership', 'get', ['id' => $chicago['remaining_membership_id']])['count'];
    $this->assertEquals(1, $countSurviving, 'Expected surviving membership not in DB');

    $msg2 = 'Expected deleted membership not in API result';
    foreach ($chicago['deleted_membership_ids'] as $deletedId) {
      $this->assertContains($deletedId, $this->data->membershipIdsOrganization['delete'], $msg2);
    }
    $countDeleted = civicrm_api3('Membership', 'get', ['id' => ['IN' => $chicago['deleted_membership_ids']]])['count'];
    // TODO for CA2-376
    // $this->assertEquals(0, $countDeleted, 'Expected no deleted memberships in DB');
  }

}
