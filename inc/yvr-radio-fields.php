<?php
/** Real, attributed Metro Vancouver field recordings. No bundled substitutes. */
if (!defined('ABSPATH')) { exit; }
add_action('init', function() {
    register_post_type('se_radio_field', array('labels'=>array('name'=>'Vancouver recordings','singular_name'=>'Vancouver recording','add_new_item'=>'Add a Vancouver recording'), 'public'=>false, 'show_ui'=>true, 'show_in_menu'=>true, 'supports'=>array('title'), 'capability_type'=>'post', 'capabilities'=>array('create_posts'=>'manage_options','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options','delete_posts'=>'manage_options'), 'map_meta_cap'=>true, 'menu_icon'=>'dashicons-microphone'));
});
add_action('add_meta_boxes', function() {
    add_meta_box('se-radio-field-details','Recording provenance','se_radio_field_box','se_radio_field','normal','high');
});
function se_radio_field_box($post) {
    wp_nonce_field('se_radio_field_save','se_radio_field_nonce');
    echo '<p>Upload your audio in Media first. Use the attachment ID shown in its edit URL (post=123). A published recording appears only when all provenance fields are valid. Coordinates must be in Metro Vancouver.</p>';
    $fields=array('attachment'=>'Audio attachment ID','place'=>'Place / location name','lat'=>'Latitude (49.0 to 49.5)','lon'=>'Longitude (-123.5 to -122.3)','date'=>'Date recorded (YYYY-MM-DD)','credit'=>'Recordist and permission / licence');
    foreach($fields as $key=>$label) {
        echo '<p><label for="yr-field-'.esc_attr($key).'">'.esc_html($label).'</label><br><input class="widefat" id="yr-field-'.esc_attr($key).'" name="se_radio_field['.esc_attr($key).']" value="'.esc_attr(get_post_meta($post->ID,'_yr_'.$key,true)).'"></p>';
    }
    echo '<p><label><input type="checkbox" name="se_radio_field[rights]" value="1" '.checked(get_post_meta($post->ID,'_yr_rights',true),'1',false).'> I recorded this here, or have permission to publish it with the stated credit.</label></p>';
}
add_action('save_post_se_radio_field', function($id) {
    if(wp_is_post_autosave($id)||wp_is_post_revision($id)||!current_user_can('manage_options')||!isset($_POST['se_radio_field_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['se_radio_field_nonce'])),'se_radio_field_save')) {return;}
    $values=isset($_POST['se_radio_field'])&&is_array($_POST['se_radio_field']) ? wp_unslash($_POST['se_radio_field']) : array();
    foreach(array('attachment','place','lat','lon','date','credit','rights') as $key) {update_post_meta($id,'_yr_'.$key,sanitize_text_field(is_scalar($values[$key]??'')?(string)($values[$key]??''):''));}
});
function se_radio_field_channels() {
    $posts=get_posts(array('post_type'=>'se_radio_field','post_status'=>'publish','numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
    $out=array();
    foreach($posts as $post) {
        $id=$post->ID; $attachment=absint(get_post_meta($id,'_yr_attachment',true));
        $lat=get_post_meta($id,'_yr_lat',true);$lon=get_post_meta($id,'_yr_lon',true);
        $date=get_post_meta($id,'_yr_date',true);$credit=get_post_meta($id,'_yr_credit',true);$place=get_post_meta($id,'_yr_place',true);
        if(!is_numeric($lat)||!is_numeric($lon)||(float)$lat<49.0||(float)$lat>49.5||(float)$lon< -123.5||(float)$lon> -122.3) {continue;}
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$parts)||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])||!$credit||!$place||get_post_meta($id,'_yr_rights',true)!=='1') {continue;}
        if(get_post_type($attachment)!=='attachment'||!wp_attachment_is('audio',$attachment)) {continue;}
        $url=se_radio_url(wp_get_attachment_url($attachment));if(!$url) {continue;}
        $out[]=array('key'=>'field_'.$id,'label'=>get_the_title($id),'hint'=>'Recorded in '.$place.' · '.$date,'mode'=>'soundscape','format'=>'audio','stream_url'=>$url,'loop'=>false,'group'=>'Vancouver recordings','region'=>'vancouver','map_lat'=>(float)$lat,'map_lon'=>(float)$lon,'place'=>$place,'recorded_at'=>$date,'credit'=>$credit,'source_url'=>get_attachment_link($attachment));
    }
    return $out;
}
add_action('admin_notices', function() {
    $screen=get_current_screen();
    if(!$screen||$screen->post_type!=='se_radio_field')return;
    echo '<div class="notice notice-info"><p>Only published recordings with an audio attachment, valid Metro Vancouver coordinates, capture date, credit and permission confirmation appear on the map. Purge the radio page cache after publishing.</p></div>';
});
