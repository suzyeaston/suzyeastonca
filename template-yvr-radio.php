<?php
/* Template Name: YVR Radio */
get_header();
?>
<main class="yr" id="yvr-radio">
    <header class="yr-intro"><p class="yr-eyebrow">VANCOUVER / OPEN FREQUENCIES</p><h1>YVR RADIO<span>Listen to the city.</span></h1><p>Community stations, airspace chatter, real Vancouver field recordings and nearby underwater worlds. Pick a signal. Stay a while.</p><a href="<?php echo esc_url(home_url('/')); ?>">← Back to Suzy’s lab</a></header>
    <noscript><p>This player needs JavaScript. Listen directly at <a href="https://player.citr.ca/">CiTR</a>, <a href="https://coopradio.org/">Co-op Radio</a> or <a href="https://www.cjsf.ca/">CJSF</a>.</p></noscript>
    <section class="yr-map-panel" aria-labelledby="yr-map-title">
        <div class="yr-map-header"><div><p class="yr-eyebrow">LISTENING POINTS / WEST COAST</p><h2 id="yr-map-title">Find the sound on the map.</h2></div><div class="yr-map-scopes" role="group" aria-label="Map region"><button data-radio-region="vancouver" aria-pressed="true">Vancouver</button><button data-radio-region="salish" aria-pressed="false">Salish Sea</button></div></div>
        <p>Tap a pin, then choose its station. Radio pins mark studio areas or listening areas, not transmitter coverage. Recorded sounds show where they were captured.</p>
        <div id="yr-map" role="region" aria-label="Interactive listening map"></div><p id="yr-map-status" role="status">Loading the listening map…</p>
        <p class="yr-map-legend">● Radio &nbsp; ◆ Scanners &nbsp; ◉ Hydrophones &nbsp; ★ Vancouver recordings</p>
        <p>Map data © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · <a href="https://openfreemap.org/">OpenFreeMap</a> · <a href="https://openmaptiles.org/">OpenMapTiles</a></p>
    </section>
    <div class="yr-layout">
        <section class="yr-library" aria-labelledby="yr-library-title"><h2 id="yr-library-title">Find your frequency</h2>
            <label for="yr-search">Search stations and sounds</label><input id="yr-search" type="search" placeholder="Try UBC, harbour, aviation…">
            <label for="yr-category">Browse</label><select id="yr-category"><option value="">All signals</option><option>Community radio</option><option>Radio</option><option>Scanners</option><option>Hydrophones</option><option>Vancouver recordings</option><option>Our station</option><option>Favourites</option></select>
            <p id="yr-count" role="status"></p><div id="yr-stations"></div>
        </section>
        <div class="yr-right"><section class="yr-deck" aria-labelledby="yr-title">
            <p class="yr-eyebrow">YOUR RECEIVER</p><p id="yr-status" role="status" aria-live="polite" data-state="idle">READY · Pick a station</p>
            <h2 id="yr-title">A whole city in your headphones.</h2><p id="yr-description">Nothing plays until you choose a signal.</p>
            <div class="yr-controls"><button id="yr-play" disabled>Play</button><button id="yr-stop" disabled>Stop</button><button id="yr-retry" hidden>Retry stream</button></div>
            <label for="yr-volume">Volume <output id="yr-volume-value">80%</output></label><input id="yr-volume" type="range" min="0" max="1" step="0.05" value="0.8">
            <p id="yr-origin"></p><p id="yr-station-health" role="status"></p><p id="yr-note">Live streams have no fixed duration. Playback status appears above.</p><p><a id="yr-source" hidden target="_blank" rel="noopener noreferrer">Open station’s official player ↗</a></p>
            <p><a id="yr-license" hidden target="_blank" rel="noopener noreferrer">Recording licence ↗</a></p>
            <button id="yr-share" disabled>Copy station link</button><p id="yr-share-result" role="status"></p>
            <details><summary>Connection details</summary><pre id="yr-diagnostics">No stream selected.</pre><p>“Playing” means the browser is receiving playback events. It does not prove the source contains audible sound.</p></details>
            <audio id="yr-audio" preload="none" playsinline></audio>
        </section>
        <section class="yr-community"><p class="yr-eyebrow">BUILD THE NEXT FREQUENCY</p><h2>Suzy Pirate Radio.</h2>
            <?php if (se_radio_url(get_option('se_radio_stream',''))) : ?><p>The community stream is in the station list. Tune in when someone is broadcasting.</p><?php else : ?><p>Our own internet station for live sets, field sounds and conversations. The transmitter is built; its public stream is not connected yet.</p><?php endif; ?>
            <p>Have a station to add, a sound to share or an idea for a show? Leave a message below.</p><a href="#yr-messages">Send a signal ↓</a>
            <?php $room=se_radio_url(get_option('se_radio_room','')); if($room) : ?><p><a href="<?php echo esc_url($room); ?>" target="_blank" rel="noopener noreferrer">Open community room ↗</a></p><?php endif; ?>
        </section></div>
    </div>
    <section class="yr-field-note"><h2>Vancouver, recorded.</h2><p>Jericho waves. A real SkyTrain ride. Rain outside the studio. Recordings here need an actual place, capture date and credit.</p><p>Start with the actual Gastown steam clock: a credited 2012 archive recording. Our own local recordings will join it as ★ pins when they are uploaded and published.</p><?php if(current_user_can('manage_options')) : ?><a href="<?php echo esc_url(admin_url('edit.php?post_type=se_radio_field')); ?>">Manage Vancouver recordings ↗</a><?php endif; ?></section>
    <section class="yr-messages" id="yr-messages"><h2>Signals back</h2><p>A moderated message board. Approved messages and display names are public. This does not transmit on radio frequencies.</p>
    <?php while(have_posts()) : the_post();
        $messages=get_comments(array('post_id'=>get_the_ID(),'status'=>'approve','number'=>30,'order'=>'DESC','type'=>'comment'));
        foreach($messages as $message) { echo '<article><strong>'.esc_html($message->comment_author).'</strong><p>'.nl2br(esc_html($message->comment_content)).'</p></article>'; }
        if(is_user_logged_in()) { comment_form(array('title_reply'=>'Leave a signal','label_submit'=>'Submit for moderation','comment_notes_after'=>'<p>Your message will appear after approval.</p>')); }
        else { echo '<p><a href="'.esc_url(wp_login_url(get_permalink().'#yr-messages')).'">Sign in to leave a message</a></p>'; }
    endwhile; ?>
    </section>
</main>
<?php get_footer(); ?>
