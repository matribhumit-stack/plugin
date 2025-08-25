<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Gateway_Supi5 extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'supi5';
        $this->method_title = __('Smart UPI (Final 9)', 'supi-final7');
        $this->has_fields = true;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title','Pay via UPI');
        $this->description = $this->get_option('description','Pay securely using UPI.');
        $this->upi_id = $this->get_option('upi_id','');
        $this->timer = intval($this->get_option('timer',30));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this,'process_admin_options'));
        register_activation_hook(__FILE__, array($this, 'supi5_activate_defaults'));
    }

    public function supi5_activate_defaults(){
        $opts = get_option('woocommerce_' . $this->id . '_settings', array());
        if (!isset($opts['enabled'])) {
            $opts['enabled'] = 'yes';
            update_option('woocommerce_' . $this->id . '_settings', $opts);
        }
    }

    public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array('title'=>'Enable/Disable','type'=>'checkbox','label'=>'Enable Smart UPI (Final 5)','default'=>'yes'),
            'title' => array('title'=>'Title','type'=>'text','default'=>'Pay via UPI'),
            'description' => array('title'=>'Description','type'=>'textarea','default'=>'Pay using UPI.'),
            'upi_id' => array('title'=>'Merchant UPI ID','type'=>'text','description'=>'Enter your UPI ID (example@bank)','default'=>''),
            'timer' => array('title'=>'Confirmation Timer (seconds)','type'=>'number','default'=>30),
        );
    }

    public function is_available(){
        if ('yes' !== $this->get_option('enabled')) return false;
        if ( empty($this->get_option('upi_id')) ) return false;
        if ( ! is_checkout() ) return false;
        return true;
    }

    public function payment_fields(){
        $upi = esc_html($this->get_option('upi_id',''));
        $timer = esc_attr($this->get_option('timer',30));
        ?>
        <div class="supi5-box" data-upi="<?php echo $upi; ?>" data-timer="<?php echo $timer; ?>">
            <h3 class="supi5-title"><?php echo esc_html($this->title); ?></h3>
            <p class="supi5-desc"><?php echo esc_html($this->description); ?></p>

            <div class="supi5-containers" style="display:flex;justify-content:center;align-items:center;flex-wrap:wrap;">
                <div class="supi5-qr" style="text-align:center;">
                    <div id="supi5-qr-area" aria-hidden="true"></div>
                    <div id="supi5-upi" style="margin-top:8px;font-family:monospace;"><?php echo $upi; ?></div>
                </div>
            </div>

            <div id="supi5-progress" style="display:none;text-align:center;margin-top:12px;">
                <div class="supi5-bar" style="margin:0 auto;max-width:320px;"><div class="supi5-fill" id="supi5-fill" style="width:0%"></div></div>
                <div class="supi5-count">Waiting for confirmation... <span id="supi5-timer"><?php echo $timer; ?></span>s</div>
            </div>

            <div id="supi5-retry" style="display:none;text-align:center;margin-top:10px;">
                <p class="supi5-warn">If payment didn't process, please <strong>Retry</strong> instead of navigating back.</p>
                <button id="supi5-retry-btn" class="button">Retry</button>
            </div>

            <div id="supi5-manual" style="display:none;text-align:center;margin-top:10px;">
                <h4>Manual confirmation</h4>
                <input type="text" id="supi5-txn" placeholder="Transaction ID" style="width:80%;padding:6px;margin-top:6px;" />
                <input type="file" id="supi5-file" accept="image/*" style="margin-top:8px;" />
                <div><button id="supi5-manual-submit" class="button">Submit Proof</button></div>
                <div id="supi5-manual-res" style="margin-top:8px;"></div>
            </div>
        </div>
        <?php
    }

    public function process_payment($order_id){
        $order = wc_get_order($order_id);
        $order->update_status('on-hold','Awaiting UPI payment confirmation');
        wc_reduce_stock_levels($order_id);
        return array('result'=>'success','redirect'=>$this->get_return_url($order));
    }
}
?>