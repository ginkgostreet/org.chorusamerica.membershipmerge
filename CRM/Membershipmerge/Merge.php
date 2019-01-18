<?php

/**
 * Performs membership history merges.
 */
class CRM_Membershipmerge_Merge {

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
  }

  public function doMerge() {

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
