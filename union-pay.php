<?php
/*
 * Plugin Name:       Union Bank Pay
 * Plugin URI:        https://github.com/radon1284/union-pay-for-woocomerce
 * Description:       A local payment gateway using Union Bank
 * Author:            Team WP
 * Author URI:        https://github.com/radon1284
 * Requires at least: 4.0
 * Tested up to:      4.6
 * Text Domain:       woocommerce-union-pay
 * Domain Path:       languages
 * Network:           false
 * GitHub Plugin URI: https://github.com/radon1284/union-pay-for-woocomerce
 *
 * WooCommerce Payment Gateway Boilerlate is distributed under the terms of the 
 * GNU General Public License as published by the Free Software Foundation, 
 * either version 2 of the License, or any later version.
 *
 * WooCommerce Payment Gateway Boilerlate is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Payment Gateway Boilerlate. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_ubp_gateway_class');
function init_ubp_gateway_class(){

    class WC_Union_Pay extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'ubp_payment';

            $this->id                 = 'ubp';
            $this->icon               = apply_filters('woocommerce_ubp_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Union pay', $this->domain );
            $this->method_description = __( 'Allows payments with ubp gateway.', $this->domain );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        			= $this->get_option( 'title' );
            $this->description  			= $this->get_option( 'description' );
            $this->instructions 			= $this->get_option( 'instructions', $this->description );
            $this->order_status 			= $this->get_option( 'order_status', 'completed' );
            $this->public_api_key        	= $this->get_option( 'public_api_key' );
            $this->secret_api_key        	= $this->get_option( 'secret_api_key' );
            $this->channel_id        		= $this->get_option( 'channel_id' );
            $this->account_number       	= $this->get_option( 'account_number' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_ubp', array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Payment', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'Union Pay', $this->domain ),
                    'desc_tip'    => true,
                ),
                'public_api_key' => array(
                    'title'       => __( 'Public Api Key', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Public Api Key', $this->domain ),
                    'default'     => __( 'Public Api Key', $this->domain ),
                    'desc_tip'    => true,
                ),
                'secret_api_key' => array(
                    'title'       => __( 'Secret Api Key', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Secret Api Key', $this->domain ),
                    'default'     => __( 'Secret Api Key', $this->domain ),
                    'desc_tip'    => true,
                ),
                'channel_id' => array(
                    'title'       => __( 'Channel Id', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is channel Id', $this->domain ),
                    'default'     => __( 'Channel Id', $this->domain ),
                    'desc_tip'    => true,
                ),
                'account_number' => array(
                    'title'       => __( 'Account Number', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This is Account Number', $this->domain ),
                    'default'     => __( 'Account Number', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the ubper will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'ubp' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

        public function payment_fields(){

            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }

            ?>
            <div id="ubp_input">
                <p class="form-row form-row-wide">
                    <label for="source_account" class=""><?php _e('Your Account Number', $this->domain); ?></label>
                    <input type="text" class="" name="source_account" id="source_account" placeholder="" value="">
                </p>
            </div>
            <?php
            
            
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Set order status
            $order->update_status( $status, __( 'Checkout with ubp payment. ', $this->domain ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
            


        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_ubp_gateway_class' );
function add_ubp_gateway_class( $methods ) {
    $methods[] = 'WC_Union_Pay'; 
    return $methods;
}

add_action('woocommerce_checkout_process', 'process_ubp_payment');
function process_ubp_payment(){

    if($_POST['payment_method'] != 'ubp')
        return;

    if( !isset($_POST['source_account']) || empty($_POST['source_account']) )
        wc_add_notice( __( 'Please add your account number', $this->domain ), 'error' );


$data = array();
        $data['channel_id'] = $get_options['channel_id'];
        $data['transaction_id'] = $order;
        $data['source_account'] = $get_options['source_account'];
        $data['source_currency'] = "php";
        $data['target_account'] = $_POST['source_account'];
        $data['target_currency'] = "php";
        $data['amount'] = $order->get_total();
$post_str = '';
        foreach($data as $key=> $value){
         $post_str .= $key. '=' .urlencode($value).'&';
         }
        $post_str = substr($post_str, 0, -1);
        if($_POST['submitted'] == 'true') {
        
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.us.apiconnect.ibmcloud.com/ubpapi-dev/sb/api/RESTs/transfer",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => $post_str,
  CURLOPT_HTTPHEADER => array(
    "accept: application/json",
    "content-type: application/json",
    "x-ibm-client-id:". $get_options['public_api_key'],
    "x-ibm-client-secret:". $get_options['secret_api_key']
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
}
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'ubp_payment_update_order_meta' );
function ubp_payment_update_order_meta( $order_id ) {

    if($_POST['payment_method'] != 'ubp')
        return;

    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit();

    update_post_meta( $order_id, 'source_account', $_POST['source_account'] );
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'ubp_checkout_field_display_admin_order_meta', 10, 1 );
function ubp_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'ubp')
        return;

    $source_account = get_post_meta( $order->id, 'source_account', true );
    $transaction = get_post_meta( $order->id, 'transaction', true );

    echo '<p><strong>'.__( 'Mobile Number' ).':</strong> ' . $source_account . '</p>';
    echo '<p><strong>'.__( 'Transaction ID').':</strong> ' . $transaction . '</p>';
}