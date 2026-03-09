<!-- Floating Link Bar -->
<div class="floating-link-bar">
    <div class="link-group">
        <a href="https://suzyeaston.bandcamp.com" target="_blank" class="floating-link">
            <span class="icon">🎵</span>
            <span class="label">Bandcamp</span>
        </a>
        <a href="https://soundcloud.com/suzyeaston" target="_blank" class="floating-link">
            <span class="icon">🔊</span>
            <span class="label">SoundCloud</span>
        </a>
        <a href="https://youtube.com/@suzyeaston" target="_blank" class="floating-link">
            <span class="icon">📺</span>
            <span class="label">YouTube</span>
        </a>
        <a href="https://instagram.com/suzyeaston" target="_blank" class="floating-link">
            <span class="icon">📸</span>
            <span class="label">Instagram</span>
        </a>
        <a href="https://twitter.com/suzyeaston" target="_blank" class="floating-link">
            <span class="icon">🐦</span>
            <span class="label">X/Twitter</span>
        </a>
    </div>
</div>

<!-- Simple Footer -->
<footer class="site-footer">
    <div class="footer-content">
        <nav class="footer-site-links" aria-label="Explore creative projects">
            <a href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>">ASMR Lab</a>
            <a href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>">Track Analyzer</a>
            <a href="<?php echo esc_url( home_url( '/riff-generator/' ) ); ?>">Riff Generator</a>
            <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>">Lousy Outages</a>
        </nav>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Suzy Easton. All rights reserved.</p>
        </div>
        <div class="footer-marquee">
            <span>Made in Vancouver &middot; Built with coffee, riffs, and command line confidence.</span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
