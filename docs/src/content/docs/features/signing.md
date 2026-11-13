---
title: Signing
description: Sign the imgproxy URLs so only your site can request transforms.
---

With a **Signature Key** and **Signature Salt**, the plugin signs every URL it builds. Set the same hex-encoded key and salt on your imgproxy server (`IMGPROXY_KEY` and `IMGPROXY_SALT`), which then rejects unsigned URLs.

The key and salt must be hexadecimal strings, and set together. Without them, the URLs are unsigned.
