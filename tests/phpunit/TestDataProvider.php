<?php

/**
 * A helper class to prepare sample data for the test suite.
 *
 * Here's the scenario:
 *
 * Four contacts exist whose memberships we will evaluate: an individual, a
 * second individual who is a recent addition to an organization with a long
 * membership history, an organization which confers some of its memberships to
 * said individuals, and a second (control) organization with no memberships at
 * all.
 *
 * Additionally, three chapters sell memberships:
 *
 * Atlanta Chapter
 * ===============
 * - has one associated membership type
 * - has one member (the organization), with no duplicate memberships
 *
 * Boston Chapter
 * ==============
 * - has one associated membership type
 * - has the organization as a member, with duplicate memberships which should
 *   be merged
 * - the organization's memberships are conferred to the individual contacts
 *
 * Chicago Chapter
 * ===============
 * - has two associated membership types
 * - the organization contact has one of each type; these should be merged
 * - the individual contact has two of the same type; these should be merged
 */

class TestDataProvider {

  /**
   * @var id
   *   The ID of a contact who is a recent addition to an organization with a
   *   long membership history. The contact has conferred membership, but for
   *   only part of the organization's membership period.
   */
  private $contactIdPartiallyConferred;

  /**
   * @var int
   */
  private $contactIdChapterAtlanta;

  /**
   * @var int
   */
  private $contactIdChapterBoston;

  /**
   * @var int
   */
  private $contactIdChapterChicago;

  /**
   * @var int
   *   The ID of the individual contact which holds a membership.
   */
  private $contactIdIndividualMember;

  /**
   * @var int
   *   The ID of an individual contact with no memberships.
   */
  private $contactIdNoMembership;

  /**
   * @var int
   *   The ID of the organization contact which holds a membership.
   */
  private $contactIdOrganizationMember;

  /**
   * @var int
   */
  private $employmentRelationshipId = 5;

  /**
   * @var array
   *   Keyed 'persist' and 'delete', for the expected fate of the memberships.
   */
  private $membershipIdsIndividual = ['persist' => [], 'delete' => []];

  /**
   * @var array
   *   Keyed 'persist' and 'delete', for the expected fate of the memberships.
   */
  private $membershipIdsOrganization = ['persist' => [], 'delete' => []];

  /**
   * @var int
   */
  private $membershipTypeIdAtlanta;

  /**
   * @var int
   */
  private $membershipTypeIdBoston;

  /**
   * @var int
   */
  private $membershipTypeIdChicago;

  /**
   * @var int
   */
  private $membershipTypeIdChicagoVips;

  /**
   * Allows inaccessible properties to be read.
   * 
   * Property visibility is restricted so that values are not altered externally;
   * the magic get function restores the ability for them to be read.
   * 
   * @param string $property
   * @return mixed
   * @throws Exception
   */
  public function __get($property) {
    if (property_exists($this, $property)) {
      return $this->$property;
    }

    throw new Exception("Cannot access nonexistent property $property");
  }

