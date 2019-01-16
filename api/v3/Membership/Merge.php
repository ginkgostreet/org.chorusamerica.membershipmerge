<?php
use CRM_Membershipmerge_ExtensionUtil as E;

/**
 * Membership.Merge API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_membership_Merge_spec(&$spec) {
  $spec['contact_id'] = civicrm_api3('Membership', 'getfield', [
    'action' => 'create',
    'name' => 'contact_id',
  ])['values'];
}

/**
 * Membership.Merge API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CiviCRM_API_Exception
 */
function civicrm_api3_membership_Merge($params) {
  try {
    $raiseException = TRUE;
    $contactId = CRM_Utils_Type::validate($params['contact_id'], 'Int', $raiseException, 'One of parameters ', $raiseException);
  }
  catch (\CRM_Core_Exception $e) {
    throw new \CiviCRM_API3_Exception('Invalid format for contact_id', 'invalid_format', $params, $e);
  }

  $returnValues = array();

  return civicrm_api3_create_success($returnValues, $params, 'Member', 'Merge');
}
