# Membership Merge

Membership Merge (org.chorusamerica.membershipmerge) is an extension for
[CiviCRM](https://civicrm.org) which provides an API for merging membership
history. The audience for this extension is site administrators.

![Diagram: membership log merging strategy](/images/member-log.svg)

The need for this extension arose from years of modeling memberships in an
unorthodox way, where each real-world renewal resulted in the creation of
a new membership record rather than an update to the existing one. As a result
of this approach, staff were unable to use reports and Scheduled Reminders with
confidence (e.g., searching for members with a recurring membership would return
members who had a recurring membership in the past but whose current membership
is not recurring).

## Installation

This extension has not yet been published for in-app installation. [General
extension installation instructions](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension)
are available in the CiviCRM System Administrator Guide.

## Requirements

* PHP v7.0.0+
* CiviCRM v4.7+

## Usage

```php
$apiResult = civicrm_api3('Membership', 'merge', ['contact_id' => 42]);
var_dump($apiResult['values']);

// Example output:
// array(2) {
//   [0]=>
//   array(3) {
//     ["membership_organization_contact_id"]=>
//     int(1)
//     ["remaining_membership_id"]=>
//     int(44)
//     ["deleted_membership_ids"]=>
//     array(2) {
//       [0]=>
//       int(11)
//       [1]=>
//       int(33)
//     }
//   }
//   [1]=>
//   array(3) {
//     ["membership_organization_contact_id"]=>
//     int(9)
//     ["remaining_membership_id"]=>
//     int(100)
//     ["deleted_membership_ids"]=>
//     array(1) {
//       [0]=>
//       int(62)
//     }
//   }
// }
```

See `api.Membership.merge` in the [API
Explorer](https://docs.civicrm.org/dev/en/latest/api/#api-explorer). See also
[Technical Details](#technical-details) for how merges are performed.

### Helper Script

The extension ships with a helper script (`bin/dedupe-all-memberships.php`)
which finds every contact with more than one associated membership and passes each
to `api.Membership.merge`. The helper script depends on [`cv`](http://github.com/civicrm/cv)
to bootstrap CiviCRM.

Note that in some cases a contact may legitimately be
associated with more than one membership (see [Technical Details](#technical-details)).
Attempts to merge which do not succeed are recorded in
[CiviCRM's log](https://docs.civicrm.org/dev/en/latest/tools/debugging/#viewing-log-files).

If your site does not run the version of PHP required by this extension, you can
still make use of it by specifying the version of PHP to use for command line
operations. For example:

```bash
# Note: your path to PHP may vary

# Step 1: Install the extension (you should be able to do this via the UI
# without trouble, even if your site's version of PHP is too low)
/usr/bin/php7.1 `command -v cv` en org.chorusamerica.membershipmerge

# Step 2: Execute the helper script
/usr/bin/php7.1 /path/to/org.chorusamerica.membershipmerge/bin/dedupe-all-memberships.php
```

## Technical Details

Memberships are merged so that only one remains per membership organization.
(Merging memberships which have different types but the same membership
organization is consistent with CiviCRM's [membership up-sell
feature](https://docs.civicrm.org/user/en/latest/membership/renewals/#membership-up-sell).)

The membership with the latest expiry date is selected as the surviving
membership record. The surviving membership is updated with the `join_date` and
`source` fields from the original membership (i.e., that which has the log record
with the earliest modified date). The `start_date` ("Member Since" in most user
interfaces) is calculated by squashing the log records for the duplicate
memberships and identifying the start of the most recent uninterrupted membership
period.

Membership logs are merged by ordering the logs from earliest to latest
`modified_date` and resolving overlaps in history in favor of the later
membership. See diagram above. Care is taken to ensure that only logs from the
original membership have a status of "New."

All payment records associated with duplicate memberships are updated to
reference the surviving membership.

### Conferred Membership
When a contact's ID is passed to the API, any conferred memberships (i.e.,
memberships where `owner_membership_id` is not `NULL`) she may hold are ignored
by the API.

The history for conferred memberships is rewritten only when the ID of a contact
who _confers_ membership is passed to the API. The conferring memberships are
merged, the duplicates are deleted, and the surviving membership is conferred
anew. For each conferee, the conferment date is determined from the
`modified_date` of the first membership log associated with the conferring
membership or any of its duplicates. The membership log of the conferring
membership is then replayed into the history of the conferred membership from
that point forward, preserving the data point of the conferment date.

> Note: It is common for organizations to offer both individual membership and
> organizational membership that confers to individuals, and for both classes of
> membership to be up-sellable. For example, individuals may be offered Student
> and Full memberships, and organizations may be offered different levels based
> on budget.
>
> In such a scenario, the organizational membership types should specify one
> membership organization, and the individual membership types should specify
> another. Otherwise, an individual with both a personal and a conferred
> organizational membership can, in some cases, inadvertently cause the type and
> end date of the conferred membership (which should change only when the
> organization's membership record changes) to change when upgrading the
> individual membership.
>
> It is highly recommended that users of this extension ensure their membership
> type configuration is consistent with the above before performing merges.

### Audit Log
For the purposes of debugging, quality assurance, and reconciling old records
with the rewritten history, an audit log is provided.

On installation, this extension creates a custom Activity Type "Membership
Merge." When a merge is performed, the ID of the surviving membership is stored
in the Activity's `source_record_id` field (the same field that is referenced
when other membership events are logged as Activities), while the deleted
membership ID is stored in a custom field "Deleted membership ID."

Using a custom Activity rather than an ad hoc database table provides visibility
to site users as well as a logging mechanism for ad hoc merges that may
occur in the future.

To maximize the value of the audit log, it is recommended that site
administrators take a snapshot of the database prior to performing merges. Since
memberships will be deleted following the merge, the backup provides the only
way to reverse a merge or to perform a detailed review of the changes that
occurred.

## Testing

A suite of [PHPUnit](https://phpunit.de/) tests exists to ensure consistent
behavior of the API over time. Developers may run it to verify changes they
introduce do not cause regressions, as follows:

```bash
cd /path/to/extension/root
env CIVICRM_UF=UnitTests phpunit4 --group headless
```

See also [PHPUnit Tests](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension)
in the CiviCRM Developer Guide.

### Hindsight is 20/20
The code to set up the data against which the tests are performed is far from
trivial. The approach was selected to try to take advantage of CiviCRM's logic
(i.e., automatic creation of related records, setting of defaults, etc.), but,
in retrospect, it would have been much easier to import SQL files. The
challenges involved in the logic and in navigating CiviCRM's quirks exceed any
of the benefits (see the comment in `CRM_Membershipmerge_Merge::updateSurvivingMembership()`
-- yes, that's right: the decisions around populating the test database impacted
the implementation of the extension).

## Known Issues/Limitations

* The API is intentionally limited to dealing with only one contact at a time.
  Executing a request such as follows will raise an exception:
  ```php
    civicrm_api3('Membership', 'Merge', [
      'contact_id' => ['IN' => [1, 2, 3]],
    ]);`
  ```
  This decision was made to avoid making the implementation more complex than
  necessary and to minimize misconceptions about what actions are performed
  against which entities in the case of memberships which involve more than one
  contact (i.e., when there is conferment).
* This extension does not attempt to reconcile Activities associated with
  membership events (e.g., Membership Signup, Change Membership Type, etc.).
* In some cases, both a success and a failure can come out of a merge operation.
  Suppose a contact has duplicate memberships in both Chapter A and Chapter B.
  For Chapter A, the merge is successful. The Chapter B merge fails because of
  data integrity issues (e.g., none of the records has a `join_date`). In such a
  case, the API sets `is_error` equal to 0 in the response and records the
  unsuccessful merge attempt in [CiviCRM's
  log](https://docs.civicrm.org/dev/en/latest/tools/debugging/#viewing-log-files).

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)