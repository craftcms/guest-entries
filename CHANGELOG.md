# Release Notes for Guest Entries

## 3.0.1 - 2022-05-11

### Fixed
- Fixed a PHP error that would occur when submitting a form.  

## 3.0.0 - 2022-05-03

### Added
- Added Craft 4 compatibility.

## 2.4.0 - 2020-05-06

### Changed
- Guest Entries now requires Craft 3.5 or later.
- You can now customize the success/fail flash messages by passing a hashed successMessage/failMessage param with the request.

## 2.3.0 - 2020-04-07

### Changed
- Guest Entries now requires Craft 3.4 or later.

### Fixed
- Fixed a bug where it wasn’t clear when a section wasn’t configured with a default author. ([#56](https://github.com/craftcms/guest-entries/issues/56))

## 2.2.4 - 2019-07-11

### Fixed
- Fixed a Craft 3.2 compatibility issue.

## 2.2.3 - 2019-02-24

### Fixed
- Fixed a bug where guest entries weren’t getting their author set properly. ([#43](https://github.com/craftcms/guest-entries/issues/43)) 

## 2.2.2.1 - 2019-02-06

### Fixed
- Fixed a bug where the plugin settings weren’t saving. ([#45](https://github.com/craftcms/guest-entries/issues/45))

## 2.2.2 - 2019-02-04

### Changed
- The Section Settings table now has a UID column instead of ID.

## 2.2.1 - 2019-01-31

### Fixed
- Fixed an error that could occur when updating to Guest Entries 2.2. ([#42](https://github.com/craftcms/guest-entries/issues/42))

## 2.2.0 - 2019-01-29

### Changed
- Guest Entries now requires Craft 3.1.0-alpha.1 or later.
- You can now specify the target section by its UID or handle in addition to using ID.

### Fixed
- Fixed a bug where it wasn’t possible to turn off guest submissions for a section once it had been activated.

## 2.1.3 - 2017-12-04

### Changed
- Loosened the Craft CMS version requirement to allow any 3.x version.

## 2.1.2 - 2017-11-10

### Changed
- The `live` validation scenario is now only set if guest entry validation is enabled for the section.

### Fixed
- Fixed an error that occurred when saving a guest entry. ([#28](https://github.com/craftcms/guest-entries/issues/28))

## 2.1.1 - 2017-11-09

### Changed
- Guest Entries now sets the `live` validation scenario when saving enabled entries.

## 2.1.0 - 2017-08-03

### Added
- Added the “Enable CSRF Protection?” setting, making it possible to disable CSRF protection for `guest-entries/save` requests. ([#24](https://github.com/craftcms/guest-entries/issues/24)) 

## 2.0.1 - 2017-08-01

### Fixed
- Fixed a bug where custom field content was not getting saved. ([#23](https://github.com/craftcms/guest-entries/issues/23))

## 2.0.0 - 2017-07-14

### Added
- Craft 3 compatibility.

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

###Added
- GuestEntriesService.php to raise an ‘onBeforeSave’ event before saving a new guest entry.

## 1.0.0 - 2014-03-28

Initial release
