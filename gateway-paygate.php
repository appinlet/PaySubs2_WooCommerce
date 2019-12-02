<?php

/**
 * Plugin Name: PayGate PaySubs2 plugin for WooCommerce
 * Plugin URI: https://github.com/PayGate/PaySubs2_WooCommerce
 * Description: Accept payments for WooCommerce using PayGate's PaySubs2 service
 * Version: 1.0.4
 * Tested: 5.3.0
 * Author: PayGate (Pty) Ltd
 * Author URI: https://www.paygate.co.za/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * WC requires at least: 2.6
 * WC tested up to: 3.8
 *
 * Copyright: © 2019 PayGate (Pty) Ltd.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action( 'plugins_loaded', 'woocommerce_paysubs2_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */

function woocommerce_paysubs2_init()
{

    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once plugin_basename( 'classes/paysubs2.class.php' );

    add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_paysubs2_gateway' );

    require_once 'classes/updater.class.php';

    if ( is_admin() ) {
        // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug'               => plugin_basename( __FILE__ ),
            'proper_folder_name' => 'woocommerce-gateway-paygate-ps2',
            'api_url'            => 'https://api.github.com/repos/PayGate/PaySubs2_WooCommerce',
            'raw_url'            => 'https://raw.github.com/PayGate/PaySubs2_WooCommerce/master',
            'github_url'         => 'https://github.com/PayGate/PaySubs2_WooCommerce',
            'zip_url'            => 'https://github.com/PayGate/PaySubs2_WooCommerce/archive/master.zip',
            'homepage'           => 'https://github.com/PayGate/PaySubs2_WooCommerce',
            'sslverify'          => true,
            'requires'           => '4.0',
            'tested'             => '5.3.0',
            'readme'             => 'README.md',
            'access_token'       => '',
        );

        new WP_GitHub_Updater_PS2( $config );
        add_action( 'woocommerce_product_write_panel_tabs', 'ps2_custom_tab_options_tab' );
        add_action( 'woocommerce_product_write_panels', 'ps2_custom_tab_options' );
        add_action( 'woocommerce_process_product_meta', 'ps2_process_product_meta_custom_tab' );
    }
} // End woocommerce_paysubs2_init()

