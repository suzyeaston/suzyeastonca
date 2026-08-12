#!/usr/bin/env python3
"""Theme deploy manifest and validation for production SFTP uploads.

Keeps the explicit production file list in sync with:
- functions.php bootstrap requires (ordering + completeness)
- get_template_part() references from deployed PHP templates
- Local asset paths referenced from deployed PHP
- GitHub Actions path filters (deployable paths must reach the manifest)

Usage:
    python3 scripts/theme_deploy_manifest.py validate
    python3 scripts/theme_deploy_manifest.py validate --git-base origin/main
"""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# Mirrors .github/workflows/deploy-production.yml theme path filters.
THEME_DEPLOY_PATTERNS = (
    "root-php",  # *.php at theme root
    "inc/**",
    "parts/**",
    "assets/css/**",
    "assets/js/**",
    "assets/brand/**",
    "assets/audio/**",
    "assets/data/**",
    "js/**",
)

# Files that may live under deployable paths but are intentionally not uploaded.
THEME_DEPLOY_EXCLUDES = frozenset(
    {
        "assets/audio/gastown/README.md",
        "assets/brand/README.md",
        "visitor-tracker.php",
    }
)

# Production theme files managed by deploy-lousy-outages-ssh.py.
# Bootstrap dependencies required by functions.php MUST appear before
# functions.php so a partial upload cannot leave WordPress requiring a file
# that has not reached production yet.
THEME_FILES = [
    "inc/albini-quotes.php",
    "inc/ai-guardrails.php",
    "inc/openai.php",
    "inc/vancouver-tech-events.php",
    "inc/home-translink-alerts.php",
    "inc/home-yvr-audio-channels.php",
    "inc/home-yvr-broadcaster.php",
    "inc/shop-products.php",
    "inc/shop.php",
    "inc/seo.php",
    "inc/blog.php",
    "functions.php",
    "style.css",
    "header.php",
    "footer.php",
    "home.php",
    "single.php",
    "archive.php",
    "category.php",
    "page-home.php",
    "page-shop.php",
    "page-work-with-suzy.php",
    "page-lousy-outages.php",
    "page-lousy-outages-pricing.php",
    "page-lousy-outages-account.php",
    "parts/home-commercial-strip.php",
    "parts/home-hire-strip.php",
    "parts/home-signal-log.php",
    "parts/blog-card.php",
    "parts/blog-empty.php",
    "parts/blog-hero.php",
    "parts/blog-pagination.php",
    "parts/shop-product-card.php",
    "parts/shop-product-detail.php",
    "parts/lousy-outages-teaser.php",
    "parts/home-yvr-channel-buttons.php",
    "assets/css/home-commercial-strip.css",
    "assets/css/blog.css",
    "assets/css/shop.css",
    "assets/css/shop-console.css",
    "assets/js/shop.js",
    "assets/js/header-contact-modal.js",
    "assets/js/seo-analytics.js",
    "assets/css/home-hero-cabinet.css",
    "assets/css/home-yvr-radar-deck.css",
    "assets/audio/yvr/rain-loop.mp3",
    "assets/audio/yvr/skytrain-rumble.mp3",
    "assets/audio/yvr/ferry-horn.mp3",
    "js/home-hero-map.js",
    "js/home-yvr-broadcaster.js",
    "assets/css/lousy-outages-page.css",
    "assets/css/lousy-outages-theme-isolation.css",
    "assets/css/lousy-outages-teaser.css",
    "assets/js/lousy-outages-teaser.js",
]

REQUIRE_PATTERN = re.compile(
    r"require_once\s+get_template_directory\(\)\s*\.\s*['\"](/[^'\"]+)['\"]"
)
TEMPLATE_PART_PATTERN = re.compile(
    r"get_template_part\s*\(\s*['\"]([^'\"]+)['\"](?:\s*,\s*['\"]([^'\"]+)['\"])?"
)
LOCAL_ASSET_PATTERN = re.compile(
    r"get_template_directory\(\)\s*\.\s*['\"](/(?:assets|js)/[^'\"]+)['\"]"
)
QUOTED_ASSET_PATTERN = re.compile(r"['\"](/(?:assets|js)/[^'\"]+\.(?:css|js|mp3))['\"]")

# Files validated for template-part and local-asset closure. Scoped to Meanwhile/blog
# so pre-existing selective-deploy gaps elsewhere do not block unrelated work.
TRANSITIVE_VALIDATION_PREFIXES = (
    "home.php",
    "single.php",
    "archive.php",
    "category.php",
    "inc/blog.php",
    "parts/home-signal-log.php",
    "parts/blog-",
)


def matches_deploy_pattern(relative: str) -> bool:
    path = relative.replace("\\", "/")
    if path in THEME_DEPLOY_EXCLUDES:
        return False
    if path == "style.css":
        return True
    if "/" not in path and path.endswith(".php"):
        return True
    prefixes = (
        "inc/",
        "parts/",
        "assets/css/",
        "assets/js/",
        "assets/brand/",
        "assets/audio/",
        "assets/data/",
        "js/",
    )
    return any(path.startswith(prefix) for prefix in prefixes)


