# AGENTS

This project is a Craft CMS plugin that provides an integration for [imgproxy](https://imgproxy.net/).

## Tech Stack

- **PHP 8.2+**: The primary programming language.
- **Craft CMS 5.8+**: The content management system this plugin is built for.
- **Yii 2**: The underlying PHP framework used by Craft CMS.
- **Composer**: Dependency management.

## Key Concepts

### Image Processing
The plugin facilitates image processing by integrating with an imgproxy server, allowing for on-the-fly image transformations. The core URL-building logic lives in the `ImageTransformer` service (`src/services/ImageTransformer.php`), which extends `Component`, implements `ImageTransformerInterface`, and is registered on the plugin instance both as a component and as the image transformer type.

The `getTransformUrl()` method in the service maps all Craft CMS image transform options to imgproxy URL parameters:
- **Modes**: `crop` → `fill`, `fit` → `fit`, `letterbox` → `force` (with background color and gravity), `stretch` → `force`.
- **Position/gravity**: Craft position strings (e.g. `top-left`, `center-center`) are mapped to imgproxy `GravityEnum` values via `GravityUtility::mapPositionToGravity()` (`src/utilities/GravityUtility.php`).
- **Focal point**: When an asset has a focal point set, `GravityFocusPoint` is used for `crop` mode instead of the position setting.
- **Upscaling**: The `upscale` setting maps to imgproxy's `Enlarge` option.
- **Letterbox fill color**: The `fill` setting maps to imgproxy's `Background` option; `null` or `transparent` results in a transparent background (RGBA 0,0,0,0).

### Settings
Plugin configuration is handled via a settings model (`src/models/Settings.php`). Settings include:
- `endpoint`: The imgproxy server base URL.
- `signatureKey`: HMAC key for URL signing (hex-encoded).
- `signatureSalt`: HMAC salt for URL signing (hex-encoded).

All settings support environment variables via `EnvAttributeParserBehavior`.

## Project Structure

- `src/`: Contains the plugin's source code.
  - `models/`: Data models, including `Settings.php`.
  - `services/`: Plugin services, including `Builder.php`.
  - `templates/`: Twig templates for the control panel UI.
    - `settings/plugin.twig`: The plugin settings page template.
  - `utilities/`: Utility classes, including `GravityUtility.php`.
  - `Plugin.php`: The main plugin class.

## Entry Points & Initialization

### Plugin Initialization (`src/Plugin.php`)
- `init()`: Registers aliases, components, image transformers, the thumbnail handler, and the preview handler.
- `registerAliases()`: Sets up the `@imgproxy` alias.
- `registerComponents()`: Registers the `ImageTransformer` service as the `imageTransformer` component.
- `registerThumbnailHandler()`: Listens to `Assets::EVENT_DEFINE_THUMB_URL` and replaces CP asset thumbnail URLs with imgproxy URLs.
- `registerAssetHandler()`: Listens to `Asset::EVENT_BEFORE_DEFINE_URL` and replaces asset preview URLs (e.g. focal point editor) with imgproxy URLs, setting `$event->handled = true` to prevent local transform generation.
- `createSettingsModel()`: Returns a new `Settings` model instance.
- `settingsHtml()`: Renders the settings page template (`src/templates/settings/plugin.twig`) for the control panel.

## Common Tasks

### Development Setup
1. Clone the repository into a Craft CMS project's `plugins/` directory.
2. Run `composer install` in the plugin root.
3. Install the plugin: `./craft plugin/install imgproxy`.

## Coding Guidelines
- Update this document when adding new features or changing existing ones.
- Follow Craft CMS and Yii 2 coding standards.
- Use `Craft::t('imgproxy', '...')` for all translatable strings.
- Ensure all new settings support environment variables via `EnvAttributeParserBehavior`.
- `somehow-digital/imgproxy` is used to build URLs.
- Abbreviations for names should not be used.
