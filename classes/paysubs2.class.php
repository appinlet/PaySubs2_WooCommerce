<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * PayGate Payment Gateway - PaySubs2
 *
 * Provides a PayGate PaySubs2 Payment Gateway.
 *
 * @class       woocommerce_paysubs2
 * @package     WooCommerce
 * @category    Payment Gateways
 * @author      PayGate
 *
 */
class WC_Gateway_PaySubs2 extends WC_Payment_Gateway
{

    protected static $_instance = null;
    private static $log;

    const TEST_PAYGATE_ID = '10011072130';
    const TEST_SECRET_KEY = 'secret';

    public $version = '3.5.5';

    public $id = 'paysubs2';

    private $process_url = 'https://www.paygate.co.za/paysubs/process.trans';

    private $merchant_id;
    private $encryption_key;

    private $redirect_url;
    private $data_to_send;

    private $msg;

    public static function instance()
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {

        $this->method_title       = __( 'PayGate via PaySubs2', 'paysubs2' );
        $this->method_description = __( 'PayGate via PaySubs2 works by sending the customer to PayGate to complete their payment.', 'paysubs2' );
        $this->icon               = $this->get_plugin_url() . '/assets/images/logo_small.png';
        $this->has_fields         = true;
        $this->supports           = array(
            'products',
            'subscriptions',
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->merchant_id       = $this->settings['paygate_id'];
        $this->encryption_key    = $this->settings['encryption_key'];
        $this->title             = $this->settings['title'];
        $this->order_button_text = $this->settings['button_text'];
        $this->description       = $this->settings['description'];

        $this->msg['message'] = "";
        $this->msg['class']   = "";

        // Setup the test data, if in test mode.
        if ( $this->settings['testmode'] == 'yes' ) {
            $this->add_testmode_admin_settings_notice();
        }

        $this->redirect_url = add_query_arg( 'wc-api', 'WC_Gateway_PaySubs2_Redirect', home_url( '/' ) );

        add_action( 'woocommerce_api_wc_gateway_paysubs2_redirect', array(
            $this,
            'check_paysubs2_response',
        ) );

        add_action( 'wp_ajax_order_pay_payment', array( $this, 'process_review_payment' ) );
        add_action( 'wp_ajax_nopriv_order_pay_payment', array( $this, 'process_review_payment' ) );

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options',
            ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array(
                &$this,
                'process_admin_options',
            ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'paygate_payment_scripts' ) );

        add_action( 'woocommerce_receipt_paysubs2', array(
            $this,
            'receipt_page',
        ) );
    }

    public function get_order_id_order_pay()
    {
        global $wp;

        // Get the order ID
        $order_id = absint( $wp->query_vars['order-pay'] );

        if ( empty( $order_id ) || $order_id == 0 ) {
            return;
        }
        // Exit;
        return $order_id;
    }

