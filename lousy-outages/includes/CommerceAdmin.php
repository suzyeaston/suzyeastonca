<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages;

final class CommerceAdmin {
    private const OPTIONS=['lousy_outages_stripe_publishable_key','lousy_outages_stripe_secret_key','lousy_outages_stripe_webhook_secret','lousy_outages_stripe_price_pro','lousy_outages_stripe_price_team','lousy_outages_feature_commerce','lousy_outages_feature_webhooks','lousy_outages_feature_private_boards'];
    public static function bootstrap(): void { add_action('admin_menu',[self::class,'menu']); add_action('admin_init',[self::class,'settings']); }
    public static function menu(): void { add_submenu_page('lousy-outages','Plans & Billing','Plans & Billing','manage_options','lousy-outages-billing',[self::class,'page']); }
    public static function settings(): void { foreach(self::OPTIONS as $option) register_setting('lousy_outages_billing',$option,['sanitize_callback'=>str_contains($option,'feature_')?'absint':'sanitize_text_field']); }
    public static function page(): void { if(!current_user_can('manage_options'))return; ?><div class="wrap"><h1>Plans &amp; Billing</h1><p>Stripe keys can also be supplied through deployment tooling. Secret values are stored as WordPress options and should never be committed.</p><form action="options.php" method="post"><?php settings_fields('lousy_outages_billing'); ?><table class="form-table"><?php foreach(self::OPTIONS as $option): $secret=str_contains($option,'secret'); $flag=str_contains($option,'feature_'); ?><tr><th><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html(ucwords(str_replace(['lousy_outages_','_'],' ', $option))); ?></label></th><td><input id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" type="<?php echo $flag?'checkbox':($secret?'password':'text'); ?>" <?php if($flag) checked((int)get_option($option,0),1); ?> value="<?php echo $flag?'1':esc_attr((string)get_option($option,'')); ?>" class="<?php echo $flag?'':'regular-text'; ?>" autocomplete="off"></td></tr><?php endforeach; ?></table><?php submit_button(); ?></form><p><strong>Webhook URL:</strong> <code><?php echo esc_html(rest_url('lousy-outages/v1/stripe/webhook')); ?></code></p></div><?php }
}
