                    <div class="home-yvr-broadcaster__feeds">
                        <p class="home-yvr-broadcaster__feeds-label pixel-font"><?php echo esc_html( 'feeds // dave reads' ); ?></p>
                        <div class="home-yvr-broadcaster__channels home-yvr-broadcaster__channels--data">
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="translink" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'SkyTrain' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'TransLink alerts' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="drivers" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'DriveBC' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Road incidents' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="ferries" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'BC Ferries' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Sailings & fill' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="weather" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Weather' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'EC warnings' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="wildfire" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Wildfire' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Coastal status' ); ?></span>
                            </button>
                            <button type="button" class="home-yvr-broadcaster__ch" data-broadcaster-channel-btn="air" aria-pressed="false">
                                <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( 'Air quality' ); ?></span>
                                <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( 'Metro AQHI' ); ?></span>
                            </button>
                        </div>
                        <p class="home-yvr-broadcaster__feeds-label pixel-font"><?php echo esc_html( 'listen // live + beds' ); ?></p>
                        <div class="home-yvr-broadcaster__channels home-yvr-broadcaster__channels--listen">
                            <?php foreach ( se_broadcaster_audio_channel_catalog() as $audio_ch ) : ?>
                                <?php
                                $ch_key   = $audio_ch['key'];
                                $ch_class = 'home-yvr-broadcaster__ch home-yvr-broadcaster__ch--listen';
                                if ( $audio_ch['mode'] === 'link_out' ) {
                                    $ch_class .= ' home-yvr-broadcaster__ch--link';
                                }
                                if ( $audio_ch['mode'] === 'soundscape' ) {
                                    $ch_class .= ' home-yvr-broadcaster__ch--bed';
                                }
                                if ( in_array( $audio_ch['pin_tier'], array( 'hydro', 'marine', 'atc' ), true ) ) {
                                    $ch_class .= ' home-yvr-broadcaster__ch--scan';
                                }
                                ?>
                                <button type="button" class="<?php echo esc_attr( $ch_class ); ?>" data-broadcaster-channel-btn="<?php echo esc_attr( $ch_key ); ?>" aria-pressed="false">
                                    <span class="home-yvr-broadcaster__ch-label pixel-font"><?php echo esc_html( $audio_ch['label'] ); ?></span>
                                    <span class="home-yvr-broadcaster__ch-hint"><?php echo esc_html( $audio_ch['hint'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>