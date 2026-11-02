#!/usr/bin/env python3
"""Build the 1200x630 social thumbnail for The Toaster Was a Decoy.

Uses the original Futureproof photo committed with the November Meanwhile post.
The generated JPEG is uploaded by the existing theme deployment workflow.
"""
from __future__ import annotations
from pathlib import Path
from zipfile import ZipFile
from io import BytesIO
from PIL import Image, ImageDraw, ImageFont, ImageOps
import math

ROOT = Path(__file__).resolve().parent.parent
PHOTO_ZIP = ROOT / "content/meanwhile/media/Suzy_The_Toaster_Was_a_Decoy_11_Photos.zip"
PHOTO_NAME = "IMG_3556(2).JPG"
OUTPUT = ROOT / "assets/brand/toaster-was-a-decoy-social.jpg"
W, H = 1200, 630


def font(paths, size):
    for filename in paths:
        if Path(filename).exists():
            return ImageFont.truetype(filename, size)
    return ImageFont.load_default()


def main():
    with ZipFile(PHOTO_ZIP) as zf:
        if zf.testzip() is not None:
            raise RuntimeError("Corrupted Meanwhile photo archive")
        source = Image.open(BytesIO(zf.read(PHOTO_NAME))).convert("RGB")

    image = Image.new("RGB", (W, H))
    pixels = image.load()
    for y in range(H):
        for x in range(W):
            t = x / W
            flare = math.exp(-((t - .66) ** 2) / .12) * 32
            pixels[x, y] = (
                int(12 + 21 * t + flare * .6),
                int(13 + 8 * t + flare * .25),
                int(29 + 24 * t + flare),
            )

    portrait = ImageOps.fit(
        source, (575, H), method=Image.Resampling.LANCZOS, centering=(.52, .45)
    )
    portrait = Image.blend(
        portrait, Image.new("RGB", portrait.size, (96, 71, 145)), .10
    )
    image.paste(portrait, (625, 0))
    image = image.convert("RGBA")
    overlay = Image.new("RGBA", (W, H))
    overlay_pixels = overlay.load()
    for x in range(W):
        if x < 620:
            continue
        fade = max(0, 1 - (x - 615) / 305)
        for y in range(H):
            overlay_pixels[x, y] = (11, 12, 29, int(235 * fade * fade))
    image = Image.alpha_composite(image, overlay)
    draw = ImageDraw.Draw(image, "RGBA")
    draw.line((64, 60, 64, 564), fill=(165, 211, 223, 180), width=2)
    draw.line((64, 565, 586, 565), fill=(165, 211, 223, 150), width=2)
    draw.ellipse((520, 65, 554, 99), outline=(178, 230, 226, 135), width=2)
    draw.arc((515, 62, 570, 118), 40, 300, fill=(244, 178, 228, 180), width=2)

    small = font(["/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"], 15)
    sans = font(["/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"], 17)
    serif = font(["/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf"], 74)
    italic = font([
        "/usr/share/fonts/truetype/dejavu/DejaVuSerifCondensed-Italic.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf",
    ], 72)

    draw.text((90, 72), "M E A N W H I L E    /    N O V E M B E R   2 0 2 6",
              font=small, fill=(183, 232, 238))
    draw.text((88, 156), "The Toaster", font=serif, fill=(252, 245, 250),
              stroke_width=1, stroke_fill=(27, 18, 47))
    draw.text((88, 262), "Was a Decoy", font=italic, fill=(252, 245, 250),
              stroke_width=1, stroke_fill=(27, 18, 47))
    draw.line((91, 380, 504, 380), fill=(196, 168, 221, 175), width=2)
    draw.text((91, 415), "NO FIXED STUDIO", font=sans, fill=(223, 199, 239))
    draw.text((91, 454), "ONE 8GB ORCHESTRA.", font=sans, fill=(223, 199, 239))
    draw.text((91, 521), "SUZY EASTON   ✦   FUTUREPROOF", font=small,
              fill=(194, 223, 236))

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    image.convert("RGB").save(
        OUTPUT, "JPEG", quality=89, subsampling=0, optimize=True, progressive=True
    )
    with Image.open(OUTPUT) as result:
        if result.size != (W, H) or result.format != "JPEG":
            raise RuntimeError("Unexpected social thumbnail format")
    print(f"Built {OUTPUT}: {OUTPUT.stat().st_size} bytes, 1200x630")


if __name__ == "__main__":
    main()