    /**
     * Add payment scripts for iFrame support
     *
     * @since 1.0.0
     */
    public function paygate_payment_scripts()
    {
        wp_enqueue_script( 'paygate-checkout-js', $this->get_plugin_url() . '/assets/js/paygate_checkout.js', array(), WC_VERSION, true );
        if ( is_wc_endpoint_url( 'order-pay' ) ) {
            wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                'order_id' => $this->get_order_id_order_pay(),
            ) );

        } else {
            wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                'order_id' => 0,
            ) );
        }

        wp_enqueue_style( 'paygate-checkout-css', $this->get_plugin_url() . '/assets/css/paygate_checkout.css', array(), WC_VERSION );
    }

    /**
     * Add a notice to the merchant_key and merchant_id fields when in test mode.
     *
     * @since 1.0.0
     */
    public function add_testmode_admin_settings_notice()
    {
        $this->form_fields['paygate_id']['description'] .= ' <br><br><strong>' . __( 'PayGate ID currently in use.', 'paysubs2' ) . ' ( 10011072130 )</strong>';
        $this->form_fields['encryption_key']['description'] .= ' <br><br><strong>' . __( 'PayGate Encryption Key currently in use.', 'paysubs2' ) . ' ( secret )</strong>';
    }

    /**
     * Show Message.
     *
     * Display message depending on order results.
     *
     * @since 1.0.0
     *
     * @param $content
     *
     * @return string
     */
    public function show_message( $content )
    {
        return '<div class="' . $this->msg['class'] . '">' . $this->msg['message'] . '</div>' . $content;
    }

    /**
     * Get the plugin URL
     *
     * @since 1.0.0
     */
    public function get_plugin_url()
    {
        if ( isset( $this->plugin_url ) ) {
            return $this->plugin_url;
        }

        if ( is_ssl() ) {
            return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled'        => array(
                'title'       => __( 'Enable/Disable', 'paysubs2' ),
                'label'       => __( 'Enable PaySubs2 Payment Gateway', 'paysubs2' ),
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'paysubs2' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'          => array(
                'title'       => __( 'Title', 'paysubs2' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'paysubs2' ),
                'desc_tip'    => false,
                'default'     => __( 'Recurring Payment Gateway', 'paysubs2' ),
            ),
            'paygate_id'     => array(
                'title'       => __( 'PayGate ID', 'paysubs2' ),
                'type'        => 'text',
                'description' => __( 'This is the PayGate ID, received from PayGate.', 'paysubs2' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'encryption_key' => array(
                'title'       => __( 'Encryption Key', 'paysubs2' ),
                'type'        => 'text',
                'description' => __( 'This is the Encryption Key set in the PayGate Back Office.', 'paysubs2' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'frequency'      => array(
                'title'       => __( 'Payment Frequency', 'woocommerce_gateway_paysubs' ),
                'label'       => __( 'Choose Payment Frequency', 'woocommerce_gateway_paysubs' ),
                'type'        => 'select',
                'description' => 'Choose you desired payment frequency (recurring must be enabled).',
                'default'     => '229',
                'options'     => array(
                    '111' => 'Weekly on Sun',
                    '112' => 'Weekly on Mon',
                    '113' => 'Weekly on Tue',
                    '114' => 'Weekly on Wed',
                    '115' => 'Weekly on Thu',
                    '116' => 'Weekly on Fri',
                    '117' => 'Weekly on Sat',
                    '121' => '2nd Weekly on Sun',
                    '122' => '2nd Weekly on Mon',
                    '123' => '2nd Weekly on Tue',
                    '124' => '2nd Weekly on Wed',
                    '125' => '2nd Weekly on Thu',
                    '126' => '2nd Weekly on Fri',
                    '127' => '2nd Weekly on Sat',
                    '131' => '3rd Weekly on Sun',
                    '132' => '3rd Weekly on Mon',
                    '133' => '3rd Weekly on Tue',
                    '134' => '3rd Weekly on Wed',
                    '135' => '3rd Weekly on Thu',
                    '136' => '3rd Weekly on Fri',
                    '137' => '3rd Weekly on Sat',
                    '201' => 'Monthly on 1st',
                    '202' => 'Monthly on 2nd',
                    '203' => 'Monthly on 3rd',
                    '204' => 'Monthly on 4th',
                    '205' => 'Monthly on 5th',
                    '206' => 'Monthly on 6th',
                    '207' => 'Monthly on 7th',
                    '208' => 'Monthly on 8th',
                    '209' => 'Monthly on 9th',
                    '210' => 'Monthly on 10th',
                    '211' => 'Monthly on 11th',
                    '212' => 'Monthly on 12th',
                    '213' => 'Monthly on 13th',
                    '214' => 'Monthly on 14th',
                    '215' => 'Monthly on 15th',
                    '216' => 'Monthly on 16th',
                    '217' => 'Monthly on 17th',
                    '218' => 'Monthly on 18th',
                    '219' => 'Monthly on 19th',
                    '220' => 'Monthly on 20th',
                    '221' => 'Monthly on 21th',
                    '222' => 'Monthly on 22th',
                    '223' => 'Monthly on 23th',
                    '224' => 'Monthly on 24th',
                    '225' => 'Monthly on 25th',
                    '226' => 'Monthly on 26th',
                    '227' => 'Monthly on 27th',
                    '228' => 'Monthly on 28th',
                    '229' => 'Monthly on the last day of the month',
                    '301' => 'Every 2nd month on 1st',
                    '302' => 'Every 2nd month on 2nd',
                    '303' => 'Every 2nd month on 3rd',
                    '304' => 'Every 2nd month on 4th',
                    '305' => 'Every 2nd month on 5th',
                    '306' => 'Every 2nd month on 6th',
                    '307' => 'Every 2nd month on 7th',
                    '308' => 'Every 2nd month on 8th',
                    '309' => 'Every 2nd month on 9th',
                    '310' => 'Every 2nd month on 10th',
                    '311' => 'Every 2nd month on 11th',
                    '312' => 'Every 2nd month on 12th',
                    '313' => 'Every 2nd month on 13th',
                    '314' => 'Every 2nd month on 14th',
                    '315' => 'Every 2nd month on 15th',
                    '316' => 'Every 2nd month on 16th',
                    '317' => 'Every 2nd month on 17th',
                    '318' => 'Every 2nd month on 18th',
                    '319' => 'Every 2nd month on 19th',
                    '320' => 'Every 2nd month on 20th',
                    '321' => 'Every 2nd month on 21th',
                    '322' => 'Every 2nd month on 22th',
                    '323' => 'Every 2nd month on 23th',
                    '324' => 'Every 2nd month on 24th',
                    '325' => 'Every 2nd month on 25th',
                    '326' => 'Every 2nd month on 26th',
                    '327' => 'Every 2nd month on 27th',
                    '328' => 'Every 2nd month on 28th',
                    '329' => 'Every 2nd month on last day of the month',
                    '401' => 'Every 3rd month on 1st',
                    '402' => 'Every 3rd month on 2nd',
                    '403' => 'Every 3rd month on 3rd',
                    '404' => 'Every 3rd month on 4th',
                    '405' => 'Every 3rd month on 5th',
                    '406' => 'Every 3rd month on 6th',
                    '407' => 'Every 3rd month on 7th',
                    '408' => 'Every 3rd month on 8th',
                    '409' => 'Every 3rd month on 9th',
                    '410' => 'Every 3rd month on 10th',
                    '411' => 'Every 3rd month on 11th',
                    '412' => 'Every 3rd month on 12th',
                    '413' => 'Every 3rd month on 13th',
                    '414' => 'Every 3rd month on 14th',
                    '415' => 'Every 3rd month on 15th',
                    '416' => 'Every 3rd month on 16th',
                    '417' => 'Every 3rd month on 17th',
                    '418' => 'Every 3rd month on 18th',
                    '419' => 'Every 3rd month on 19th',
                    '420' => 'Every 3rd month on 20th',
                    '421' => 'Every 3rd month on 21th',
                    '422' => 'Every 3rd month on 22th',
                    '423' => 'Every 3rd month on 23th',
                    '424' => 'Every 3rd month on 24th',
                    '425' => 'Every 3rd month on 25th',
                    '426' => 'Every 3rd month on 26th',
                    '427' => 'Every 3rd month on 27th',
                    '428' => 'Every 3rd month on 28th',
                    '429' => 'Every 3rd month on last day of the month',
                ),
            ),
            'subsenddate'    => array(
                'title'       => __( 'Subs End Date', 'woocommerce_gateway_paysubs' ),
                'label'       => __( 'Choose Subs End Date', 'woocommerce_gateway_paysubs' ),
                'type'        => 'select',
                'description' => 'This is the date when the subscription expires and becomes invalid.',
                'default'     => '+1 year',
                'options'     => array(
                    '+1 month'  => 'Expire in 1 month',
                    '+2 month'  => 'Expire in 2 months',
                    '+3 month'  => 'Expire in 3 months',
                    '+4 month'  => 'Expire in 4 months',
                    '+5 month'  => 'Expire in 5 months',
                    '+6 month'  => 'Expire in 6 months',
                    '+7 month'  => 'Expire in 7 months',
                    '+8 month'  => 'Expire in 8 months',
                    '+9 month'  => 'Expire in 9 months',
                    '+10 month' => 'Expire in 10 months',
                    '+11 month' => 'Expire in 11 months',
                    '+12 month' => 'Expire in 12 months',
                    '+1 year'   => 'Expire in 1 year',
                    '+2 year'   => 'Expire in 2 years',
                    '+3 year'   => 'Expire in 3 years',
                    '+4 year'   => 'Expire in 4 years',
                    '+5 year'   => 'Expire in 5 years',
                    '+6 year'   => 'Expire in 6 years',
                    '+7 year'   => 'Expire in 7 years',
                    '+8 year'   => 'Expire in 8 years',
                    '+9 year'   => 'Expire in 9 years',
                    '+10 year'  => 'Expire in 10 years',
                    '+11 year'  => 'Expire in 11 years',
                    '+12 year'  => 'Expire in 12 years',
                    '+100 year' => 'Expire in 100 years',
                ),
            ),
            'payment_type'   => array(
                'title'       => __( 'Implementation', 'woocommerce_gateway_paysubs' ),
                'label'       => __( 'Choose Payment Type', 'woocommerce_gateway_paysubs' ),
                'type'        => 'select',
                'description' => 'Whether to use the Redirect or iFrame implementation.',
                'default'     => 'redirect',
                'options'     => array(
                    'redirect' => 'Redirect',
                    'iframe'   => 'iFrame',
                ),
            ),
            'testmode'       => array(
                'title'       => __( 'Test mode', 'paysubs2' ),
                'type'        => 'checkbox',
                'description' => __( 'Uses a PaySubs2 test account. Request test cards from PayGate.', 'paysubs2' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'description'    => array(
                'title'       => __( 'Description', 'paysubs2' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'paysubs2' ),
                'default'     => 'Pay via Credit or Debit Card',
            ),
            'button_text'    => array(
                'title'       => __( 'Order Button Text', 'paysubs2' ),
                'type'        => 'text',
                'description' => __( 'Changes the text that appears on the Place Order button.', 'paysubs2' ),
                'default'     => 'Proceed to PayGate',
            ),
        );

    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title'
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e( 'PaySubs2 Payment Gateway', 'paysubs2' );?></h3>
        <p><?php printf( __( 'PaySubs2 works by sending the user to %sPayGate%s to enter their payment information.', 'paysubs2' ), '<a href="https://www.paygate.co.za/">', '</a>' );?></p>

        <table class="form-table">
            <?php $this->generate_settings_html(); // Generate the HTML For the settings form. ?>
        </table><!--/.form-table-->
        <?php
}

    /**
     * Return false to bypass adding Tokenization in "My Account" section
     *
     * @return bool
     */
    public function add_payment_method()
    {
        return false;
    }

    /**
     * There are no payment fields for PaySubs2, but we want to show the description if set
     *
     * @since 1.0.0
     */
    public function payment_fields()
    {
        if ( isset( $this->settings['description'] ) && $this->settings['description'] != '' ) {
            echo wpautop( wptexturize( $this->settings['description'] ) );
        }
    }

    /**
     * Fetch required fields for PaySubs2
     *
     * @since 1.0.1
     */
    public function fetch_payment_params( $order_id )
    {

        $order = new WC_Order( $order_id );

        if ( $this->settings['testmode'] == 'yes' ) {
            $this->merchant_id    = self::TEST_PAYGATE_ID;
            $this->encryption_key = self::TEST_SECRET_KEY;
        }

        $encryptionKey = $this->encryption_key;

        $data = array(
            'VERSION'          => 21,
            'PAYGATE_ID'       => $this->merchant_id,
            'REFERENCE'        => 'order-id_' . $order_id . '_order-number_' . $order->get_order_number(),
            'AMOUNT'           => $order->get_total() * 100,
            'CURRENCY'         => 'ZAR',
            'RETURN_URL'       => $this->redirect_url,
            'TRANSACTION_DATE' => date( 'Y-m-d', $order->get_date_created()->getOffsetTimestamp() ),
        );

        // Processing subscription
        if (  ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) || ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order_id ) ) ) {

            $subscriptions = wcs_get_subscriptions_for_order( $order );

            $subscription = array_pop( $subscriptions );

            $unconverted_periods = array(
                'billing_period' => $subscription->billing_period,
                'trial_period'   => $subscription->trial_period,
            );

            $converted_periods = array();

            // Convert period strings into PayPay's format
            foreach ( $unconverted_periods as $key => $period ) {
                switch ( strtolower( $period ) ) {
                    case 'day':
                        $converted_periods[$key] = 'day';
                        break;
                    case 'week':
                        $converted_periods[$key] = 'week';
                        break;
                    case 'year':
                        $converted_periods[$key] = 'year';
                        break;
                    case 'month':
                    default:
                        $converted_periods[$key] = 'month';
                        break;
                }
            }

            $data['SUBS_START_DATE'] = $subscription->get_time( 'start' );
            $data['SUBS_END_DATE']   = $subscription->get_time( 'end' );

            if ( $converted_periods['billing_period'] == 'day' ) {
                $data['SUBS_FREQUENCY'] = 111;
            } else if ( $converted_periods['billing_period'] == 'week' ) {
                $data['SUBS_FREQUENCY'] = 121;
            } else {
                $data['SUBS_FREQUENCY'] = 228;
            }

            // End subscription
        } else {

            $data['SUBS_START_DATE'] = date( 'Y-m-d', $order->get_date_created()->getOffsetTimestamp() );
            $data['SUBS_END_DATE']   = date( 'Y-m-d', strtotime( $this->settings['subsenddate'] ) );
            $data['SUBS_FREQUENCY']  = $this->settings['frequency'];
        }

        $data['PROCESS_NOW']        = 'YES';
        $data['PROCESS_NOW_AMOUNT'] = $order->get_total() * 100;

        $checksum         = md5( implode( '|', $data ) . '|' . $this->encryption_key );
        $data['CHECKSUM'] = $checksum;
        return $data;
    }

    /**
     * Generate the PaySubs2 button link.
     *
     * @since 1.0.0
     *
     * @param $order_id
     *
     * @return string
     */
    public function generate_paysubs2_form( $order_id )
    {
        $order = new WC_Order( $order_id );

        $messageText = esc_js( __( 'Thank you for your order. We are now redirecting you to PayGate to make payment.', 'paysubs2' ) );

        $heading    = __( 'Thank you for your order, please click the button below to pay via PayGate.', 'paysubs2' );
        $buttonText = __( $this->order_button_text, 'paysubs2' );
        $cancelUrl  = esc_url( $order->get_cancel_order_url() );
        $cancelText = __( 'Cancel order &amp; restore cart', 'paysubs2' );

        $data  = $this->fetch_payment_params( $order_id );
        $value = "";
        foreach ( $data as $index => $v ) {

            $value .= '<input type="hidden" name="' . $index . '" value="' . $v . '" />';
        }

        $form = <<<HTML
<p>{$heading}</p>
<form action="{$this->process_url}" method="post" id="paysubs2_payment_form">
    {$value}
    <!-- Button Fallback -->
    <div class="payment_buttons">
        <input type="submit" class="button alt" id="submit_paysubs2_payment_form" value="{$buttonText}" /> <a class="button cancel" href="{$cancelUrl}">{$cancelText}</a>
    </div>
</form>
<script>
jQuery(document).ready(function(){
    jQuery(function(){
        jQuery("body").block({
            message: "{$messageText}",
            overlayCSS: {
                background: "#fff",
                opacity: 0.6
            },
            css: {
                padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait"
            }
        });
    });

    jQuery("#submit_paysubs2_payment_form").click();
    jQuery("#submit_paysubs2_payment_form").attr("disabled", true);
});
</script>
HTML;

        return $form;
    }

    /**
     * Process the payment and return the result.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment( $order_id )
    {
        if ( $this->settings['payment_type'] == 'redirect' ) {
            $order = new WC_Order( $order_id );

            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            );

        } else {
            $result = $this->fetch_payment_params( $order_id );
            echo json_encode( $result );
            die;
        }

    }

    /**
     * Receipt page.
     *
     * Display text and a button to direct the customer to PaySubs2.
     *
     * @since 1.0.0
     *
     * @param $order
     */
    public function receipt_page( $order )
    {
        echo $this->generate_paysubs2_form( $order );
    }

    /**
     * Check for valid PaySubs2 Redirect
     *
     * @since 1.0.0
     */
    public function check_paysubs2_response()
    {
        global $woocommerce;

        if ( isset( $_POST['PAYGATE_ID'] ) ) {

            if ( isset( $_POST['TRANSACTION_STATUS'] ) ) {
                $ref      = explode( '_', $_POST['REFERENCE'] );
                $order_id = $ref[1];

                if ( $order_id != '' ) {
                    $order = wc_get_order( $order_id );

                    if ( $_POST['TRANSACTION_STATUS'] == 1 ) {

                        //Success
                        $order->payment_complete();
                        $order->add_order_note( __( 'Response via Redirect, Transaction successful', 'woocommerce' ) );

                        // Empty the cart
                        $woocommerce->cart->empty_cart();
                        if ( $this->settings['payment_type'] == 'redirect' ) {
                            wp_redirect( $this->get_return_url( $order ) );
                        } else {

                            $redirect_link = $this->get_return_url( $order );

                            echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                        }
                        exit;
                    } else {

                        $order->add_order_note( 'Response via Redirect, Transaction declined.' . '<br/>' );
                        if ( !$order->has_status( 'failed' ) ) {
                            $order->update_status( 'failed' );
                        }
                        if ( $this->settings['payment_type'] == 'redirect' ) {
                            $this->add_notice( 'Your order was cancelled.', 'notice' );
                            wp_redirect( $order->get_cancel_order_url() );
                        } else {

                            $redirect_link = htmlspecialchars_decode( urldecode( $order->get_cancel_order_url() ) );
                            echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                        }
                        exit;
                    }
                }
            }
        }
        wp_die( 'PaySubs2 Request Failure', 'PaySubs2 Failure', array( 'response' => 500 ) );
    }

    /**
     * Add WooCommerce notice
     *
     * @since 1.0.0
     *
     */
    public function add_notice( $message, $notice_type = 'success' )
    {
        // If function should we use?
        if ( function_exists( "wc_add_notice" ) ) {
            // Use the new version of the add_error method
            wc_add_notice( $message, $notice_type );
        } else {
            // Use the old version
            $woocommerce->add_error( $message );
        }
    }

    public function process_review_payment()
    {
        if ( !empty( $_POST['order_id'] ) ) {
            $this->process_payment( $_POST['order_id'] );
        }
    }

    /**
     * Debug logger
     *
     * @since 1.1.3
     */

    public static function log( $message )
    {

        if ( empty( self::$log ) ) {

            self::$log = new WC_Logger();
        }

        self::$log->add( 'Paysubs', $message );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

            error_log( $message );
        }
    }
}