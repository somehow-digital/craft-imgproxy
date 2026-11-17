---
title: Modes
description: Serve the transforms from imgproxy, or store them in Craft.
---

The **Mode** applies to the image transforms and the thumbnails alike.

## Proxy

The default. The URLs of the transforms point to imgproxy, which transforms the images when a browser requests them. Put a CDN or a cache in front of imgproxy, so it doesn't transform an image twice.

## Store

imgproxy generates the transforms, and Craft stores them in the transform filesystem of the volume, and serves them like its own. Craft indexes, defers, regenerates and deletes them as usual, so imgproxy only needs to be reachable from Craft, not from the browsers.

Craft only stores transforms of files its image driver can open. imgproxy serves the others, as in the proxy mode.

Craft keeps the transforms it has generated before, until you clear its transform indexes or the transforms change.
