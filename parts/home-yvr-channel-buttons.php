                    <div class="home-yvr-broadcaster__feeds">
                        <p class="home-yvr-broadcaster__feeds-label pixel-font"><?php echo esc_html( 'listen // live only' ); ?></p>
                        <div class="home-yvr-broadcaster__channels home-yvr-broadcaster__channels--listen">
                            <?php foreach ( se_broadcaster_audio_channel_catalog() as $audio_ch ) : ?>
                                <?php if ( ! empty( $audio_ch['pin_only'] ) ) {
                                    continue;
                                } ?>
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
                        <p class="home-yvr-broadcaster__feeds-note pixel-font"><?php echo esc_html( 'map pins show bulletins + tune matched live scanners' ); ?></p>
                    </div>
