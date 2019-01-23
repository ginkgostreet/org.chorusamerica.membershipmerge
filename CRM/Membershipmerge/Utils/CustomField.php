<?php

/**
 * A utility class for generating API-ready names for custom fields. The
 * relevant configurations/values don't change at runtime, so we use a
 * singleton architecture to maximize caching and reduce redundant lookups.
 */
class CRM_Membershipmerge_Utils_CustomField {

  /**
   * We only need one instance of this object, so we use the singleton
   * pattern and cache the instance in this variable.
   *
   * @var CRM_Membershipmerge_Utils_CustomField
   */
  private static $instance = NULL;

  /**
   * Metadata for custom fields, keyed by group name, then field name.
   *
   * @var array
   */
  private $customFieldMetaData = array();

  /**
   * The point of declaring this method is to make it private, so that only the
   * singleton method can be used to instantiate the class.
   */
  private function __construct() {
    // nothing to do here, really...
  }

  /**
   * Singleton method used to manage this object.
   *
   * @return CRM_Membershipmerge_Utils_CustomField
   */
  static public function singleton() {
    if (self::$instance === NULL) {
      self::$instance = new static();
    }
    return self::$instance;
  }

  /**
   * Fetches and caches custom field metadata.
   *
   * @see $this->customFieldMetaData.
   * @param string $groupName
   *   Machine name for the custom field group.
   * @return array
   *   Metadata for custom fields in the specified group.
   */
  private function fetchCustomFieldMetaData($groupName) {
    if (empty($this->customFieldMetaData[$groupName])) {
      $result = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => $groupName,
        'sequential' => 1,
      ));
      // key the results by 'name' for easy retrieval of properties by field name
      $this->customFieldMetaData[$groupName] = array_column($result['values'], NULL, 'name');
    }
    return $this->customFieldMetaData[$groupName];
  }

  /**
   * Get API-suitable field name for a custom field.
   *
   * @param string $groupName
   * @param string $fieldName
   * @return string
   * @throws Exception
   */
  public function getApiName($groupName, $fieldName) {
    $metadata = $this->fetchCustomFieldMetaData($groupName);
    if (empty($metadata[$fieldName]['id'])) {
      throw new Exception("Invalid field $fieldName");
    }
    return 'custom_' . $metadata[$fieldName]['id'];
  }

}
