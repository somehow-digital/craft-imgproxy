---
title: Settings
description: Point Craft at your imgproxy server.
---

1. Go to **Settings** → **imgproxy**.
2. Fill in the settings below and save.

| Setting              | Required | Description                                                                                                          |
| -------------------- | :------: | -------------------------------------------------------------------------------------------------------------------- |
| **Endpoint**         |   Yes    | The base URL of your imgproxy server, an HTTP or HTTPS URL.                                                          |
| **Signature Key**    |          | The hex-encoded HMAC key. Set it together with the salt, see [signing](/features/signing/).                          |
| **Signature Salt**   |          | The hex-encoded HMAC salt. Set it together with the key.                                                             |
| **Fallback Format**  |          | `jpg` (default), `png`, `webp` or `avif`, for sources browsers can't display, such as HEIC and TIFF, with no format. |
| **Thumbnails**       |          | On by default. Turn it off to let Craft generate the thumbnails, see [thumbnails](/features/thumbnails/).            |
| **Thumbnail Format** |          | `webp` (default), `jpg`, `png` or `avif`.                                                                            |

:::tip
All settings support [environment variables](https://craftcms.com/docs/5.x/configure.html#env).
:::