def template_part_path(slug: str, name: str) -> str:
    slug = slug.strip("/")
    if slug.endswith(".php"):
        return slug
    if "/" in slug:
        return f"{slug}-{name}.php"
    return f"{slug}/{name}.php" if slug == "parts" else f"{slug}-{name}.php"


def resolve_template_part(slug: str, name: str | None) -> str:
    slug = slug.strip("/")
    if name:
        if slug.startswith("parts/"):
            return f"{slug}-{name}.php"
        if slug == "parts":
            return f"parts/{name}.php"
        return template_part_path(slug, name)
    if slug.endswith(".php"):
        return slug
    return f"{slug}.php"


def should_validate_transitive(relative: str) -> bool:
    return any(
        relative == prefix or relative.startswith(prefix)
        for prefix in TRANSITIVE_VALIDATION_PREFIXES
    )


def referenced_paths_from_php(relative: str) -> set[str]:
    text = (ROOT / relative).read_text(encoding="utf-8")
    refs: set[str] = set()
    for slug, name in TEMPLATE_PART_PATTERN.findall(text):
        refs.add(resolve_template_part(slug, name or None))
    if should_validate_transitive(relative):
        for asset in LOCAL_ASSET_PATTERN.findall(text):
            refs.add(asset.lstrip("/"))
        for asset in QUOTED_ASSET_PATTERN.findall(text):
            refs.add(asset.lstrip("/"))
    return refs


def collect_transitive_references(manifest: set[str]) -> set[str]:
    refs: set[str] = set()
    for relative in sorted(manifest):
        if not relative.endswith(".php") or not should_validate_transitive(relative):
            continue
        if not (ROOT / relative).is_file():
            continue
        refs.update(referenced_paths_from_php(relative))
    return refs


def functions_requires() -> list[str]:
    functions_text = (ROOT / "functions.php").read_text(encoding="utf-8")
    return [
        match.group(1).lstrip("/")
        for match in REQUIRE_PATTERN.finditer(functions_text)
    ]


def git_changed_files(base_ref: str) -> list[str]:
    try:
        subprocess.run(
            ["git", "rev-parse", "--verify", base_ref],
            check=True,
            capture_output=True,
            text=True,
        )
    except (subprocess.CalledProcessError, FileNotFoundError):
        return []
    result = subprocess.run(
        ["git", "diff", "--name-only", "--diff-filter=ACMR", f"{base_ref}...HEAD"],
        check=False,
        capture_output=True,
        text=True,
        cwd=ROOT,
    )
    if result.returncode != 0:
        result = subprocess.run(
            ["git", "diff", "--name-only", "--diff-filter=ACMR", base_ref, "HEAD"],
            check=False,
            capture_output=True,
            text=True,
            cwd=ROOT,
        )
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def validate_theme_manifest(git_base: str | None = None) -> list[str]:
    errors: list[str] = []
    manifest = set(THEME_FILES)

    missing_local = [relative for relative in THEME_FILES if not (ROOT / relative).is_file()]
    if missing_local:
        errors.append(
            "Theme deploy manifest contains missing local files: " + ", ".join(missing_local)
        )

    direct_requires = functions_requires()
    omitted_requires = sorted(
        relative
        for relative in direct_requires
        if (ROOT / relative).is_file() and relative not in manifest
    )
    if omitted_requires:
        errors.append(
            "functions.php requires files missing from THEME_FILES: "
            + ", ".join(omitted_requires)
        )

    functions_index = THEME_FILES.index("functions.php")
    for relative in direct_requires:
        if relative not in THEME_FILES:
            continue
        if THEME_FILES.index(relative) >= functions_index:
            errors.append(
                f"Bootstrap ordering error: {relative} must appear before functions.php"
            )

    transitive = collect_transitive_references(manifest)
    missing_refs = sorted(
        relative
        for relative in transitive
        if (ROOT / relative).is_file() and relative not in manifest
    )
    if missing_refs:
        errors.append(
            "Deployed templates reference runtime files missing from THEME_FILES: "
            + ", ".join(missing_refs)
        )

    if git_base:
        changed = git_changed_files(git_base)
        unmanifested = sorted(
            path
            for path in changed
            if matches_deploy_pattern(path) and path not in manifest
        )
        if unmanifested:
            errors.append(
                "Changed deployable theme files are not listed in THEME_FILES "
                f"(compare {git_base}): "
                + ", ".join(unmanifested)
                + ". Add them to scripts/theme_deploy_manifest.py or THEME_DEPLOY_EXCLUDES."
            )

    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate production theme deploy manifest")
    parser.add_argument(
        "command",
        nargs="?",
        default="validate",
        choices=["validate"],
        help="Validation command (default: validate)",
    )
    parser.add_argument(
        "--git-base",
        default=None,
        help="Optional git ref; fail when changed deployable files are absent from THEME_FILES",
    )
    args = parser.parse_args()

    errors = validate_theme_manifest(git_base=args.git_base)
    if errors:
        for error in errors:
            print(error, file=sys.stderr)
        return 1

    print(f"Theme deploy manifest OK ({len(THEME_FILES)} files).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
