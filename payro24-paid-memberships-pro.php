<?php
/**
 * Plugin Name: payro24 Paid Memberships Pro
 * Description: payro24 payment gateway for Paid Memberships Pro
 * Author: payro24
 * Version: 1.1.1
 * License: GPL v2.0.
 * Author URI: https://payro24.ir
 * Author Email: info@payro24.ir
 * Text Domain: payro24-paid-memberships-pro
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
function payro24_pmpro_load_textdomain()
{
    load_plugin_textdomain('payro24-paid-memberships-pro', FALSE, basename(dirname(__FILE__)) . '/languages');
}

function activate(){
    global $wpdb;
    $table_names = [
        'pmpro_discount_codes',
        'pmpro_discount_codes_levels',
        'pmpro_discount_codes_uses',
        'pmpro_memberships_categories',
        'pmpro_memberships_pages',
        'pmpro_memberships_users',
        'pmpro_membership_levelmeta',
        'pmpro_membership_levels',
        'pmpro_membership_orders',
    ];
    foreach ($table_names as $table_name){
        $table_name = $wpdb->prefix . $table_name;

        if ( $wpdb->get_var( "show tables like '$table_name'" ) == $table_name ) {
            $wpdb->query("ALTER TABLE $table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        }
    }
    update_option( 'payro24_pmpro_version', '1.1.1' );
}

add_action('init', 'payro24_pmpro_load_textdomain');

//load classes init method
add_action('plugins_loaded', 'load_payro24_pmpro_class', 11);
add_action('plugins_loaded', ['PMProGateway_payro24', 'init'], 12);
register_activation_hook( __FILE__, 'activate' );

function load_payro24_pmpro_class()
{
    if (class_exists('PMProGateway')) {
        class PMProGateway_payro24 extends PMProGateway
        {
            public function __construct($gateway = NULL)
            {
                $this->gateway = $gateway;

                return $this->gateway;
            }

            public static function init()
            {
                //make sure payro24 is a gateway option
                add_filter('pmpro_gateways', [
                    __CLASS__,
                    'pmpro_gateways',
                ]);

                //add fields to payment settings
                add_filter('pmpro_payment_options', [
                    __CLASS__,
                    'pmpro_payment_options',
                ]);
                add_filter('pmpro_payment_option_fields', [
                    __CLASS__,
                    'pmpro_payment_option_fields',
                ], 10, 2);

                // Add some currencies
                add_filter('pmpro_currencies', [
                    __CLASS__,
                    'pmpro_currencies',
                ]);

                //code to add at checkout if payro24 is the current gateway
                $gateway = pmpro_getOption('gateway');
                if ($gateway == 'payro24') {
                    add_filter('pmpro_checkout_before_change_membership_level', [
                        __CLASS__,
                        'pmpro_checkout_before_change_membership_level',
                    ], 10, 2);
                    add_filter('pmpro_include_billing_address_fields', '__return_false');
                    add_filter('pmpro_include_payment_information_fields', '__return_false');
                    add_filter('pmpro_required_billing_fields', [
                        __CLASS__,
                        'pmpro_required_billing_fields',
                    ]);
                }

                add_action('wp_ajax_nopriv_payro24-ins', [
                    __CLASS__,
                    'pmpro_wp_ajax_payro24_ins',
                ]);
                add_action('wp_ajax_payro24-ins', [
                    __CLASS__,
                    'pmpro_wp_ajax_payro24_ins',
                ]);
                add_action('pmpro_checkout_after_form', [
                    __CLASS__,
                    'pmpro_checkout_after_form',
                ]);
                add_action('pmpro_invoice_bullets_bottom', [
                    __CLASS__,
                    'pmpro_invoice_bullets_bottom',
                ]);
                $version = get_option( 'payro24_pmpro_version', '1.0' );
                if ( version_compare( $version, '1.1.0' ) < 0 ) {
                    activate();
                }
            }

            /**
             * Adds Iranian currencies
             *
             * @param $currencies
             *
             * @return mixed
             */
            public static function pmpro_currencies($currencies)
            {

                $currencies['IRT'] = array(
                    'name' => __('Iranian Toman', 'payro24-paid-memberships-pro'),
                    'symbol' => __('Toman', 'payro24-paid-memberships-pro'),
                    'position' => 'right',
                );
                $currencies['IRR'] = array(
                    'name' => __('Iranian Rial', 'payro24-paid-memberships-pro'),
                    'symbol' => ' &#65020;',
                    'position' => 'right',
                );

                return $currencies;
            }

            /**
             * Make sure payro24 is in the gateways list.
             *
             * @since 1.0
             */
            public static function pmpro_gateways($gateways)
            {
                if (empty($gateways['payro24'])) {
                    $gateways['payro24'] = 'payro24';
                }

                return $gateways;
            }

            /**
             * Get a list of payment options that the payro24 gateway needs/supports.
             *
             * @since 1.0
             */
            public static function getGatewayOptions()
            {
                $options = [
                    'payro24_api_key',
                    'currency',
                    'tax_rate',
                ];

                return $options;
            }

            /**
             * Set payment options for payment settings page.
             *
             * @since 1.0
             */
            public static function pmpro_payment_options($options)
            {
                //get gateway options
                $payro24_options = self::getGatewayOptions();

                //merge with others.
                $options = array_merge($payro24_options, $options);

                return $options;
            }

            /**
             * Remove required billing fields.
             *
             * @since 1.8
             */
            public static function pmpro_required_billing_fields($fields)
            {
                unset($fields['bfirstname']);
                unset($fields['blastname']);
                unset($fields['baddress1']);
                unset($fields['bcity']);
                unset($fields['bstate']);
                unset($fields['bzipcode']);
                unset($fields['bphone']);
                unset($fields['bemail']);
                unset($fields['bcountry']);
                unset($fields['CardType']);
                unset($fields['AccountNumber']);
                unset($fields['ExpirationMonth']);
                unset($fields['ExpirationYear']);
                unset($fields['CVV']);

                return $fields;
            }

            /**
             * Display fields for payro24 options.
             *
             * @since 1.0
             */
            public static function pmpro_payment_option_fields($values, $gateway)
            {
                ?>
                <tr class="pmpro_settings_divider gateway gateway_payro24"
                    <?php if ($gateway != 'payro24'): ?>
                        style="display: none;"
                    <?php endif; ?>
                >
                    <td colspan="2">
                        <hr>
                        <h3><?php _e('payro24 Configuration', 'payro24-paid-memberships-pro'); ?></h3>
                    </td>
                </tr>
                <tr class="gateway gateway_payro24"
                    <?php if ($gateway != 'payro24') : ?>
                        style="display: none;"
                    <?php endif; ?>
                >
                    <th scope="row" valign="top">
                        <label for="payro24_api_key"><?php _e('API Key', 'payro24-paid-memberships-pro'); ?> :</label>
                    </th>
                    <td>
                        <input type="text" id="payro24_api_key"
                               name="payro24_api_key" size="60"
                               value="<?php echo esc_attr($values['payro24_api_key']); ?>"
                        />
                    </td>
                </tr>
                <script>
                    setTimeout(function(){
                        pmpro_changeGateway(jQuery('#gateway').val())
                    }, 100);
                </script>
                <?php
            }

            public static function pmpro_checkout_after_form(){
                print sprintf(
                    '<span class="payro24-pmpro-logo" style="font-size: 12px;padding: 5px 0;"><img src="%1$s" style="display: inline-block;vertical-align: middle;width: 70px;">%2$s</span>',
                    plugins_url( 'assets/logo.svg', __FILE__ ), __( 'Pay with payro24', 'payro24-paid-memberships-pro' )
                );
                print '<style>
                    .payro24-pmpro-logo{
                        margin: calc(-1em - 44px) 0 calc(1.5em + 44px) 0;
                        display: block;
                    }
                </style>';

                if( !empty($_GET['payro24_message']) ){
                    print '<div class="pmpro_error pmpro_message" style="text-align: center;">'. sanitize_text_field($_GET['payro24_message']) .'</div>';
                }
            }

            public static function pmpro_invoice_bullets_bottom(){
                if( !empty($_GET['payro24_message']) ){
                    print '<div class="pmpro_success pmpro_message payro24-success-message" style="text-align: center;margin: 30px 0 0 0;">'. sanitize_text_field($_GET['payro24_message']) .'</div>';
                }
            }

            /**
             * Instead of change membership levels, send users to payro24 to pay.
             *
             * @since 1.8
             */
            public static function pmpro_checkout_before_change_membership_level($user_id, $morder)
            {
                global $wpdb, $discount_code_id;

                //if no order, no need to pay
                if (empty($morder)) {
                    return;
                }

                $morder->user_id = $user_id;
                $morder->saveOrder();

                //save discount code use
                if (!empty($discount_code_id)) {
                    $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");
                }

                $gtw_env = pmpro_getOption('gateway_environment');
                $api_key = pmpro_getOption('payro24_api_key');

                if ($gtw_env == '' || $gtw_env == 'sandbox') {
                    $sandbox = 1;
                } else {
                    $sandbox = 0;
                }

                $order_id = $morder->code;
                $callback = admin_url('admin-ajax.php') . '?action=payro24-ins&oid=' . $order_id;

                global $pmpro_currency;
                $amount = intval($morder->subtotal);
                if ($pmpro_currency == 'IRT') {
                    $amount *= 10;
                }

                $data = [
                    'order_id'  => $order_id,
                    'amount'    => $amount,
                    'name'      => (!empty( $morder->FirstName ) ? $morder->FirstName : '') . !empty( $morder->LastName ) ? $morder->LastName : '',
                    'phone'     => '',
                    'mail'      => !empty( $morder->Email ) ? $morder->Email : '',
                    'desc'      => '',
                    'callback'  => $callback,
                ];
                $headers = [
                    'Content-Type' => 'application/json',
                    'P-TOKEN' => $api_key,
                    'P-SANDBOX' => $sandbox,
                ];
                $args = [
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 30,
                ];
                $response = self::call_gateway_endpoint('https://api.payro24.ir/v1.1/payment', $args);
                if (is_wp_error($response)) {
                    $note           = sprintf(__('An Error accrued: %s', 'payro24-paid-memberships-pro'), $response->get_error_message() );
                    $morder->status = 'error';
                    $morder->notes  = $note;
                    $morder->saveOrder();

                    $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note);
                    wp_redirect($redirect);
                    exit;
                }

                $http_status = wp_remote_retrieve_response_code($response);
                $result      = wp_remote_retrieve_body($response);
                $result      = json_decode($result);

                if ($http_status == 201) {
                    $morder->status = 'pending';
                    $morder->notes  = sprintf(__('An pending occurred while creating a transaction. pendding status: %s', 'payro24-paid-memberships-pro'), $http_status);
                    $morder->saveOrder();

                    wp_redirect($result->link);
                    exit;
                } else {
                    $morder->status = 'error';
                    $note           = sprintf(__('An error occurred while creating a transaction. error status: %s, error code: %s, error message: %s', 'payro24-paid-memberships-pro'), $http_status, $result->error_code, $result->error_message);
                    $morder->notes  = $note;
                    $morder->saveOrder();

                    $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                    wp_redirect($redirect);
                    exit;
                }
            }

            public static function pmpro_wp_ajax_payro24_ins()
            {
                if (!isset($_GET['oid']) || empty($_GET['oid'])) {
                    $redirect = pmpro_url('checkout', '?payro24_message='. __('The oid parameter is not set.', 'payro24-paid-memberships-pro') );
                    wp_redirect($redirect);
                    exit;
                }

                $oid    = sanitize_text_field($_GET['oid']);
                $morder = NULL;
                try {
                    $morder = new MemberOrder($oid);
                    $morder->getMembershipLevel();
                } catch (Exception $exception) {
                    $redirect = pmpro_url('checkout', '?payro24_message='. __('The oid parameter is not correct.', 'payro24-paid-memberships-pro') );
                    wp_redirect($redirect);
                    exit;
                }

                $status    = !empty($_POST['status'])  ? sanitize_text_field($_POST['status'])   : (!empty($_GET['status'])  ? sanitize_text_field($_GET['status'])   : NULL);
                $track_id  = !empty($_POST['track_id'])? sanitize_text_field($_POST['track_id']) : (!empty($_GET['track_id'])? sanitize_text_field($_GET['track_id']) : NULL);
                $id        = !empty($_POST['id'])      ? sanitize_text_field($_POST['id'])       : (!empty($_GET['id'])      ? sanitize_text_field($_GET['id'])       : NULL);
                $order_id  = !empty($_POST['order_id'])? sanitize_text_field($_POST['order_id']) : (!empty($_GET['order_id'])? sanitize_text_field($_GET['order_id']) : NULL);

                if ($order_id != $oid) {
                    $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. __('The oid parameter is not correct.', 'payro24-paid-memberships-pro') );
                    wp_redirect($redirect);
                    exit;
                }

                if ($status == 10) {
                    $gtw_env = pmpro_getOption('gateway_environment');
                    $api_key = pmpro_getOption('payro24_api_key');

                    if ($gtw_env == '' || $gtw_env == 'sandbox') {
                        $sandbox = 1;
                    } else {
                        $sandbox = 0;
                    }

                    $data = [
                        'id' => $id,
                        'order_id' => $order_id,
                    ];
                    $headers = [
                        'Content-Type' => 'application/json',
                        'P-TOKEN' => $api_key,
                        'P-SANDBOX' => $sandbox,
                    ];
                    $args = [
                        'body' => json_encode($data),
                        'headers' => $headers,
                        'timeout' => 30,
                    ];
                    $response = self::call_gateway_endpoint('https://api.payro24.ir/v1.1/payment/verify', $args);
                    if ( is_wp_error($response) ) {
                        $note           = sprintf(__('An Error accrued: %s', 'payro24-paid-memberships-pro'), $response->get_error_message() );
                        $morder->status = 'error';
                        $morder->notes  = $note;
                        $morder->saveOrder();

                        $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                        wp_redirect($redirect);
                        exit;
                    }

                    $http_status = wp_remote_retrieve_response_code($response);
                    $result      = wp_remote_retrieve_body($response);
                    $result      = json_decode($result);

                    if ($http_status == 200) {
                        $note           = self::getStatus($result->status);
                        $note           = sprintf( $note . __(" (status code: %s), track id: %s, order id: %s", "payro24-paid-memberships-pro"), $result->track_id, $order_id );
                        $morder->notes  = $note;

                        if ($result->status == 100) {
                            if ( self::do_level_up( $morder, $id ) ) {
                                $note           = sprintf( __("Your payment is completed. track id: %s, order id: %s", "payro24-paid-memberships-pro"), $result->track_id, $order_id );
                                $morder->notes  = $note . "<br>data: " . print_r($result, true);
                                $morder->saveOrder();

                                $redirect = pmpro_url('confirmation', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                                wp_redirect($redirect);
                                exit;
                            } else {
                                $note           = sprintf( __("An Error accrued doing level up. track id: %s, order id: %s", "payro24-paid-memberships-pro"), $result->track_id, $order_id );
                                $morder->notes  = $note;
                                $morder->status = 'error';
                                $morder->saveOrder();

                                $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                                wp_redirect($redirect);
                                exit;
                            }
                        } else {
                            $morder->status = 'error';
                            $morder->saveOrder();

                            $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                            wp_redirect($redirect);
                            exit;
                        }
                    } else {
                        $morder->notes  = sprintf( $result->error_message . __('. error code: %s', "payro24-paid-memberships-pro") . "\nposted data: %s", $result->error_code, print_r($result, true) );
                        $note           = sprintf( $result->error_message . __('. error code: %s', 'payro24-paid-memberships-pro'), $result->error_code );
                        $morder->status = 'error';
                        $morder->saveOrder();

                        $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                        wp_redirect($redirect);
                        exit;
                    }
                } else {
                    $note           = self::getStatus($status);
                    $note           = sprintf( $note . __(' (status code: %s), track id: %s, order id: %s', 'payro24-paid-memberships-pro'), $status, $track_id, $order_id );
                    $morder->notes  = $note;
                    $morder->status = $status == 7 ? 'cancelled' : 'error';
                    $morder->saveOrder();

                    $redirect = pmpro_url('checkout', '?level=' . $morder->membership_level->id . '&payro24_message='. $note );
                    wp_redirect($redirect);
                    exit;
                }
            }

            public static function do_level_up(&$morder, $txn_id)
            {
                global $wpdb;
                //filter for level
                $morder->membership_level = apply_filters('pmpro_inshandler_level', $morder->membership_level, $morder->user_id);

                //fix expiration date
                if (!empty($morder->membership_level->expiration_number)) {
                    $enddate = "'" . date('Y-m-d', strtotime('+ ' . $morder->membership_level->expiration_number . ' ' . $morder->membership_level->expiration_period, current_time('timestamp'))) . "'";
                } else {
                    $enddate = 'NULL';
                }

                //get discount code
                $morder->getDiscountCode();
                if (!empty($morder->discount_code)) {
                    //update membership level
                    $morder->getMembershipLevel(TRUE);
                    $discount_code_id = $morder->discount_code->id;
                } else {
                    $discount_code_id = '';
                }

                //set the start date to current_time('mysql') but allow filters
                $startdate = apply_filters('pmpro_checkout_start_date', "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);

                //custom level to change user to
                $custom_level = [
                    'user_id'           => $morder->user_id,
                    'membership_id'     => $morder->membership_level->id,
                    'code_id'           => $discount_code_id,
                    'initial_payment'   => $morder->membership_level->initial_payment,
                    'billing_amount'    => $morder->membership_level->billing_amount,
                    'cycle_number'      => $morder->membership_level->cycle_number,
                    'cycle_period'      => $morder->membership_level->cycle_period,
                    'billing_limit'     => $morder->membership_level->billing_limit,
                    'trial_amount'      => $morder->membership_level->trial_amount,
                    'trial_limit'       => $morder->membership_level->trial_limit,
                    'startdate'         => $startdate,
                    'enddate'           => $enddate,
                ];

                global $pmpro_error;
                if (!empty($pmpro_error)) {
                    echo $pmpro_error;
                    inslog($pmpro_error);
                }

                if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== FALSE) {
                    //update order status and transaction ids
                    $morder->status = 'success';
                    $morder->payment_transaction_id = $txn_id;
                    $morder->subscription_transaction_id = '';
                    $morder->saveOrder();

                    //add discount code use
                    if (!empty($discount_code) && !empty($use_discount_code)) {
                        $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time('mysql') . "')");
                    }

                    //save first and last name fields
                    if (!empty($_POST['first_name'])) {
                        $old_firstname = get_user_meta($morder->user_id, 'first_name', TRUE);
                        if (!empty($old_firstname)) { //@todo: if not empty??? why
                            update_user_meta($morder->user_id, 'first_name', sanitize_text_field($_POST['first_name']));
                        }
                    }
                    if (!empty($_POST['last_name'])) {
                        $old_lastname = get_user_meta($morder->user_id, 'last_name', TRUE);
                        if (!empty($old_lastname)) { //@todo: if not empty??? why
                            update_user_meta($morder->user_id, 'last_name', sanitize_text_field($_POST['last_name']));
                        }
                    }

                    //hook
                    if (version_compare(PMPRO_VERSION, '2.0', '>=')) {
                        do_action('pmpro_after_checkout', $morder->user_id, $morder); //added $morder param in v2.0
                    } else {
                        do_action('pmpro_after_checkout', $morder->user_id);
                    }

                    //setup some values for the emails
                    if (!empty($morder)) {
                        $invoice = new MemberOrder($morder->id);
                    } else {
                        $invoice = NULL;
                    }

                    $user = get_userdata(intval($morder->user_id));
                    if (empty($user)) {
                        return FALSE;
                    }

                    $user->membership_level = $morder->membership_level;  //make sure they have the right level info
                    //send email to member
                    $pmproemail = new PMProEmail();
                    $pmproemail->sendCheckoutEmail($user, $invoice);

                    //send email to admin
                    $pmproemail = new PMProEmail();
                    $pmproemail->sendCheckoutAdminEmail($user, $invoice);

                    return TRUE;
                } else {
                    return FALSE;
                }
            }

            /**
             * Calls the gateway endpoints.
             *
             * Tries to get response from the gateway for 4 times.
             *
             * @param $url
             * @param $args
             *
             * @return array|\WP_Error
             */
            private static function call_gateway_endpoint($url, $args)
            {
                $number_of_connection_tries = 4;
                while ($number_of_connection_tries) {
                    $response = wp_safe_remote_post($url, $args);
                    if (is_wp_error($response)) {
                        $number_of_connection_tries--;
                        continue;
                    } else {
                        break;
                    }
                }

                return $response;
            }

            /**
             * return description for status.
             *
             * Tries to get response from the gateway for 4 times.
             *
             * @param $url
             * @param $args
             *
             * @return array|\WP_Error
             */
            public function getStatus($status_code){
                switch ($status_code){
                    case 1:
                        return 'پرداخت انجام نشده است';
                        break;
                    case 2:
                        return 'پرداخت ناموفق بوده است';
                        break;
                    case 3:
                        return 'خطا رخ داده است';
                        break;
                    case 4:
                        return 'بلوکه شده';
                        break;
                    case 5:
                        return 'برگشت به پرداخت کننده';
                        break;
                    case 6:
                        return 'برگشت خورده سیستمی';
                        break;
                    case 7:
                        return 'انصراف از پرداخت';
                        break;
                    case 8:
                        return 'به درگاه پرداخت منتقل شد';
                        break;
                    case 10:
                        return 'در انتظار تایید پرداخت';
                        break;
                    case 100:
                        return 'پرداخت تایید شده است';
                        break;
                    case 101:
                        return 'پرداخت قبلا تایید شده است';
                        break;
                    case 200:
                        return 'به دریافت کننده واریز شد';
                        break;
                }
            }
        }
    }
}