  public function __construct() {
    // set up membership types
    $this->contactIdChapterAtlanta = $this->createOrganization('Atlanta Chapter');
    $this->membershipTypeIdAtlanta = $this->createMembershipType($this->contactIdChapterAtlanta);
    $this->contactIdChapterBoston = $this->createOrganization('Boston Chapter');
    $this->membershipTypeIdBoston = $this->createMembershipType($this->contactIdChapterBoston);
    $this->contactIdChapterChicago = $this->createOrganization('Chicago Chapter');
    $this->membershipTypeIdChicago = $this->createMembershipType($this->contactIdChapterChicago);
    $this->membershipTypeIdChicagoVips = $this->createMembershipType($this->contactIdChapterChicago, 'chicago_vips');

    // set up (most) contacts
    $this->contactIdNoMembership = $this->createOrganization('Nonmember, LLC');
    $this->contactIdOrganizationMember = $this->createOrganization('Acme Corp');
    $this->contactIdIndividualMember = $this->createIndividual('lifer@acme.corp');
    $this->contactIdPartiallyConferred = $this->createIndividual('johnny@come.lately');

    // set up organization memberships

    // The Atlanta membership will be neither deleted nor persisted; since it
    // is the only membership for this organization, it will simply be ignored
    // because there is nothing to merge.
    $this->createMembership($this->contactIdOrganizationMember, '2018-08-08', $this->membershipTypeIdAtlanta);

    // Boston
    $conferees = [$this->contactIdIndividualMember];
    $this->membershipIdsOrganization['delete'][] = $this->createMembership($this->contactIdOrganizationMember, '2014-04-04', $this->membershipTypeIdBoston, $conferees);
    $this->membershipIdsOrganization['delete'][] = $this->createMembership($this->contactIdOrganizationMember, '2015-01-05', $this->membershipTypeIdBoston, $conferees);
    // Johnny Come Lately joins Acme Corp about two years into the life of their
    // membership; his membership history should vary from Lifer's accordingly.
    $conferees[] = $this->contactIdPartiallyConferred;
    $partiallyConferredMemId = $this->createMembership($this->contactIdOrganizationMember, '2016-06-06', $this->membershipTypeIdBoston, $conferees);
    $this->membershipIdsOrganization['delete'][] = $partiallyConferredMemId;
    // Repurpose Johnny Come Lately's join event; make it a conferment event
    $log = civicrm_api3('MembershipLog', 'getsingle', [
      'membership_id.contact_id' => $this->contactIdPartiallyConferred,
      'membership_id.owner_membership_id' => $partiallyConferredMemId,
      'modified_date' => '20160606',
    ]);
    $log['modified_date'] = '20160901';
    civicrm_api3('MembershipLog', 'create', $log);
    // End repurposing of join event
    $this->membershipIdsOrganization['persist'][] = $this->createMembership($this->contactIdOrganizationMember, '2017-06-01', $this->membershipTypeIdBoston, $conferees);

    // Chicago
    $this->membershipIdsOrganization['delete'][] = $this->createMembership($this->contactIdOrganizationMember, '2017-07-07', $this->membershipTypeIdChicagoVips, [], NULL);
    $this->membershipIdsOrganization['persist'][] = $this->createMembership($this->contactIdOrganizationMember, '2018-08-08', $this->membershipTypeIdChicago);

    // set up direct individual memberships
    $this->membershipIdsIndividual['delete'][] = $this->createMembership($this->contactIdIndividualMember, '2016-06-06', $this->membershipTypeIdChicago, [], "Chicago World's Fair");
    $this->membershipIdsIndividual['persist'][] = $this->createMembership($this->contactIdIndividualMember, '2018-08-28', $this->membershipTypeIdChicago);

  }

  /**
   * @param string $email
   * @return int
   *   Contact ID.
   */
  private function createIndividual($email) {
    $params = [
      'contact_type' => 'Individual',
      'email' => $email,
    ];

    return civicrm_api3('Contact', 'create', $params)['id'];
  }

