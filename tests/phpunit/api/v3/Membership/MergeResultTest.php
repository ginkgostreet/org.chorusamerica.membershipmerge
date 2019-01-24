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
   * Tests that the membership payment records for deleted memberships have been
   * updated to reference the surviving membership.
   */
  public function testContributionHistoryMerged() {
    // Assert the initial state, pre-merge
    $msgPreSurvive = 'Expected one payment for surviving membership pre-merge';
    $countPreSurvive = civicrm_api3('MembershipPayment', 'get', [
          'membership_id' => $this->data->membershipIdsIndividual['persist'][0],
        ])['count'];
    $this->assertEquals(1, $countPreSurvive, $msgPreSurvive);

    $msgPreDelete = 'Expected one payment per deleted membership pre-merge';
    $countPreDelete = civicrm_api3('MembershipPayment', 'get', [
      'membership_id' => ['IN' => $this->data->membershipIdsIndividual['delete']],
    ])['count'];
    $this->assertEquals(count($this->data->membershipIdsIndividual['delete']), $countPreDelete, $msgPreDelete);

    // Do the merge
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdIndividualMember]);

    // Assert the state post-merge
    $msgPostSurvive = 'Expected one payment per initial membership record post-merge';
    $countPostSurvive = civicrm_api3('MembershipPayment', 'get', [
      'membership_id' => $this->data->membershipIdsIndividual['persist'][0],
    ])['count'];
    $this->assertEquals($countPreSurvive + $countPreDelete, $countPostSurvive, $msgPostSurvive);

    $msgPostDelete = 'Expected payment records for deleted memberships to have been updated post-merge';
    $countDeletedPost = civicrm_api3('MembershipPayment', 'get', [
      'membership_id' => ['IN' => $this->data->membershipIdsIndividual['delete']],
    ])['count'];
    $this->assertEquals(0, $countDeletedPost, $msgPostDelete);
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
   * Ensures that only membership logs associated with the original membership
   * can have a status of "New."
   */
  public function testMembershipLogOnlyOriginalNew() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $statusId = civicrm_api3('MembershipLog', 'getvalue', [
      'membership_id' => ['IN' => $this->data->membershipIdsOrganization['persist']],
      'modified_date' => '2015-01-05',
      'return' => 'status_id',
    ]);

    $currentStatusId = 2;
    $this->assertEquals($currentStatusId, $statusId);
  }

  /**
   * Tests that logs from an earlier membership which occur chronologically
   * after the first log of a later membership are culled.
   */
  public function testMembershipLogOverlapsCulled() {
    // This log, the last record in the contact's first membership, should be
    // culled because it follows the 2015-01-05 membership event which marks the
    // beginning of the second membership.
    $shouldBeCulledLogId = civicrm_api3('MembershipLog', 'getvalue', [
      'membership_id' => ['IN' => $this->data->membershipIdsOrganization['delete']],
      'modified_date' => '2015-04-04',
      'return' => 'id',
    ]);

    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $expectedCnt = 0;
    $actualCnt = civicrm_api3('MembershipLog', 'getcount', ['id' => $shouldBeCulledLogId]);
    $this->assertEquals($expectedCnt, $actualCnt);
  }

  /**
   * Tests that all logs from the surviving membership persist.
   */
  public function testMembershipLogSurviving() {
    $originalLogs = civicrm_api3('MembershipLog', 'get', [
      'membership_id' => ['IN' => $this->data->membershipIdsOrganization['persist']],
      'sequential' => 0,
    ])['values'];
    $logIds = array_keys($originalLogs);

    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $remainingLogCnt = civicrm_api3('MembershipLog', 'getcount', ['id' => ['IN' => $logIds]]);

    $this->assertEquals(count($logIds), $remainingLogCnt);
  }

  /**
   * Tests that membership logs referencing the IDs of to-be-deleted memberships
   * are either updated to reference the surviving membership or are themselves
   * deleted.
   */
  public function testMembershipLogMembershipId() {
    $originalLogs = civicrm_api3('MembershipLog', 'get', [
      'membership_id' => ['IN' => $this->data->membershipIdsOrganization['delete']],
      'sequential' => 0,
    ])['values'];
    $logIds = array_keys($originalLogs);

    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $mergedLogs = civicrm_api3('MembershipLog', 'get', [
      'id' => ['IN' => $logIds],
    ])['values'];
    $membershipIds = array_unique(array_column($mergedLogs, 'membership_id'));

    // Note: These arrays are sorted to ensure the values are in the same order
    // (i.e., have the same keys), as required for array equality comparisons.
    $expected = $this->data->membershipIdsOrganization['persist'];
    sort($expected);
    sort($membershipIds);
    $this->assertEquals($expected, $membershipIds, 'Expected membership logs to reference the surviving membership(s) and only the surviving membership(s)');
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
    $this->assertEquals(0, $countDeleted, 'Expected no deleted memberships in DB');
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
    $this->assertEquals(0, $countDeleted, 'Expected no deleted memberships in DB');
  }

  /**
   * Tests that the appropriate "Membership Merged" audit activities are created.
   */
  public function testAuditRecords() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdIndividualMember]);

    $fieldUtil = CRM_Membershipmerge_Utils_CustomField::singleton();
    $auditRecords = civicrm_api3('Activity', 'get', [
      'activity_type_id' => 'membership_merge',
      'source_record_id' => $this->data->membershipIdsIndividual['persist'][0],
      'target_contact_id' => $this->data->contactIdIndividualMember,
      $fieldUtil->getApiName('membership_merge', 'deleted_membership_id') => ['IN' => $this->data->membershipIdsIndividual['delete']],
    ]);
    $this->assertEquals(count($this->data->membershipIdsIndividual['delete']), $auditRecords['count']);
  }

}
