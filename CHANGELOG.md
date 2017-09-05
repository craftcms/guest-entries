Changelog
=========

## 1.5.3 - 2017-09-05

### Fixed
- Fixed a bug where sections’ Default Author options were limited to 100 users. ([#25](https://github.com/craftcms/guest-entries/issues/25))

## 1.5.2 - 2016-06-04

### Added
- Added the ability to limit the data returned on a successful save for an AJAX request. This removes potentially sensitive data from being returned.

## 1.5.1 - 2016-01-13

### Fixed
- Fixed a PHP error that would occur if the guest entry failed validation.

## 1.5.0 - 2015-12-23

### Added
 - Added ‘onSuccess‘ and ‘onError‘ events.

## 1.4.0 - 2015-12-20

### Updated
- Updated to take advantage of new Craft 2.5 plugin features.

## 1.3.1 - 2014-03-14

### Fixed
-Fixed a bug where the “Validate Entry” setting Lightswitch would reset to on position after being set to off.

## 1.3.0 - 2014-10-30

### Added
-Added the ‘entryVariable‘ config setting.

## 1.2.2 - 2014-09-17

### Fixed
- Fixed a bug where validation would fail when saving guest entries for sections/entry types with dynamic titles.
## 1.2.1 - 2014-07-2

### Added
- Added the ability to explicitly set whether validation is required on a per-section basis.

## 1.2.0 - 2014-07-2

### Added
- Added support for the Client user when running Craft Client.

## 1.1.0 - 2014-03-28

### Added
- GuestEntriesService.php to raise an ‘onBeforeSave’ event before saving a new guest entry.

## 1.0.0 - 2014-03-28

### Changed
- Initial release
