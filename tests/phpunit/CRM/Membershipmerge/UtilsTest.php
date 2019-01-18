<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests CRM_Membershipmerge_Utils class.
 *
 * @group headless
 */
class CRM_Membershipmerge_UtilsTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

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
   * Called before each test.
   */
  public function setUp() {
    parent::setUp();

    $this->data = new TestDataProvider();
  }

  /**
   * Tests that only direct memberships are accounted for.
   */
  public function testConferredMembershipsExcluded() {
    $merges = \CRM_Membershipmerge_Utils::getMerges($this->data->contactIdIndividualMember);
    $this->assertArrayNotHasKey($this->data->contactIdChapterBoston, $merges);
    $this->assertArrayHasKey($this->data->contactIdChapterChicago, $merges);
  }

  /**
   * Tests that an empty array is returned when there are no memberships for a
   * contact.
   */
  public function testEmptyArrayForNoMemberships() {
    $merges = \CRM_Membershipmerge_Utils::getMerges($this->data->contactIdNoMembership);
    $this->assertInternalType('array', $merges);
    $this->assertEmpty($merges);
  }

  /**
   * Tests that the contact's memberships are grouped according to the
   * organization specified in the membership type configuration.
   */
  public function testSeparationOfMembershipsByOrg() {
    $merges = \CRM_Membershipmerge_Utils::getMerges($this->data->contactIdOrganizationMember);
    $this->assertArrayHasKey($this->data->contactIdChapterBoston, $merges, 'Expected there to be a merge for Boston');
    $this->assertArrayHasKey($this->data->contactIdChapterChicago, $merges, 'Expected there to be a merge for Chicago');
  }

  /**
   * Test that the only membership associated with an organization (per the
   * membership type config) is excluded (there is nothing to merge).
   */
  public function testSingleMembershipForOrgIsExcluded() {
    $merges = \CRM_Membershipmerge_Utils::getMerges($this->data->contactIdOrganizationMember);
    $this->assertArrayNotHasKey($this->data->contactIdChapterAtlanta, $merges);
  }

}
