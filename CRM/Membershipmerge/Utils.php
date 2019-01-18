<?php

/**
 * A factory-like delegate of api.membership.merge.
 *
 * Prepares CRM_Membershipmerge_Merge objects.
 */
class CRM_Membershipmerge_Utils {

  /**
   * @param int $contactId
   *   Contact ID for which to get merge objects.
   * @return CRM_Membershipmerge_Merge[]
   *   Keyed by membership organization contact ID.
   */
  static public function getMerges(int $contactId) {
    $directMemberships = civicrm_api3('Membership', 'get', [
      'contact_id' => $contactId,
      'owner_membership_id' => ['IS NULL' => 1],
      'return' => [
        // TODO: add other needed membership fields
        'contact_id',
        'end_date',
        'id',
        'membership_type_id',
        'membership_type_id.member_of_contact_id',
      ],
    ])['values'];

    $membershipsByOrg = array_reduce($directMemberships, function ($bucket, $item) {
      $orgId = $item['membership_type_id.member_of_contact_id'];
      $bucket[$orgId][] = $item;
      return $bucket;
    }, []);

    $result = [];
    foreach ($membershipsByOrg as $orgId => $memberships) {
      try {
        $result[$orgId] = new CRM_Membershipmerge_Merge($memberships);
      }
      catch (CRM_Membershipmerge_Exception_Merge $e) {
        // It may be okay to do nothing here... or may we should create a log?
      }
    }

    return $result;
  }

}
