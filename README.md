<img src="./src/icon.svg" width="95" height="70" alt="imgproxy icon">

# `imgproxy` for Craft CMS
> Provides an [imgproxy](https://imgproxy.net/) integration for [Craft CMS](https://craftcms.com/).

## Requirements

* Craft CMS 5.8.0 or later.
* PHP 8.2 or later.

## Installation

Install this plugin from the Plugin Store or via Composer.

#### Plugin Store

Go to the “Plugin Store” in your project’s Control Panel, search for
“imgproxy” and click on the “Install” button in its modal window.

#### Composer

```sh
composer require somehow-digital/craft-imgproxy
./craft plugin/install imgproxy
```

## Configuration

1. Go to **Settings** → **imgproxy**.
2. Enter the **Endpoint** — the base URL of your imgproxy server.
3. To enable URL signing, enter the **Signature Key** and **Signature Salt** (hex-encoded HMAC key and salt).
4. Click **Save**.

> **Tip:** All settings support environment variables. See [Environmental Configuration](https://craftcms.com/docs/5.x/configure.html#env) in the Craft CMS docs to learn more about that.
