<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use WP_REST_Request;
use WP_REST_Response;

final class StripeBilling {
    public static function bootstrap(): void { add_action('rest_api_init', [self::class, 'routes']); }

    public static function routes(): void {
        register_rest_route('lousy-outages/v1', '/billing/checkout', ['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'checkout']]);
        register_rest_route('lousy-outages/v1', '/billing/portal', ['methods'=>'POST','permission_callback'=>static fn()=>is_user_logged_in(),'callback'=>[self::class,'portal']]);
        register_rest_route('lousy-outages/v1', '/stripe/webhook', ['methods'=>'POST','permission_callback'=>'__return_true','callback'=>[self::class,'webhook']]);
    }

    private static function request(string $path, array $body) {
        $key = (string)get_option('lousy_outages_stripe_secret_key', '');
        if ($key === '') return new \WP_Error('stripe_unconfigured', 'Billing is not configured.', ['status'=>503]);
        $response = wp_remote_post('https://api.stripe.com/v1/' . ltrim($path, '/'), ['timeout'=>20,'headers'=>['Authorization'=>'Basic '.base64_encode($key.':')],'body'=>$body]);
        if (is_wp_error($response)) return $response;
        $decoded = json_decode((string)wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) >= 300) return new \WP_Error('stripe_error', (string)($decoded['error']['message'] ?? 'Stripe request failed.'), ['status'=>502]);
        return $decoded;
    }

    public static function checkout(WP_REST_Request $request) {
        if (!(bool)get_option('lousy_outages_feature_commerce', true)) return new \WP_Error('billing_disabled', 'Billing is temporarily offline.', ['status'=>503]);
        $plan = Entitlements::normalize_plan((string)$request->get_param('plan'));
        if (!in_array($plan, ['pro','team'], true)) return new \WP_Error('invalid_plan', 'Choose Pro or Team.', ['status'=>400]);
        $price = (string)get_option('lousy_outages_stripe_price_'.$plan, '');
        if ($price === '') return new \WP_Error('price_unconfigured', 'That plan is not ready yet.', ['status'=>503]);
        $user = wp_get_current_user();
        $payload = ['mode'=>'subscription','line_items[0][price]'=>$price,'line_items[0][quantity]'=>1,'client_reference_id'=>(string)$user->ID,'customer_email'=>$user->user_email,'success_url'=>home_url('/lousy-outages/account/?checkout=complete&session_id={CHECKOUT_SESSION_ID}'),'cancel_url'=>home_url('/lousy-outages/pricing/?checkout=cancelled'),'metadata[user_id]'=>(string)$user->ID,'metadata[plan]'=>$plan,'subscription_data[metadata][user_id]'=>(string)$user->ID,'subscription_data[metadata][plan]'=>$plan];
        do_action('lousy_outages_product_event', 'checkout_start', ['user_id'=>$user->ID,'plan'=>$plan]);
        $session = self::request('checkout/sessions', $payload);
        return is_wp_error($session) ? $session : new WP_REST_Response(['url'=>$session['url'] ?? ''], 201);
    }

    public static function portal() {
        $customer = CommerceStore::customer(get_current_user_id());
        if (empty($customer['stripe_customer_id'])) return new \WP_Error('no_customer', 'No billing account found.', ['status'=>404]);
        $session = self::request('billing_portal/sessions', ['customer'=>$customer['stripe_customer_id'],'return_url'=>home_url('/lousy-outages/account/')]);
        return is_wp_error($session) ? $session : new WP_REST_Response(['url'=>$session['url'] ?? ''], 201);
    }

    public static function valid_signature(string $payload, string $header, string $secret, ?int $now=null): bool {
        $parts=[]; foreach (explode(',', $header) as $piece) { [$k,$v]=array_pad(explode('=',trim($piece),2),2,''); $parts[$k][]=$v; }
        $timestamp=(int)($parts['t'][0] ?? 0); $now=$now ?? time();
        if (!$timestamp || abs($now-$timestamp)>300 || empty($parts['v1'])) return false;
        $expected=hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($parts['v1'] as $signature) if (hash_equals($expected, $signature)) return true;
        return false;
    }

    public static function webhook(WP_REST_Request $request) {
        if (!(bool)get_option('lousy_outages_feature_webhooks', true)) return new \WP_Error('webhooks_disabled','Billing webhooks are disabled.',['status'=>503]);
        $payload=$request->get_body(); $secret=(string)get_option('lousy_outages_stripe_webhook_secret',''); $signature=(string)$request->get_header('stripe-signature');
        if ($secret==='' || !self::valid_signature($payload,$signature,$secret)) return new \WP_Error('invalid_signature','Invalid Stripe signature.',['status'=>400]);
        $event=json_decode($payload,true); $id=(string)($event['id']??''); $type=(string)($event['type']??'');
        if ($id==='' || CommerceStore::event_seen($id)) return new WP_REST_Response(['received'=>true,'duplicate'=>$id!=='']);
        self::apply_event($type, (array)($event['data']['object']??[])); CommerceStore::mark_event($id,$type);
        return new WP_REST_Response(['received'=>true]);
    }

    public static function apply_event(string $type, array $object): void {
        $meta=(array)($object['metadata']??[]); $user_id=(int)($meta['user_id']??$object['client_reference_id']??0);
        if ('checkout.session.completed'===$type && $user_id) {
            CommerceStore::upsert_customer($user_id,['stripe_customer_id'=>$object['customer']??null,'stripe_subscription_id'=>$object['subscription']??null,'plan'=>$meta['plan']??'free','subscription_status'=>'active']);
            do_action('lousy_outages_product_event','checkout_complete',['user_id'=>$user_id,'plan'=>$meta['plan']??'free']);
        } elseif (in_array($type,['customer.subscription.created','customer.subscription.updated','customer.subscription.deleted'],true)) {
            if (!$user_id && !empty($object['customer'])) { $user_id=self::user_for_customer((string)$object['customer']); }
            if ($user_id) CommerceStore::upsert_customer($user_id,['stripe_customer_id'=>$object['customer']??null,'stripe_subscription_id'=>$object['id']??null,'plan'=>$meta['plan']??CommerceStore::plan($user_id),'subscription_status'=>$type==='customer.subscription.deleted'?'canceled':($object['status']??'none'),'current_period_end'=>empty($object['current_period_end'])?null:gmdate('Y-m-d H:i:s',(int)$object['current_period_end'])]);
        }
    }

    private static function user_for_customer(string $customer): int { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}lo_customers WHERE stripe_customer_id=%s",$customer)); }
}
