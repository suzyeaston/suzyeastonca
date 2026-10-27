#!/usr/bin/env python3
"""Deterministic Facebook/Threads OG card, built from Suzy's October portrait."""
from __future__ import annotations
import math
import random
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "assets/brand/meanwhile-october-portrait.webp"
OUTPUT = ROOT / "assets/brand/what-we-leave-open-social.jpg"
W, H = 1200, 630

def font(paths, size):
    for path in paths:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()

def main():
    random.seed(10272026)
    img = Image.new("RGB", (W, H))
    pix = img.load()
    for y in range(H):
        for x in range(W):
            u, v = x / W, y / H
            bloom = math.exp(-((u-.81)**2/.14 + (v-.33)**2/.22))
            dusk = math.exp(-((v-.84)**2/.012))
            pix[x,y] = (
                int(9 + 38*u + 48*bloom + 35*dusk),
                int(9 + 19*u + 12*bloom + 14*dusk),
                int(32 + 40*u + 60*bloom + 30*dusk),
            )
    img = img.convert("RGBA")
    art = Image.new("RGBA", (W, H), (0,0,0,0))
    draw = ImageDraw.Draw(art, "RGBA")
    for _ in range(180):
        x, y = random.randrange(W), random.randrange(H)
        r = random.choice([1,1,1,2])
        draw.ellipse((x-r,y-r,x+r,y+r), fill=(239,207,249,random.randrange(40,155)))
    draw.ellipse((612,3,1155,546), outline=(243,179,240,115), width=3)
    draw.arc((550,-80,1310,670),192,357,fill=(161,227,238,90),width=2)
    draw.ellipse((668,60,1075,465), fill=(191,106,220,22),outline=(243,190,236,64),width=2)
    for phase, alpha, color in [(0,95,(223,160,237)),(1.2,55,(157,221,239))]:
        pts = [(x,int(472+23*math.sin(x/67+phase)+10*math.sin(x/19+phase))) for x in range(W)]
        draw.line(pts, fill=(*color,alpha), width=2)
    img = Image.alpha_composite(img,art.filter(ImageFilter.GaussianBlur(.55)))

    portrait = Image.open(SOURCE).convert("RGB").resize((468,655),Image.Resampling.LANCZOS)
    mask = Image.new("L",(468,655),0)
    ImageDraw.Draw(mask).rounded_rectangle((9,6,462,647),radius=190,fill=225)
    mask = mask.filter(ImageFilter.GaussianBlur(47))
    portrait = Image.blend(portrait,Image.new("RGB",portrait.size,(161,116,197)),.17)
    img.paste(portrait.convert("RGBA"),(737,-8),mask)

    layer = Image.new("RGBA",(W,H))
    pixels = layer.load()
    for y in range(H):
        for x in range(W):
            pixels[x,y] = (7,8,25,int(222*(max(0,1-x/830)**1.6)))
    img = Image.alpha_composite(img,layer)
    d = ImageDraw.Draw(img, "RGBA")
    serif=font(["/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf"],79)
    italic=font(["/usr/share/fonts/truetype/dejavu/DejaVuSerifCondensed-Italic.ttf"],85)
    sans=font(["/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"],17)
    small=font(["/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"],15)
    d.line((69,66,69,562),fill=(230,198,243,180),width=2)
    d.line((69,562,565,562),fill=(230,198,243,155),width=2)
    d.text((91,80),"M E A N W H I L E     /     2 0 2 6 . 1 0 . 2 7",font=small,fill=(237,207,250))
    d.text((88,180),"What We",font=serif,fill=(250,240,253),stroke_width=1,stroke_fill=(23,14,38))
    d.text((88,290),"Leave Open",font=italic,fill=(250,240,253),stroke_width=1,stroke_fill=(23,14,38))
    d.line((92,431,534,431),fill=(237,188,234,160),width=1)
    d.text((92,459),"on belonging, letting go & finding music",font=sans,fill=(236,223,244))
    d.text((92,526),"SUZY EASTON   ✦   JERICHO BEACH",font=small,fill=(229,206,245))
    OUTPUT.parent.mkdir(parents=True,exist_ok=True)
    img.convert("RGB").save(OUTPUT,"JPEG",quality=86,optimize=True,progressive=True,subsampling=0)
    assert Image.open(OUTPUT).size==(W,H)
    print(f"Built: {OUTPUT}, {OUTPUT.stat().st_size} bytes")

if __name__ == "__main__":
    main()
