<?php
/** Homepage spotlight for the live browser instrument. */
?>
<section class="home-appliance" aria-labelledby="home-appliance-title">
    <div class="home-appliance__copy">
        <p class="home-appliance__eyebrow">NOW PLAYING // BROWSER INSTRUMENT</p>
        <h2 id="home-appliance-title">APPLIANCE<br>LATENT SPACE</h2>
        <p class="home-appliance__lead">Write a loop. Bend the sound. Watch it burn.</p>
        <p class="home-appliance__description">A playable experiment in code, sound and visuals. Bass, tone, percussion. Built in Vancouver. Bring headphones.</p>
        <div class="home-appliance__actions">
            <a class="home-appliance__play" href="<?php echo esc_url( home_url( '/appliance-latent-space/' ) ); ?>">PLAY THE INSTRUMENT <span aria-hidden="true">↗</span></a>
            <a class="home-appliance__source" href="https://github.com/suzyeaston/appliance-latent-space-live">View the source</a>
        </div>
        <p class="home-appliance__basecamp">On the workbench for <a href="https://basecampyvr.ca/">Basecamp, Vancouver</a>. Browser now. Appliance next.</p>
    </div>
    <div class="home-appliance__art" aria-hidden="true">
        <span class="home-appliance__art-label">SOUND / CODE / HEAT</span>
        <svg viewBox="0 0 400 220" focusable="false">
            <g fill="none" stroke="#866aad" stroke-width="1" opacity=".45">
                <ellipse cx="200" cy="110" rx="176" ry="66"/>
                <ellipse cx="200" cy="110" rx="150" ry="88" transform="rotate(-24 200 110)"/>
                <ellipse cx="200" cy="110" rx="150" ry="88" transform="rotate(24 200 110)"/>
                <path d="M24 110H376M200 22V198"/>
            </g>
            <path d="M22 111H66L77 92L89 137L103 70L118 155L137 48L155 171L177 88L195 123L216 62L235 152L253 81L273 136L290 94L306 118H378" fill="none" stroke="#85f5e0" stroke-width="3" stroke-linejoin="round"/>
            <circle cx="200" cy="110" r="100" fill="none" stroke="#d6afff" stroke-width="1" stroke-dasharray="2 8"/>
        </svg>
        <span class="home-appliance__art-footer">01 — AN INSTRUMENT IN PROGRESS</span>
    </div>
</section>
