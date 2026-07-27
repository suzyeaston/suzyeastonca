<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use WP_REST_Request;
use WP_REST_Response;

final class Product {
    public static function bootstrap(): void {
        add_action('rest_api_init',[self::class,'routes']);
        add_action('template_redirect',[self::class,'consume_magic_link']);
        add_shortcode('lousy_outages_pricing',[self::class,'pricing']);
        add_shortcode('lousy_outages_account',[self::class,'account']);
        add_action('wp_footer',[self::class,'browser_events']);
    }

    public static function install_pages(): void {
        $parent=get_page_by_path('lousy-outages');
        foreach(['pricing'=>['Lousy Outages Pricing','page-lousy-outages-pricing.php'],'account'=>['Lousy Outages Account','page-lousy-outages-account.php']] as $slug=>$config) {
            if(get_page_by_path('lousy-outages/'.$slug)) continue;
            $id=wp_insert_post(['post_title'=>$config[0],'post_name'=>$slug,'post_type'=>'page','post_status'=>'publish','post_parent'=>$parent ? (int)$parent->ID : 0]);
            if(!is_wp_error($id)) update_post_meta((int)$id,'_wp_page_template',$config[1]);
        }
    }

    public static function routes(): void {
        register_rest_route('lousy-outages/v1','/account/magic-link',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>[self::class,'magic_link']]);
        register_rest_route('lousy-outages/v1','/watchlists',['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'save_watchlist']]);
        register_rest_route('lousy-outages/v1','/alert-destinations',['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'save_destination']]);
        register_rest_route('lousy-outages/v1','/api-tokens',['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'create_api_token']]);
    }

    public static function require_feature(int $user_id,string $feature) {
        $plan=CommerceStore::plan($user_id);
        if($feature==='private_board' && !(bool)get_option('lousy_outages_feature_private_boards',false)) return new \WP_Error('feature_disabled','Private boards are not in this rollout yet.',['status'=>503,'feature'=>$feature]);
        return Entitlements::allows($plan,$feature) ? true : new \WP_Error('upgrade_required','This feature needs a paid plan.',['status'=>403,'plan'=>$plan,'feature'=>$feature]);
    }

    public static function save_watchlist(WP_REST_Request $request) {
        $user_id=get_current_user_id(); $allowed=self::require_feature($user_id,'watchlists'); if (is_wp_error($allowed)) return $allowed;
        global $wpdb; $providers=array_values(array_filter(array_map('sanitize_key',(array)$request->get_param('providers'))));
        $filters=(array)$request->get_param('filters');
        if (!Entitlements::allows(CommerceStore::plan($user_id),'advanced_filters')) $filters=[];
        $wpdb->insert($wpdb->prefix.'lo_watchlists',['owner_user_id'=>$user_id,'name'=>sanitize_text_field((string)$request->get_param('name')),'providers'=>wp_json_encode($providers),'filters'=>wp_json_encode($filters),'digest_preferences'=>wp_json_encode((array)$request->get_param('digest')),'is_shared'=>0,'created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)]);
        do_action('lousy_outages_product_event','watchlist_saved',['user_id'=>$user_id,'watchlist_id'=>(int)$wpdb->insert_id]);
        return new WP_REST_Response(['id'=>(int)$wpdb->insert_id],201);
    }

    public static function create_api_token(WP_REST_Request $request) {
        $user_id=get_current_user_id(); $allowed=self::require_feature($user_id,'api_tokens'); if (is_wp_error($allowed)) return $allowed;
        global $wpdb; $plain='lo_'.bin2hex(random_bytes(24)); $prefix=substr($plain,0,12);
        $wpdb->insert($wpdb->prefix.'lo_api_tokens',['owner_user_id'=>$user_id,'name'=>sanitize_text_field((string)$request->get_param('name')) ?: 'API token','token_prefix'=>$prefix,'token_hash'=>password_hash($plain,PASSWORD_DEFAULT),'created_at'=>current_time('mysql',true)]);
        return new WP_REST_Response(['id'=>(int)$wpdb->insert_id,'token'=>$plain,'notice'=>'Copy this token now. It will not be shown again.'],201);
    }

    public static function save_destination(WP_REST_Request $request) {
        $user_id=get_current_user_id(); $plan=CommerceStore::plan($user_id); $allowed=self::require_feature($user_id,'alert_destination'); if(is_wp_error($allowed)) return $allowed;
        $type=sanitize_key((string)$request->get_param('type')); $endpoint=esc_url_raw((string)$request->get_param('endpoint'));
        if(!in_array($type,['slack','webhook'],true) || !wp_http_validate_url($endpoint)) return new \WP_Error('invalid_destination','Use a valid HTTPS Slack or webhook URL.',['status'=>400]);
        global $wpdb; $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}lo_alert_destinations WHERE owner_user_id=%d",$user_id));
        if($count>=Entitlements::destination_limit($plan)) return new \WP_Error('destination_limit','This plan has reached its destination limit.',['status'=>403]);
        $encrypted=self::encrypt_secret($endpoint);
        $wpdb->insert($wpdb->prefix.'lo_alert_destinations',['owner_user_id'=>$user_id,'type'=>$type,'label'=>sanitize_text_field((string)$request->get_param('label')) ?: ucfirst($type),'endpoint_encrypted'=>$encrypted,'enabled'=>1,'created_at'=>current_time('mysql',true)]);
        return new WP_REST_Response(['id'=>(int)$wpdb->insert_id],201);
    }

    private static function encrypt_secret(string $value): string {
        $key=hash('sha256',wp_salt('auth'),true); $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false) return '';
        return base64_encode($iv.$tag.$cipher);
    }

    public static function magic_link(WP_REST_Request $request) {
        $email=sanitize_email((string)$request->get_param('email'));
        if (!is_email($email)) return new \WP_Error('invalid_email','Enter a real email address.',['status'=>400]);
        $user=get_user_by('email',$email);
        if (!$user) { $id=wp_create_user($email,wp_generate_password(32,true,true),$email); if (!is_wp_error($id)) $user=get_user_by('id',$id); }
        if ($user) {
            $token=bin2hex(random_bytes(24)); set_transient('lo_magic_'.hash('sha256',$token),['user_id'=>(int)$user->ID,'redirect'=>home_url('/lousy-outages/account/')],15*MINUTE_IN_SECONDS);
            wp_mail($email,'Your Lousy Outages sign-in link','One click. No password nonsense.\n\n'.add_query_arg(['lo_magic'=>$token],home_url('/lousy-outages/account/')));
        }
        return new WP_REST_Response(['message'=>'If that address can sign in, the link is on its way.']);
    }

    public static function consume_magic_link(): void {
        if (empty($_GET['lo_magic'])) return; $token=sanitize_text_field(wp_unslash($_GET['lo_magic'])); $key='lo_magic_'.hash('sha256',$token); $record=get_transient($key);
        if (!is_array($record) || empty($record['user_id'])) return;
        delete_transient($key); wp_set_auth_cookie((int)$record['user_id'],true,is_ssl()); wp_safe_redirect((string)$record['redirect']); exit;
    }

    public static function pricing(): string {
        do_action('lousy_outages_product_event','plan_page_view',['user_id'=>get_current_user_id()]);
        $plans=[
            'Free'=>['$0','Public dashboard, recent history, RSS and basic email. The useful part stays free.'],
            'Pro'=>['paid monthly','Saved watchlists, component filters, digests and one Slack or webhook destination.'],
            'Team'=>['paid monthly','Shared boards, more destinations, API tokens and a private-board scaffold.'],
        ];
        ob_start(); ?><div class="lo-product"><header><p class="lo-product__kicker">pick your level</p><h1>Outages, with fewer surprises.</h1><p>The public dashboard stays public. Pay when the machine needs to remember things for you.</p></header><div class="lo-price-grid"><?php foreach($plans as $name=>$copy): $slug=strtolower($name); ?><article class="lo-price-card"><h2><?php echo esc_html($name); ?></h2><strong><?php echo esc_html($copy[0]); ?></strong><p><?php echo esc_html($copy[1]); ?></p><?php if($slug==='free'): ?><a class="lo-product-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">Check the dashboard</a><?php else: ?><button class="lo-product-button" data-lo-checkout="<?php echo esc_attr($slug); ?>">Choose <?php echo esc_html($name); ?></button><?php endif; ?></article><?php endforeach; ?></div>
        <div class="lo-comparison"><table><thead><tr><th>Feature</th><th>Free</th><th>Pro</th><th>Team</th></tr></thead><tbody><?php foreach(['Public dashboard / history / RSS'=>[1,1,1],'Basic email subscription'=>[1,1,1],'Saved watchlists + filters'=>[0,1,1],'Digest preferences'=>[0,1,1],'Alert destinations'=>[0,'1','many'],'Shared boards'=>[0,0,1],'API tokens'=>[0,0,1],'Private board scaffold'=>[0,0,1]] as $feature=>$levels): ?><tr><th><?php echo esc_html($feature); ?></th><?php foreach($levels as $value): ?><td><?php echo esc_html($value===1?'yes':($value===0?'—':$value)); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div><?php return (string)ob_get_clean();
    }

    public static function account(): string {
        if (!is_user_logged_in()) return '<div class="lo-product lo-account"><h1>Your outage controls.</h1><p>No password maze. We’ll email a one-use sign-in link.</p><form data-lo-magic><label>Email <input type="email" name="email" required></label><button class="lo-product-button">Send the link</button><p data-lo-message></p></form></div>';
        $plan=CommerceStore::plan(get_current_user_id());
        return '<div class="lo-product lo-account"><p class="lo-product__kicker">account console</p><h1>'.esc_html(ucfirst($plan)).' plan</h1><p>'.($plan==='free'?'The public signal is yours. Watchlists start on Pro.':'Your paid controls are switched on.').'</p><p><a class="lo-product-button" data-lo-upgrade href="'.esc_url(home_url('/lousy-outages/pricing/')).'">'.($plan==='free'?'See paid plans':'Change plan').'</a> <button class="lo-product-button" data-lo-portal>Manage billing</button></p></div>';
    }

    public static function browser_events(): void {
        ?><script>(function(){function event(n,d){window.dispatchEvent(new CustomEvent('lousy-outages:event',{detail:Object.assign({event:n},d||{})}));if(window.dataLayer)window.dataLayer.push(Object.assign({event:'lo_'+n},d||{}));}window.addEventListener('lousy-outages:event',function(e){var d=e.detail||{};if(window.dataLayer&&d.event)window.dataLayer.push(Object.assign({},d,{event:'lo_'+d.event}));});
        document.addEventListener('click',async function(e){var b=e.target.closest('[data-lo-checkout]');if(b){event('upgrade_click',{plan:b.dataset.loCheckout});if(!<?php echo is_user_logged_in()?'true':'false'; ?>){location.href='<?php echo esc_url_raw(home_url('/lousy-outages/account/')); ?>';return;}b.disabled=true;var r=await fetch('<?php echo esc_url_raw(rest_url('lousy-outages/v1/billing/checkout')); ?>',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'},body:JSON.stringify({plan:b.dataset.loCheckout})});var j=await r.json();if(j.url)location.href=j.url;else{b.disabled=false;alert(j.message||'Billing is taking a nap.');}}var p=e.target.closest('[data-lo-portal]');if(p){var r=await fetch('<?php echo esc_url_raw(rest_url('lousy-outages/v1/billing/portal')); ?>',{method:'POST',headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'}});var j=await r.json();if(j.url)location.href=j.url;}if(e.target.closest('[data-lo-upgrade]'))event('upgrade_click',{placement:'account'});});
        document.addEventListener('submit',async function(e){var f=e.target.closest('[data-lo-magic]');if(!f)return;e.preventDefault();var r=await fetch('<?php echo esc_url_raw(rest_url('lousy-outages/v1/account/magic-link')); ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:f.email.value})});var j=await r.json();f.querySelector('[data-lo-message]').textContent=j.message||'Check your inbox.';});})();</script><?php
    }
}
