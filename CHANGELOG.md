# Changelog

## [Unreleased]

### Added

- `Settings::isConfigured()`, which tells whether the endpoint is set and the signature is valid.
- `Plugin::instance()`, which returns the plugin, or throws when it is not loaded.

### Changed

- Crops without upscaling use the `fill-down` resizing type, so imgproxy 3.0 or newer is required.
- The endpoint must be an HTTP or HTTPS URL, and the signature key and salt must be hexadecimal strings, and set together.
- The `Plugin`, `Settings`, `BuilderService` and `GravityHelper` classes are final, so they can no longer be extended.
- The `Builder` service is now `BuilderService`. The `builder` component and `Plugin::getBuilder()` are unchanged.
- The `GravityUtility` class is now `GravityHelper`, and is internal.
- `Settings::defineRules()` is protected, like in Craft's `Model`.
- The components are declared in `Plugin::config()`.
- Transforms without a quality use Craft's `defaultImageQuality`, instead of the imgproxy default.

### Fixed

- An invalid endpoint or signature, an empty endpoint or a signature with only a key or a salt no longer fail requests or produce broken URLs. The plugin leaves the URLs to Craft instead.
- Sources browsers can't display, such as HEIC and TIFF, are converted to JPEG when the transform has no format, like Craft does. Before, imgproxy kept the source format.
- Transforms with only a width or a height calculate the other one from the size of the asset, like Craft. Before, stretched and letterboxed images could end up with the wrong size.
- Crops without upscaling keep the aspect ratio of the transform when the image is smaller than the transform, like Craft.
- Stretched images are enlarged, regardless of upscaling, like Craft.
- Letterboxed images are placed at the position of the transform. Before, they were always centered.
- Files with an uppercase extension, such as `IMAGE.JPG`, are handled. Before, they were left to Craft.
- Signed URLs are signed including the leading slash of their path, as imgproxy expects. Before, imgproxy rejected them.
- Assets in volumes without public URLs, or with a relative base URL, are left to Craft. Before, the plugin built URLs from sources imgproxy can't fetch.
- Files that Craft doesn't know as images, such as ICO, are left to Craft. Before, requesting a transform of one failed.

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
