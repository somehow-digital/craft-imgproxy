# Changelog

## [Unreleased]

### Added

- `Plugin::instance()`, which returns the plugin, or throws when it is not loaded.

### Changed

- The `Plugin`, `Settings`, `BuilderService` and `GravityHelper` classes are final, so they can no longer be extended.
- The `Builder` service is now `BuilderService`. The `builder` component and `Plugin::getBuilder()` are unchanged.
- The `GravityUtility` class is now `GravityHelper`, and is internal.
- `Settings::defineRules()` is protected, like in Craft's `Model`.
- The components are declared in `Plugin::config()`.

### Fixed

- Signed URLs are signed including the leading slash of their path, as imgproxy expects. Before, imgproxy rejected them.

### Removed

- The `string` validation rule of the settings, which the typed properties already enforce.

## [1.0.4] - 2026-10-31

### Added

- Checks and tests workflows, and a security workflow.

### Changed

- Update the plugin icon.
- Enforce LF line endings.

### Fixed

- Handle some edge cases.

## [1.0.3] - 2026-02-20

### Fixed

- Improve the focal point and position handling.

## [1.0.2] - 2026-02-20

### Fixed

- Use the extend option for the letterbox mode.

## [1.0.1] - 2026-02-20

### Removed

- The encryption masker.

## [1.0.0] - 2026-02-20

### Added

- Initial release.

[Unreleased]: https://github.com/somehow-digital/craft-imgproxy/compare/v1.0.4...HEAD
[1.0.4]: https://github.com/somehow-digital/craft-imgproxy/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/somehow-digital/craft-imgproxy/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/somehow-digital/craft-imgproxy/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/somehow-digital/craft-imgproxy/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/somehow-digital/craft-imgproxy/releases/tag/v1.0.0
