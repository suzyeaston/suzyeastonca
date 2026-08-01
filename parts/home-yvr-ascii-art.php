<?php
/**
 * ASCII Vancouver downtown + harbor for the homepage hero left panel.
 *
 * @return string[]
 */
function home_yvr_ascii_art_lines(): array {
    return [
        '              /\      /\\',
        '             /  \\    /  \\',
        '            /    \\__/    \\',
        '           /  north shore   \\',
        '        [::]|##| |##| |##| |##|',
        '        |##||##||##||##||##||##|',
        '        |##||##||##||##||##||##|',
        '         \\ /\\  /\\  /\\  /\\  sail',
        '        ~~~~~~~~~~~~~~~~~~~~~~~~',
        '         downtown // harbor // YVR',
    ];
}

/**
 * @return string
 */
function home_yvr_ascii_art(): string {
    return implode( "\n", home_yvr_ascii_art_lines() );
}
