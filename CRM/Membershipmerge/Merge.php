<?php

/**
 * Performs membership history merges.
 */
class CRM_Membershipmerge_Merge {

  /**
   * @var array
   */
  private $memberships = array();

  /**
   * @param array $memberships
   *   Membership data, in the format of an API result (e.g., $result['values']).
   */
  public function __construct(array $memberships) {
    $this->memberships = $memberships;
    $this->validateMemberships();
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
