# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.0] - 2025-11-23
- Nextcloud 32 compatibility
- Replace PHP imap functions with imapengine library, no more IMAP module or c-client library required!
- Introduced majordomo:idle OCC command to wait for incoming emails

## [2.1.1] - 2025-06-05
### Changed
- Nextcloud 31 compatibility

## [2.1.0] - 2024-10-11
### Changed
- Nextcloud 30 compatibility
- Housekeeping and dependency updates
- Remove not-null constraints from some classic Majordomo specific database fields

### Fixed
- Creation of new mailing lists (with missing fields)

## [2.0.0] - 2024-10-01
### Changed
- Nextcloud 29 compatibility
- Introduced concept of list admins and moderators
- Introduced fine-grained list permissions
- Subsequently, make the app visible to ALL users ([#25](https://github.com/mziech/nextcloud-majordomo/issues/25))
- Exposed configuration for internal list manager, making it useful without the (pre-)historic Majordomo software

## [2.0.0-pre6] - 2024-09-27
## Fixed
- Fix possible permission escalation by sub-admin users

## [2.0.0-pre5] - 2024-09-23
## Fixed
- Fix mailing list access resolver

## [2.0.0-pre4] - 2024-09-22
## Fixed
- Fix moderator detection
- Only show new button for admins

## [2.0.0-pre3] - 2024-09-22
## Fixed
- Resolution of visible mailing lists
- Some 'de' translation issues

## [2.0.0-pre2] - 2024-09-20
### Changed
- Nextcloud 29 compatibility
- Introduced concept of list admins and moderators
- Introduced fine-grained list permissions
- Subsequently, make the app visible to ALL users ([#25](https://github.com/mziech/nextcloud-majordomo/issues/25))
- Exposed configuration for internal list manager, making it useful without the (pre-)historic Majordomo software

## [1.2.0] - 2024-02-07
### Changed
- Nextcloud 28 compatibility
- Internal preparations for use without external Majordomo installation and without admin privileges ([#25](https://github.com/mziech/nextcloud-majordomo/issues/25))
- Update frontend dependencies ([#38](https://github.com/mziech/nextcloud-majordomo/issues/38))

## [1.1.5] - 2023-08-20
### Changed
- Nextcloud 25-27 compatibility

## [1.1.4] - 2023-05-23
### Changed
- Nextcloud 26 compatibility

## [1.1.3] - 2022-12-03
### Changed
- Nextcloud 25 compatibility
- Updated NPM dependencies

## [1.1.2] - 2022-09-08
### Fixed
- Fixed too long payload column ([#31](https://github.com/mziech/nextcloud-majordomo/issues/31))

## [1.1.1] - 2022-08-13
### Added
- User interface for handling bounce messages

### Fixed
- Wording for IMAP connection security ([#28](https://github.com/mziech/nextcloud-majordomo/issues/28))

## [1.0.3] - 2022-01-19
### Added
- Simple configuration of IMAP options

### Fixed
- Do not poll IMAP mailbox if servername is blank
- Lock list action buttons on policy change until save ([#22](https://github.com/mziech/nextcloud-majordomo/issues/22))

## [1.0.2] - 2022-01-13
### Added
- Nextcloud 23 compatibility

### Fixed
- Add depenency on `imap` extension
- Do not assume mod_rewrite is installed ([#19](https://github.com/mziech/nextcloud-majordomo/issues/19))

## [1.0.1] - 2021-08-08
### Added
- First appstore release
- Nextcloud 22 compatibility

## [1.0.0] - 2021-07-03
### Changed
- Nextcloud 21 compatibility

## [0.0.8] - 2021-04-25
### Added
- Initial working release
