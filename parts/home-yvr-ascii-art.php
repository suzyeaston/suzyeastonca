<?php
/**
 * ASCII Vancouver downtown + harbour for the homepage hero left panel.
 *
 * @return string[]
 */
function home_yvr_ascii_art_lines(): array {
    return [
        '       _.---._   _.---._',
        '      / snow  \\ /  rain \\  north shore',
        '     /  caps   X   caps  \\',
        '    |~~|##|##|##|##|##|~~|',
        '    |##||##||##||##||##||##|',
        '    |##||##||##||##||##||##| downtown',
        '     \\=SkyTrain==SkyTrain=/',
        '    ~~~~~~~~~~~~~~~~~~~~~~~~',
        '      \\  sails /  cranes  /',
        '   harbour // burrard inlet // YVR',
    ];
}

/**
 * @return string
 */
function home_yvr_ascii_art(): string {
    return implode( "\n", home_yvr_ascii_art_lines() );
}
