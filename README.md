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

See api.membership.merge in the [API
Explorer](https://docs.civicrm.org/dev/en/latest/api/#api-explorer).

## Technical Details

TODO: Describe how merges are performed.

## Known Issues

TODO

## License

[AGPL-3.0](https://github.com/ginkgostreet/org.chorusamerica.membershipmerge/blob/master/LICENSE.txt)