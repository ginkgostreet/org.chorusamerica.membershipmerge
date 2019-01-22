<?php

use CRM_Membershipmerge_ExtensionUtil as E;

/**
 * Consolidates methods related to managing the Deleted Membership ID custom
 * field during extension lifecycle events.
 *
 * Creating the custom field depends on the existence of the Activity Type,
 * created when hook_civicrm_managed fires, and the custom group, created
 * here. The order in which these entities become available is not
 * deterministic, so to avoid order of operations problems they are managed
 * with custom code rather than standard CiviCRM utilities.
 */
trait CRM_Membershipmerge_Upgrader_DeletedMembershipIdTrait {

  /**
   * @var int
   */
  private $membershipMergeActivityTypeId;

  /**
   * @var int
   */
  private $membershipMergeCustomGroupId;

  /**
   * Gets the ID of the custom group, creating it if necessary.
   *
   * @return int
   */
  private function getMembershipMergeCustomGroupId() {
    if (!isset($this->membershipMergeCustomGroupId)) {
      $this->installMembershipMergeCustomGroup();
    }
    return $this->membershipMergeCustomGroupId;
  }

  /**
   * @return int
   */
  private function getMembershipMergeActivityTypeId() {
    if (!isset($this->membershipMergeActivityTypeId)) {
      $this->membershipMergeActivityTypeId = civicrm_api3('OptionValue', 'getvalue', [
        'name' => 'membership_merge',
        'option_group_id' => 'activity_type',
        'return' => 'value',
      ]);
    }
    return $this->membershipMergeActivityTypeId;
  }

  /**
   * Creates the custom field (and the custom group if necessary).
   */
  private function installDeletedMembershipIdCustomField() {
    civicrm_api3('CustomField', 'create', [
      'column_name' => 'deleted_membership_id',
      'custom_group_id' => $this->getMembershipMergeCustomGroupId(),
      'data_type' => 'Int',
      'html_type' => 'Text',
      'is_active' => 1,
      'is_required' => 0,
      'is_searchable' => 1,
      'is_search_range' => 1,
      'is_view' => 1,
      'label' => ts('Deleted membership ID'),
      'name' => 'deleted_membership_id',
    ]);
  }

  /**
   * Installs the custom group.
   */
  private function installMembershipMergeCustomGroup() {
    $this->membershipMergeCustomGroupId = civicrm_api3('CustomGroup', 'create', [
      'collapse_adv_display' => 0,
      'collapse_display' => 0,
      'extends' => 'Activity',
      'extends_entity_column_value' => [$this->getMembershipMergeActivityTypeId()],
      'is_multiple' => 0,
      'is_public' => 0,
      'is_reserved' => 0,
      'name' => 'membership_merge',
      'style' => 'Inline',
      'table_name' => 'civicrm_value_mem_merge',
      'title' => ts('Membership Merge'),
    ])['id'];
  }

  /**
   * Uninstalls the custom group.
   *
   * Any contained custom fields are automatically uninstalled as well.
   */
  private function uninstallMembershipMergeCustomGroup() {
    civicrm_api3('CustomGroup', 'get', [
      'extends' => 'Activity',
      'name' => 'membership_merge',
      'api.CustomGroup.delete' => [],
    ]);
  }

}
