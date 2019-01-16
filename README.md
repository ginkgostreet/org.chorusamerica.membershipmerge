# Membership Merge

Membership Merge (org.chorusamerica.membershipmerge) is an extension for
[CiviCRM](https://civicrm.org) which provides an API for merging membership
history. The audience for this extension is site administrators.

The need for this extension arose from years of an unorthodox approach to
modeling memberships, where each real-world renewal resulted in the creation of
a new membership record rather than an update to the existing one. As a result
of this approach, staff were unable to use reports and scheduled reminders with
confidence (e.g., searching for members with a recurring membership would return
members who had a recurring membership in the past but whose current membership
is not recurring).

## Installation

This extension has not yet been published for in-app installation. [General
extension installation instructions](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#installing-a-new-extension)
are available in the CiviCRM System Administrator Guide.

## Requirements

* PHP v5.4+
* CiviCRM v4.7+

## Usage

```php
$apiResult = civicrm_api3('Member', 'merge', ['contact_id' => 42]);
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

See api.membership.merge in the [API
Explorer](https://docs.civicrm.org/dev/en/latest/api/#api-explorer). See also
[Technical Details](#technical-details) for how merges are performed.

## Technical Details

TODO: Describe how merges are performed.

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


## Known Issues

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

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)