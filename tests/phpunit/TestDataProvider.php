<?php

/**
 * A helper class to prepare sample data for the test suite.
 */

class TestDataProvider {

  /**
   * @var TestDataProvider
   */
  private static $instance = NULL;

  /**
   * @var int
   *   The ID of the 'End User' membership type.
   */
  private $endUserMembershipTypeId;

  /**
   * @var int
   *   The ID of the individual contact who holds a membership.
   */
  private $individualContactId;

  /**
   * @var int
   *   The ID of the membership expected to survive a merge.
   */
  private $individualSurvivingMembershipId;

  /**
   * @var array
   *   The IDs of the memberships expected to be deleted in a merge.
   */
  private $individualSquashedMembershipIds = array();

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

  /**
   * Get or set the single instance of TestDataProvider
   *
   * @return TestDataProvider
   */
  static public function singleton() {
    if (self::$instance === NULL) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Visibility set to private so the class is only ever instantiated via singleton().
   */
  private function __construct() {
    $this->populateDb();
  }

  /**
   * Prepares the database with sample data used by the tests.
   */
  private function populateDb() {
    // In a bare DB, no membership types have been created yet.
    $this->endUserMembershipTypeId = civicrm_api3('MembershipType', 'create', [
      'duration_interval' => 1,
      'duration_unit' => 'year',
      'financial_type_id' => 'Member Dues',
      'member_of_contact_id' => 1, // Default organization
      'name' => 'Ender User',
      'period_type' => 'rolling',
    ])['id'];

    $this->individualContactId = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Pat',
      'last_name' => 'Member',
    ])['id'];

    $this->individualSurvivingMembershipId = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->individualContactId,
      'join_date' => '2019-01-01',
      'membership_type_id' => $this->endUserMembershipTypeId,
    ])['id'];

    $this->individualSquashedMembershipIds[] = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->individualContactId,
      'join_date' => '2016-06-06',
      'membership_type_id' => $this->endUserMembershipTypeId,
    ])['id'];
  }

}
