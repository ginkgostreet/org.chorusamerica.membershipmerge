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
   * Tests that the "member since" date for the surviving membership matches the
   * member since value from the earliest log record.
   */
  public function testMembershipMemberSince() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $memberSince = civicrm_api3('Membership', 'getvalue', [
      'contact_id' => $this->data->contactIdOrganizationMember,
      'membership_type_id' => $this->data->membershipTypeIdBoston,
      'return' => 'join_date',
    ]);

    $expected = '2014-04-04';
    $this->assertEquals($expected, $memberSince);
  }

  /**
   * Tests that the source for the surviving membership matches the source value
   * from the earliest log record.
   */
  public function testMembershipMemberSource() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdIndividualMember]);

    $source = civicrm_api3('Membership', 'getvalue', [
      'id' => $this->data->membershipIdsIndividual['persist'][0],
      'return' => 'source',
    ]);

    $expected = "Chicago World's Fair";
    $this->assertEquals($expected, $source);
  }

  /**
   * Tests that the source for the surviving membership matches the source value
   * from the earliest log record when the original is NULL.
   */
  public function testMembershipMemberSourceNull() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    // Fetch via BAO because API handles NULLs poorly.
    $membership = new CRM_Member_BAO_Membership();
    $membership->contact_id = $this->data->contactIdOrganizationMember;
    $membership->membership_type_id = $this->data->membershipTypeIdChicago;
    $membership->find(TRUE);

    $expected = NULL;
    $this->assertEquals($expected, $membership->source);
  }

  /**
   * Tests that the start date for the surviving membership identifies the start
   * of the last uninterrupted membership period.
   */
  public function testMembershipStartDate() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $memberSince = civicrm_api3('Membership', 'getvalue', [
      'contact_id' => $this->data->contactIdOrganizationMember,
      'membership_type_id' => $this->data->membershipTypeIdBoston,
      'return' => 'start_date',
    ]);

    $expected = '2016-06-06';
    $this->assertEquals($expected, $memberSince);
  }

  /**
   * Tests that membership logs for contacts who have had conferred membership
   * for the entire duration of the parent membership match those of the parent,
   * with the exception of the referenced membership_id and the log ID.
   */
  public function testMembershipLogForFullConferees() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $parentMembershipId = civicrm_api3('Membership', 'getvalue', [
      'contact_id' => $this->data->contactIdOrganizationMember,
      'membership_type_id' => $this->data->membershipTypeIdBoston,
      'return' => 'id',
    ]);
    $parentLogs = civicrm_api3('MembershipLog', 'get', [
      'membership_id' => $parentMembershipId,
      'options' => ['sort' => 'modified_date ASC'],
      'sequential' => 1,
    ])['values'];
    array_walk($parentLogs, function (&$log) {
      unset($log['id'], $log['membership_id']);
    });

    $childMembershipId = civicrm_api3('Membership', 'getvalue', [
      'contact_id' => $this->data->contactIdIndividualMember,
      'owner_membership_id' => $parentMembershipId,
      'return' => 'id',
    ]);
    $childLogs = civicrm_api3('MembershipLog', 'get', [
      'membership_id' => $childMembershipId,
      'sequential' => 1,
    ])['values'];
    array_walk($childLogs, function (&$log) {
      unset($log['id'], $log['membership_id']);
    });

    $this->assertEquals($parentLogs, $childLogs);
  }

  /**
   * Tests that membership logs for contacts who have had conferred membership
   * for only part of the duration of the parent membership contain:
   *   - a record of the conferment event
   *   - all the logs in the parent log following the date of the conferment
   *   - nothing more
   */
  public function testMembershipLogForPartialConferees() {
    civicrm_api3('Membership', 'merge', ['contact_id' => $this->data->contactIdOrganizationMember]);

    $conferredLogs = civicrm_api3('MembershipLog', 'get', [
      'membership_id.contact_id' => $this->data->contactIdPartiallyConferred,
      'membership_id.membership_type_id' => $this->data->membershipTypeIdBoston,
      'options' => ['sort' => 'modified_date ASC'],
    ])['values'];

    $this->assertEquals('2016-09-01', array_shift($conferredLogs)['modified_date'], 'Expected first log to be conferment event');

    $this->assertEquals('2016-09-04', array_shift($conferredLogs)['modified_date'], 'Expected second log to match log from deleted membership');
    $this->assertEquals('2017-06-01', array_shift($conferredLogs)['modified_date'], 'Expected third log to match first log from surviving membership');
    $this->assertEquals('2017-08-30', array_shift($conferredLogs)['modified_date'], 'Expected fourth log to match second log from surviving membership');
    $this->assertEquals('2018-06-01', array_shift($conferredLogs)['modified_date'], 'Expected fifth log to match third log from surviving membership');
    $this->assertEquals('2018-06-03', array_shift($conferredLogs)['modified_date'], 'Expected sixth log to match fourth log from surviving membership');

    $this->assertCount(0, $conferredLogs, 'Expected no additional logs');
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
   * Tests, for records which have the same membership types, that the IDs of
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
   * Tests, for records which have different membership types, that the IDs of
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
