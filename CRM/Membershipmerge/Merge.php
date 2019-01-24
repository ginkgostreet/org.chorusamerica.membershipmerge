<?php

use CRM_Membershipmerge_ExtensionUtil as E;

/**
 * Performs membership history merges.
 */
class CRM_Membershipmerge_Merge {

  /**
   * @var int
   */
  private $contactId = NULL;

  /**
   * @var array
   *
   */
  private $deletedMembershipIds = array();

  /**
   * @var array
   *   Keyed by membership ID.
   */
  private $memberships = array();

  /**
   * @var int
   */
  private $survivingMembershipId;

  /**
   * @param array $memberships
   *   Membership data, in the format of an API result (e.g., $result['values']).
   */
  public function __construct(array $memberships) {
    // ensure memberships are keyed by ID
    $this->memberships = array_column($memberships, NULL, 'id');
    $this->validateMemberships();
    $this->contactId = (int) array_unique(array_column($this->memberships, 'contact_id'))[0];
  }

  /**
   * Deletes memberships that have been merged into the surviving record, and
   * creates an audit trail in the form of Membership Merge activities.
   */
  private function cullMemberships() {
    $actingContact = CRM_Core_Session::singleton()->getLoggedInContactID();
    // In cases where the acting contact cannot be determined from the session
    // (e.g., unit tests, and possibly CLI scripts), fall back to the domain
    // organization.
    if (!$actingContact) {
      $actingContact = civicrm_api3('Domain', 'getvalue', [
        'current_domain' => 1,
        'return' => 'contact_id',
      ]);
    }

    $fieldUtil = CRM_Membershipmerge_Utils_CustomField::singleton();
    civicrm_api3('Membership', 'get', [
      'id' => ['IN' => $this->getDeletedMembershipIds()],
      'api.Membership.delete' => [],
      'api.Activity.create' => [
        'activity_type_id' => 'membership_merge',
        'source_contact_id' => $actingContact,
        'source_record_id' => $this->getSurvivingMembershipId(),
        'subject' => E::ts('Membership record ID %1 was updated', [
          1 => $this->getSurvivingMembershipId(),
        ]),
        'target_id' => $this->contactId,
        $fieldUtil->getApiName('membership_merge', 'deleted_membership_id') => '$value.id',
      ],
    ]);
  }

  public function doMerge() {
    $this->updatePayments();
    $this->mergeMembershipLogs();
    $this->cullMemberships();
  }

  /**
   * @return array
   *   The IDs of the memberships that have been/will be deleted by the merge.
   */
  public function getDeletedMembershipIds() {
    if (empty($this->deletedMembershipIds)) {
      $membershipIds = array_keys($this->memberships);
      $survivingId = $this->getSurvivingMembershipId();

      $this->deletedMembershipIds = array_diff($membershipIds, (array) $survivingId);
    }
    return $this->deletedMembershipIds;
  }

  /**
   * Determines which membership should persist, i.e., the record into which all
   * of the other passed memberships should be merged.
   *
   * @return int
   */
  public function getSurvivingMembershipId() {
    if (!isset($this->survivingMembershipId)) {
      // get expiry dates keyed by membership ID
      $expiryDates = array_column($this->memberships, 'end_date', 'id');
      // get the key (membership ID) of the latest expiry date
      $this->survivingMembershipId = array_keys($expiryDates, max($expiryDates))[0];
    }
    return (int) $this->survivingMembershipId;
  }

  private function mergeMembershipLogs() {
    $inClause = implode(',', $this->getDeletedMembershipIds());
    $query = '
      UPDATE civicrm_membership_log
      SET membership_id = %1
      WHERE membership_id IN (' . $inClause . ')';
    CRM_Core_DAO::executeQuery($query, [1 => [$this->getSurvivingMembershipId(), 'Int']]);
  }

  /**
   * Updates any civicrm_membership_payment record which references the ID of a
   * membership slated for deletion to reference the surviving membership ID.
   */
  private function updatePayments() {
    $query = '
      UPDATE civicrm_membership_payment
      SET membership_id = ' . $this->getSurvivingMembershipId() . '
      WHERE membership_id IN (' . implode(',', $this->getDeletedMembershipIds()) . ')';
    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Validates supplied membership data; throws exception on failure.
   *
   * @throws CRM_Membershipmerge_Exception_Merge
   */
  private function validateMemberships() {
    if (count($this->memberships) < 2) {
      $msg = 'Nothing to merge; pass at least two membership records';
      throw new CRM_Membershipmerge_Exception_Merge($msg, 'invalid_data', $this->memberships);
    }

    $membershipTypeIds = array_unique(array_column($this->memberships, 'membership_type_id.member_of_contact_id'));
    if (count($membershipTypeIds) !== 1) {
      $msg = 'Cannot merge memberships across membership organizations';
      throw new CRM_Membershipmerge_Exception_Merge($msg, 'invalid_data', $this->memberships);
    }

    $contactIds = array_unique(array_column($this->memberships, 'contact_id'));
    if (count($contactIds) !== 1) {
      $msg = 'Cannot merge memberships with different contact_id values';
      throw new CRM_Membershipmerge_Exception_Merge($msg, 'invalid_data', $this->memberships);
    }
  }

}