function ps2_custom_tab_options()
{
    global $post;
    $post_id = $post->ID;

    $custom_tab_options = array(
        'ps2_use_product_meta'   => get_post_meta( $post_id, 'ps2_use_product_meta', $_POST['ps2_use_product_meta'] ),
        'ps2_recurring'          => get_post_meta( $post_id, 'ps2_recurring', $_POST['ps2_recurring'] ),
        'ps2_recur_freq'         => get_post_meta( $post_id, 'ps2_recur_freq', $_POST['ps2_recur_freq'] ),
        'ps2_sub_start_date'     => get_post_meta( $post_id, 'ps2_sub_start_date', $_POST['ps2_sub_start_date'] ),
        'ps2_expire_interval'    => get_post_meta( $post_id, 'ps2_expire_interval', $_POST['ps2_expire_interval'] ),
        'ps2_process_now_amount' => get_post_meta( $post_id, 'ps2_process_now_amount', $_POST['ps2_process_now_amount'] ),
    );

    ?>
    <div id="paysubs2_service_tab_data" class="panel woocommerce_options_panel">

        <div class="options_group custom_tab_options">
            <p class="form-field">
                <label><?php _e( 'Use this meta data:', 'woothemes' );?></label>
                <?php
if ( $custom_tab_options['ps2_use_product_meta'][0] == 'on' ) {
        echo '<input type="checkbox" name="ps2_use_product_meta" checked />';
    } else {
        echo '<input type="checkbox" name="ps2_use_product_meta" />';
    }
    ?>
            </p>
            <p class="form-field">
                <label><?php _e( 'Recurring Order:', 'woothemes' );?></label>
                <?php
if ( $custom_tab_options['ps2_recurring'][0] == 'on' ) {
        echo '<input type="checkbox" name="ps2_recurring" checked />';
    } else {
        echo '<input type="checkbox" name="ps2_recurring" />';
    }
    ?>
            </p>
            <p class="form-field">
                <label><?php _e( 'Recurrence Frequency:', 'woothemes' );?></label>
                <select name="ps2_recur_freq">
                    <?php
$options = array(
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
    );
    $opts = '';
    foreach ( $options as $k => $o ) {
        if ( isset( $custom_tab_options['ps2_recur_freq'][0] ) && $custom_tab_options['ps2_recur_freq'][0] != '' ) {
            if ( $k != (int) $custom_tab_options['ps2_recur_freq'][0] ) {
                $opts .= '<option value="' . $k . '">' . $o . '</option>';
            } else {
                $opts .= '<option value="' . $k . '" selected>' . $o . '</option>';
            }
        } else {
            if ( $k != 229 ) {
                $opts .= '<option value="' . $k . '">' . $o . '</option>';
            } else {
                $opts .= '<option value="' . $k . '" selected>' . $o . '</option>';
            }
        }
    }
    echo $opts;
    ?>
                </select>
            </p>
            <p class="form-field">
                <label><?php _e( 'Subscription Start:', 'woothemes' );?></label>
                <input type="date" name="ps2_sub_start_date" value="<?php echo date( 'Y-m-d' ); ?>"/>
            </p>
            <p class="form-field">
                <label><?php _e( 'Expiry Interval:', 'woothemes' );?></label>
                <select name="ps2_expire_interval">
                    <?php
$options = array(
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
    );
    $opts = '';
    foreach ( $options as $k => $o ) {
        if ( isset( $custom_tab_options['ps2_expire_interval'][0] ) && $custom_tab_options['ps2_expire_interval'][0] != '' ) {
            if ( $k != $custom_tab_options['ps2_expire_interval'][0] ) {
                $opts .= '<option value="' . $k . '">' . $o . '</option>';
            } else {
                $opts .= '<option value="' . $k . '" selected >' . $o . '</option>';
            }
        } else {
            if ( $k != '+1 year' ) {
                $opts .= '<option value="' . $k . '">' . $o . '</option>';
            } else {
                $opts .= '<option value="' . $k . '" selected >' . $o . '</option>';
            }
        }
    }
    echo $opts;
    ?>
                </select>
            </p>
            <p class="form-field">
                <label><?php _e( 'Process Now Amount:', 'woothemes' );?></label>
                <input type="number" step="0.01" name="ps2_process_now_amount"
                       placeholder="Enter non-zero amount to process now"/>
            </p>
        </div>
    </div>
    <?php
}

function ps2_custom_tab_options_tab()
{
    ?>
    <li class="custom_tab"><a href="#paysubs2_service_tab_data"><?php _e( '  PaySubs2 Service Type', 'woothemes' );?></a>
    </li>
    <?php
}

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */

function woocommerce_add_paysubs2_gateway( $methods )
{

    $methods[] = 'WC_Gateway_PaySubs2';

    return $methods;

}

/**
 * Process meta
 *
 * Processes the custom tab options when a post is saved
 */
function ps2_process_product_meta_custom_tab( $post_id )
{
    update_post_meta( $post_id, 'ps2_use_product_meta', $_POST['ps2_use_product_meta'] );
    update_post_meta( $post_id, 'ps2_recurring', $_POST['ps2_recurring'] );
    update_post_meta( $post_id, 'ps2_recur_freq', $_POST['ps2_recur_freq'] );
    update_post_meta( $post_id, 'ps2_sub_start_date', $_POST['ps2_sub_start_date'] );
    update_post_meta( $post_id, 'ps2_expire_interval', $_POST['ps2_expire_interval'] );
    update_post_meta( $post_id, 'ps2_process_now_amount', $_POST['ps2_process_now_amount'] );
}
// End woocommerce_add_paysubs2_gateway()
