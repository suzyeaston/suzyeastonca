#!/usr/bin/env python3
"""Build genuine source-code images for Meanwhile Part II from a pinned public commit.

The images aren't illustrations invented by AI. They are syntax-highlighted crops
of repository source, with exact source line numbers and a verifiable commit SHA.
No editor screenshots, external screenshot services, or generated code needed.
"""
from __future__ import annotations

from io import BytesIO
from pathlib import Path
from urllib.request import Request, urlopen
from zipfile import ZipFile

from PIL import Image, ImageDraw, ImageFont
from pygments import lex
from pygments.lexers import SwiftLexer, PythonLexer
from pygments.token import Token

ROOT = Path(__file__).resolve().parent.parent
SLUG = "an-orchestra-in-8gb"
OUT = ROOT / "content" / "meanwhile" / "media" / SLUG
PIN = "24512f6469abc6acf26bae8cbfab7fb42d59e5d0"
ORIGIN = "https://raw.githubusercontent.com/suzyeaston/suzy-ai/" + PIN + "/"
PHOTOS = ROOT / "content/meanwhile/media/Suzy_The_Toaster_Was_a_Decoy_11_Photos.zip"

SHOTS = [
    ("01_prompt_mixer.png", "PROMPT-SPACE ORCHESTRATION",
     "apps/toaster-magenta/ToasterMagentaHost.swift", 391, 407, SwiftLexer(), "parameter(10"),
    ("02_transport_truth.png", "THE MISSING MOVING TRANSPORT",
     "apps/toaster-magenta/ToasterMagentaHost.swift", 593, 619, SwiftLexer(), "transportStateBlock"),
    ("03_native_pcm_loop.png", "PRINT AUDIO, NOT MIDI INSTRUCTIONS",
     "apps/toaster-magenta/ToasterMagentaHost.swift", 341, 355, SwiftLexer(), "scheduleBuffer"),
    ("04_browser_is_a_controller.png", "THE LOCALHOST TRUST BOUNDARY",
     "suzy_ai/music/magenta_stage.py", 105, 124, PythonLexer(), "compare_digest"),
]


def get_source(path: str) -> list[str]:
    request = Request(ORIGIN + path, headers={"User-Agent": "SUZY-Meanwhile-code-capture"})
    with urlopen(request, timeout=30) as response:
        return response.read(500000).decode("utf-8").splitlines()


def use_font(size: int, *, bold: bool = False, mono: bool = False) -> ImageFont.FreeTypeFont:
    base = "/usr/share/fonts/truetype/dejavu/"
    name = "DejaVuSansMono" if mono else "DejaVuSans"
    suffix = "-Bold" if bold else ""
    return ImageFont.truetype(base + name + suffix + ".ttf", size)


def token_color(tok) -> tuple[int, int, int]:
    if tok in Token.Comment:
        return (124, 165, 166)
    if tok in Token.Literal.String:
        return (208, 177, 250)
    if tok in Token.Literal.Number:
        return (255, 201, 130)
    if tok in Token.Keyword:
        return (255, 143, 186)
    if tok in Token.Name.Function:
        return (144, 230, 215)
    if tok in Token.Operator:
        return (208, 219, 236)
    return (219, 227, 240)


def draw_shot(filename: str, title: str, src: str, first: int, last: int,
              lexer, evidence: str, source_lines: list[str]) -> None:
    lines = source_lines[first - 1:last]
    body = "\n".join(lines)
    if evidence not in body or len(lines) != last - first + 1:
        raise ValueError(f"Source diverged: {src}:{first}-{last}")

    mono = use_font(20, mono=True)
    small = use_font(17, mono=True)
    heading = use_font(28, bold=True)
    sub = use_font(18)
    width, gap = 1930, 34
    height = 160 + gap * len(lines) + 72
    im = Image.new("RGB", (width, height), (13, 18, 31))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle((22, 20, width - 22, height - 20),
                        radius=24, fill=(25, 32, 49), outline=(84, 81, 116), width=2)
    d.rounded_rectangle((26, 25, width - 26, 129),
                        radius=21, fill=(43, 41, 67))
    for i, color in enumerate([(253, 129, 150), (251, 205, 115), (129, 223, 198)]):
        x = 57 + i * 32
        d.ellipse((x, 66, x + 17, 83), fill=color)
    d.text((184, 48), title, font=heading, fill=(251, 245, 255))
    d.text((184, 94), f"{src}  |  L{first}-{last}", font=sub, fill=(172, 187, 214))
    d.line((106, 146, 106, height - 59), fill=(73, 79, 107), width=2)
    for i, source_line in enumerate(lines):
        y = 159 + i * gap
        d.text((52, y), str(first + i).rjust(3), font=small, fill=(118, 134, 160))
        x = 131.0
        for token, value in lex(source_line, lexer):
            value = value.replace("\n", "")
            d.text((x, y), value, font=mono, fill=token_color(token))
            x += d.textlength(value, font=mono)
    d.text((56, height - 58),
           "SUZY//AI   /   SOURCE EXCERPT   /   COMMIT " + PIN[:12],
           font=small, fill=(139, 192, 194))
    dest = OUT / filename
    im.save(dest, "PNG", optimize=True)
    with Image.open(dest) as check:
        check.verify()
    print(f"Built {dest} from {src}:{first}-{last}", flush=True)


def main():
    if not (ROOT / "content/meanwhile/2026-11-an-orchestra-in-8gb.md").exists():
        print("Part II not present: skipping media generation")
        return
    OUT.mkdir(parents=True, exist_ok=True)
    if not PHOTOS.is_file():
        raise FileNotFoundError(f"Original Futureproof photo archive missing: {PHOTOS}")
    with ZipFile(PHOTOS) as archive:
        original = archive.read("IMG_3557(2).JPG")
        with Image.open(BytesIO(original)) as im:
            if im.width < 600 or im.height < 350:
                raise ValueError("Futureproof hero source photo too small")
            im.convert("RGB").save(OUT / "futureproof-stage.jpg", "JPEG",
                                   quality=87, optimize=True, progressive=True)

    cache = {}
    for name, title, source, first, last, lexer, evidence in SHOTS:
        if source not in cache:
            cache[source] = get_source(source)
        draw_shot(name, title, source, first, last, lexer, evidence, cache[source])
    print(f"Verified 1 performance photo and {len(SHOTS)} genuine code excerpts")


if __name__ == "__main__":
    main()
