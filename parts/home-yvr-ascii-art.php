<?php
/**
 * ASCII Vancouver rain-city scene for the homepage hero left panel.
 *
 * @return string[]
 */
function home_yvr_ascii_art_lines(): array {
    return array(
        '    *  .   RAIN CITY   .  *',
        '      /\\    NORTH SHORE',
        '     /  \\  /\\  /\\  /\\',
        '    |#|#|#|#|#|#|#|#|#|#|',
        '    |#|#|#|#|#|#|#|#|#|#| downtown',
        '     ==SkyTrain==>  compass',
        '   ~~~ harbour ~~~ ferry ~~~',
        '    > YVR // gastown // west coast',
    );
}

/**
 * @return string
 */
function home_yvr_ascii_art(): string {
    return implode( "\n", home_yvr_ascii_art_lines() );
}
