#!/usr/bin/env php
<?php

/**
 * This helper script is intended to serve as a harness for the Membership.merge
 * API. The tool will extract a list of IDs for contacts with more than one
 * non-conferred membership and pass each to the API.
 *
 * Depends on cv to bootstrap CiviCRM.
 */
$output = [];
$cvNotFound = 1;
exec('command -v cv', $output, $cvNotFound);

if ($cvNotFound) {
  fwrite(STDERR, "Program cv not found; cv is required to bootstrap CiviCRM.\n");
  die(1);
}

// Bootstrap CiviCRM
eval(`cv php:boot`);

$query = '
  SELECT contact_id, COUNT(contact_id) AS cnt
  FROM civicrm_membership
  GROUP BY contact_id
  HAVING cnt > 1';
$membership = CRM_Core_DAO::executeQuery($query);

while ($membership->fetch()) {
  civicrm_api3('Membership', 'merge', ['contact_id' => $membership->contact_id]);
}

die(0);
