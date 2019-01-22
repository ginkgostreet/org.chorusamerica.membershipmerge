<?php

/**
 * Lifecycle events in this extension will cause these registry records to be
 * automatically inserted, updated, or deleted from the database as appropriate.
 * For more details, see "hook_civicrm_managed" (at
 * https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_managed/) as well
 * as "API and the Art of Installation" (at
 * https://civicrm.org/blogs/totten/api-and-art-installation).
 */
use CRM_Membershipmerge_ExtensionUtil as E;

return array(
  array(
    'module' => E::LONG_NAME,
    'name' => 'membership_merge_activity_type',
    'entity' => 'OptionValue',
    'params' => array(
      'component_id' => 'CiviMember',
      'description' => ts('Duplicate membership records merged.'),
      'icon' => 'fa-compress',
      'is_reserved' => 1,
      'label' => ts('Membership Merged'),
      'name' => 'membership_merge',
      'option_group_id' => 'activity_type',
      'version' => 3,
    ),
  ),
);
