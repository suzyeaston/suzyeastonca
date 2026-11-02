#!/usr/bin/env python3
"""Verify the published Meanwhile article exposes a real, fetchable OG JPEG."""
from __future__ import annotations

from html.parser import HTMLParser
from io import BytesIO
from urllib.parse import urljoin, urlsplit
from urllib.request import Request, urlopen
from PIL import Image
import sys
import time

ARTICLE = "https://www.suzyeaston.ca/2026/11/02/the-toaster-was-a-decoy/"
EXPECTED_BASENAME = "toaster-was-a-decoy-social.jpg"


class MetaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.meta = {}

    def handle_starttag(self, tag, attrs):
        if tag != "meta":
            return
        values = dict(attrs)
        key = values.get("property", values.get("name", ""))
        if key:
            self.meta[key] = values.get("content", "")


def get(url: str, accept: str, timeout: int = 20):
    headers = {
        "User-Agent": "Mozilla/5.0 (compatible; MeanwhileSocialPreviewCheck/1.0)",
        "Accept": accept,
    }
    with urlopen(Request(url, headers=headers), timeout=timeout) as response:
        data = response.read(8 * 1024 * 1024)
        return response.status, response.headers.get_content_type(), data


def main():
    for attempt in range(1, 6):
        try:
            status, content_type, document = get(
                ARTICLE + "?social_preview_check=" + str(int(time.time())),
                "text/html",
            )
            if status != 200:
                raise RuntimeError(f"Article returned HTTP {status}")
            parser = MetaParser()
            parser.feed(document.decode("utf-8", errors="replace"))
            image_url = parser.meta.get("og:image", "")
            if not image_url:
                raise RuntimeError("Missing og:image")
            absolute = urljoin(ARTICLE, image_url)
            if urlsplit(absolute).path.split("/")[-1] != EXPECTED_BASENAME:
                raise RuntimeError(f"Unexpected og:image URL: {absolute}")
            if parser.meta.get("og:image:width") != "1200":
                raise RuntimeError("Missing or wrong og:image:width")
            if parser.meta.get("og:image:height") != "630":
                raise RuntimeError("Missing or wrong og:image:height")
            status, content_type, raw = get(absolute, "image/jpeg")
            if status != 200:
                raise RuntimeError(f"Preview image returned HTTP {status}")
            with Image.open(BytesIO(raw)) as im:
                if im.format != "JPEG" or im.size != (1200, 630):
                    raise RuntimeError(f"Unexpected image: {im.format}, {im.size}")
                im.verify()
            print(f"Verified live social preview: {absolute} ({len(raw)} bytes, 1200x630 JPEG)")
            return
        except Exception as exc:
            if attempt == 5:
                print(f"FAIL: social preview not verifiable after 5 attempts: {exc}", file=sys.stderr)
                raise SystemExit(1)
            print(f"Attempt {attempt} waiting for cache: {exc}", flush=True)
            time.sleep(5)


if __name__ == "__main__":
    main()
