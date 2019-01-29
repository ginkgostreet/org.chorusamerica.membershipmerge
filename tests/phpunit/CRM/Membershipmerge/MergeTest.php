<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests CRM_Membershipmerge_Merge class.
 *
 * @group headless
 */
class CRM_Membershipmerge_MergeTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

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
   * Tests that an exception is thrown if all memberships are not related to the
   * same contact.
   */
  public function testMixedContactsThrowsException() {
    $class = CRM_Membershipmerge_Exception_Merge::class;
    $msgRegex = '#^Cannot merge memberships with different contact_id values$#';
    $this->setExpectedExceptionRegExp($class, $msgRegex);

    // If it's not obvious, these parameters are simplified to address our test
    // case specifically.
    new CRM_Membershipmerge_Merge([
      [
        'contact_id' => 1,
        'membership_type_id.member_of_contact_id' => 5,
      ],
      [
        'contact_id' => 2,
        'membership_type_id.member_of_contact_id' => 5,
      ],
    ]);
  }

  /**
   * Tests that an exception is thrown if memberships belong to different organizations.
   */
  public function testMixedMemberOrgsThrowsException() {
    $class = CRM_Membershipmerge_Exception_Merge::class;
    $msgRegex = '#^Cannot merge memberships across membership organizations$#';
    $this->setExpectedExceptionRegExp($class, $msgRegex);

    // If it's not obvious, these parameters are simplified to address our test
    // case specifically.
    new CRM_Membershipmerge_Merge([
      [
        'contact_id' => 1,
        'membership_type_id.member_of_contact_id' => 1,
      ],
      [
        'contact_id' => 1,
        'membership_type_id.member_of_contact_id' => 2,
      ],
    ]);
  }

  /**
   * Tests that an exception is thrown if no membership end dates are provided
   * (preventing the selection of a survivor).
   */
  public function testNoExpirationDatesThrowsException() {
    $class = CRM_Membershipmerge_Exception_Merge::class;
    $msgRegex = '#^Could not determine surviving membership; probably source data is invalid$#';
    $this->setExpectedExceptionRegExp($class, $msgRegex);

    // If it's not obvious, these parameters are simplified to address our test
    // case specifically.
    new CRM_Membershipmerge_Merge([
      [
        'contact_id' => 1,
        'end_date' => NULL,
        'membership_type_id.member_of_contact_id' => 5,
      ],
      [
        'contact_id' => 1,
        'end_date' => NULL,
        'membership_type_id.member_of_contact_id' => 5,
      ],
    ]);
  }

  /**
   * Tests that an exception is thrown if there is nothing to merge (i.e., only
   * one membership).
   */
  public function testNothingToMergeThrowsException() {
    $class = CRM_Membershipmerge_Exception_Merge::class;
    $msgRegex = '#^Nothing to merge; pass at least two membership records$#';
    $this->setExpectedExceptionRegExp($class, $msgRegex);

    new CRM_Membershipmerge_Merge([['membership_type_id' => 1]]);
  }

}