  /**
   * Creates a membership and sundry ancillary records.
   *
   * Note that this method does an important thing that could not be achieved by
   * using CiviCRM's native membership conferment (i.e., first relating contacts
   * then creating the memberships): it backfills membership log history.
   *
   * @param int $contactId
   *   The contact ID of the member.
   * @param string $joinDate
   * @param int $membershipTypeId
   * @param array $confereeContactIds
   *   The IDs of contacts to whom the created membership should be conferred.
   * @param string $source
   * @return int
   *   The membership ID.
   */
  private function createMembership($contactId, $joinDate, $membershipTypeId, array $confereeContactIds = [], $source = 'default source') {
    $params = [
      'contact_id' => $contactId,
      'join_date' => $joinDate,
      'membership_type_id' => $membershipTypeId,
      'api.MembershipLog.create' => [
        $this->prepareMembershipLogParams(1, $joinDate),
        $this->prepareMembershipLogParams(2, $joinDate, 90),
        $this->prepareMembershipLogParams(3, $joinDate, 365),
        $this->prepareMembershipLogParams(4, $joinDate, 367),
      ],
    ];
    if (isset($source)) {
      $params['source'] = $source;
    }

    $membershipId = civicrm_api3('Membership', 'create', $params)['id'];

    // api.Membership.create helpfully creates the first log record for each
    // membership; unfortunately, it gives it a modified date of NOW, so we'll
    // delete it to represent a more realistic workflow (where the first log
    // date matches the join date)
    $now = new DateTime();
    $today = $now->format('Ymd');

    // Fantastically, api.MembershipLog.delete is broken, so we'll use the BAO.
    $membershipLog = new CRM_Member_BAO_MembershipLog();
    $membershipLog->membership_id = $membershipId;
    $membershipLog->modified_date = $today;
    $membershipLog->delete();

    $this->createMembershipContribution($membershipId, $contactId);

    if (count($confereeContactIds)) {
      foreach ($confereeContactIds as $confereeContactId) {
        $params['contact_id'] = $confereeContactId;
        $params['owner_membership_id'] = $membershipId;
        $conferredMembershipId = civicrm_api3('Membership', 'create', $params)['id'];

        // api.Membership.create helpfully creates the first log record for each
        // membership; unfortunately, it gives it a modified date of NOW, so we'll
        // delete it to represent a more realistic workflow (where the first log
        // date matches the join date)
        $conferredMembershipLog = new CRM_Member_BAO_MembershipLog();
        $conferredMembershipLog->membership_id = $conferredMembershipId;
        $conferredMembershipLog->modified_date = $today;
        $conferredMembershipLog->delete();
      }
    }
    return $membershipId;
  }

  /**
   * @param int $membershipId
   * @param int $contactId
   */
  private function createMembershipContribution($membershipId, $contactId) {
    civicrm_api3('Contribution', 'create', [
      'contact_id' => $contactId,
      'financial_type_id' => 'Member Dues',
      'total_amount' => 100,
      'api.MembershipPayment.create' => ['membership_id' => $membershipId, 'contribution_id' => '$value.id'],
    ]);
  }

  /**
   * Creates a membership type associated with the passed contact ID.
   *
   * @param int $memberOrgId
   * @param boolean $name
   *   An optional name for the membership type.
   * @return int
   *   The membership type ID.
   */
  private function createMembershipType($memberOrgId, $name = NULL) {
    $params = [
      'duration_interval' => 1,
      'duration_unit' => 'year',
      'financial_type_id' => 'Member Dues',
      'member_of_contact_id' => $memberOrgId,
      'name' => $name || "for_org_$memberOrgId",
      'period_type' => 'rolling',
    ];
    return civicrm_api3('MembershipType', 'create', $params)['id'];
  }

  /**
   * Creates organization contact.
   *
   * @param string $orgName
   * @return int
   *   Contact ID.
   */
  private function createOrganization($orgName) {
    return civicrm_api3('Contact', 'create', [
      'contact_type' => 'Organization',
      'organization_name' => $orgName,
    ])['id'];
  }

  /**
   * @param int $status
   *   The status the membership was in at the time the log was created; 1 =>
   *   New, 2 => Current, 3 => Grace, 4 => Expired.
   * @param string $joinDate
   *   The date the membership started.
   * @param int $daysAfterJoin
   *   The number of days after the $joinDate that the log was created.
   * @return array
   *   Parameters suitable for chaining api.MembershipLog.create to
   *   api.Membership.create.
   */
  private function prepareMembershipLogParams($status, $joinDate, $daysAfterJoin = 0) {
    $logDate = new DateTime($joinDate);
    $logDate->modify("+{$daysAfterJoin} days");

    return [
      'end_date' => '$value.end_date',
      'membership_id' => '$value.id',
      'membership_type_id' => '$value.membership_type_id',
      'modified_date' => $logDate->format('Ymd'),
      'start_date' => $joinDate,
      'status_id' => $status,
    ];
  }

}
