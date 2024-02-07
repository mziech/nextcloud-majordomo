# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
