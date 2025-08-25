<?php
/*
Plugin Name: Smart UPI Final 9
Description: Final UPI gateway (Final 5) — desktop QR, mobile deep-link, 30s->retry->30s->manual flow, auto-detect mobile payment confirmation.
Version:   9.0
Author: Assistant
Text Domain: supi-final5
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('SUPI5_DIR', plugin_dir_path(__FILE__));
define('SUPI5_URL', plugin_dir_url(__FILE__));

// Load gateway after WooCommerce is available
add_action('plugins_loaded', 'supi5_init', 11);
function supi5_init(){
    if ( ! class_exists('WC_Payment_Gateway') ) return;
    require_once SUPI5_DIR . 'includes/class-supi5-gateway.php';
    add_filter('woocommerce_payment_gateways', function($methods){
        $methods[] = 'WC_Gateway_Supi5';
        return $methods;
    });
}

// Enqueue assets on checkout and order received
add_action('wp_enqueue_scripts', function(){
    if ( ! function_exists('is_checkout') ) return;
    if ( is_checkout() || is_order_received_page() ) {
        wp_enqueue_style('supi5-style', SUPI5_URL . 'assets/css/style.css', array(), '5.0');
        wp_enqueue_script('supi5-script', SUPI5_URL . 'assets/js/script.js', array('jquery'), '5.0', true);
        wp_localize_script('supi5-script', 'supi5', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw( get_rest_url(null, 'supi5/v1/') ),
            'nonce'   => wp_create_nonce('supi5_nonce'),
            'siteUrl' => home_url(),
        ));
    }
});

// REST endpoints: confirm and check-order
add_action('rest_api_init', function(){
    register_rest_route('supi5/v1', '/confirm', array(
        'methods' => 'POST',
        'callback' => 'supi5_rest_confirm',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('supi5/v1', '/check-order/(?P<order_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'supi5_rest_check',
        'permission_callback' => '__return_true',
    ));
});

function supi5_rest_confirm( WP_REST_Request $req ){
    $body = $req->get_json_params();
    if ( empty($body) || empty($body['order_id']) ) return new WP_REST_Response(array('error'=>'missing_order'),400);
    $order_id = intval($body['order_id']);
    $order = wc_get_order($order_id);
    if ( ! $order ) return new WP_REST_Response(array('error'=>'order_not_found'),404);

    $txn = isset($body['txn_id']) ? sanitize_text_field($body['txn_id']) : '';
    $screenshot = isset($body['screenshot_url']) ? esc_url_raw($body['screenshot_url']) : '';

    if ( $txn ) update_post_meta($order_id, '_supi_txn', $txn);
    if ( $screenshot ) update_post_meta($order_id, '_supi_screenshot', $screenshot);

    // mark paid
    $order->payment_complete($txn?:'');
    $order->add_order_note('Smart UPI: payment confirmed via REST/manual.');

    return new WP_REST_Response(array('success'=>true),200);
}

function supi5_rest_check( WP_REST_Request $req ){
    $order_id = intval($req['order_id']);
    $order = wc_get_order($order_id);
    if ( ! $order ) return new WP_REST_Response(array('error'=>'order_not_found'),404);
    return new WP_REST_Response(array('status'=>$order->get_status()),200);
}

// Ajax upload handler for manual proof
add_action('wp_ajax_nopriv_supi5_upload','supi5_ajax_upload');
add_action('wp_ajax_supi5_upload','supi5_ajax_upload');
function supi5_ajax_upload(){
    check_ajax_referer('supi5_nonce','nonce');
    if ( empty($_FILES['file']) ) wp_send_json_error('nofile');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($_FILES['file'], array('test_form'=>false,'mimes'=>array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png')));
    if ( isset($uploaded['error']) ) wp_send_json_error($uploaded['error']);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $file = $uploaded['file'];
    $filetype = wp_check_filetype(basename($file), null);
    $attach = array('post_mime_type'=>$filetype['type'],'post_title'=>sanitize_file_name(basename($file)),'post_status'=>'inherit');
    $id = wp_insert_attachment($attach, $file);
    $meta = wp_generate_attachment_metadata($id, $file);
    wp_update_attachment_metadata($id, $meta);
    $url = wp_get_attachment_url($id);
    wp_send_json_success(array('url'=>$url));
}
