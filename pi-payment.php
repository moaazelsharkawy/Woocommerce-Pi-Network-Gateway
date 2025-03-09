<?php
/*
Plugin Name: WooCommerce Pi Network Gateway
Plugin URI: https://salla-shop.com
Description: Pi Network Payment Gateway for WooCommerce
Version: 1.2
Author: Moaaz
Author URI: https://salla-shop.com
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woocommerce-pi-network-gateway
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit; // Prevent direct access

// Register Pi Network currency in WooCommerce
add_filter('woocommerce_currencies', 'register_pi_currency');
function register_pi_currency($currencies) {
    $currencies['PI'] = __('Pi Network', 'woocommerce');
    return $currencies;
}

// Set Pi Network currency symbol
add_filter('woocommerce_currency_symbol', 'add_pi_currency_symbol', 10, 2);
function add_pi_currency_symbol($currency_symbol, $currency) {
    if ($currency === 'PI') {
        $currency_symbol = 'pi';
    }
    return $currency_symbol;
}

function enqueue_font_awesome() {
    if (is_checkout() || is_cart()) { // Load only on checkout or cart pages
        if (!wp_style_is('font-awesome', 'enqueued')) { // Check if not already enqueued
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
        }
    }
}

add_action('wp_enqueue_scripts', 'enqueue_font_awesome');

add_action('plugins_loaded', 'init_pi_payment_gateway');
function load_pi_payment_scripts() {
    if (is_checkout()) {
        error_log('Loading Pi Payment scripts...'); // Log for debugging
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

        // Enqueue styles with dynamic versioning
        wp_enqueue_style(
            'pi-payment-styles', // Unique handle for styles
            plugin_dir_url(__FILE__) . 'pi-payment.css', // Path to stylesheet
            array(), // No dependencies
            filemtime(plugin_dir_path(__FILE__) . 'pi-payment.css') // Dynamic version based on file modification time
        );
    }
}
add_action('wp_enqueue_scripts', 'load_pi_payment_scripts');

function init_pi_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_Pi_Payment extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'pi_payment';
            $this->method_title = 'Pi Network Payment';
            $this->method_description = 'Pi Network Payment Gateway for purchasing with Pi cryptocurrency.';
            $this->has_fields = true;
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->pi_address = $this->get_option('pi_address');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'type'        => 'checkbox',
                    'label'       => 'Enable Pi Network Gateway',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Pi Network Payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Send the order amount to the following Pi Network address:',
                ),
                'pi_address' => array(
                    'title'       => 'Pi Network Address',
                    'type'        => 'text',
                    'description' => 'The address where payments will be sent.',
                    'default'     => '',
                ),
            );
        }

        public function payment_fields() {
            echo '<div class="pi-payment-container">';
            
            echo '<button type="button" id="pi-instructions-btn" class="pi-instructions-btn">
                    <img src="' . plugins_url('assets/salla-shop-pi.jpg', __FILE__) . '" alt="Payment Instructions">
                    Payment Instructions
                  </button>';

            // Display payment amount for copying (copy only the number)
            $total = strip_tags(WC()->cart->get_total()); 
            echo '<div class="pi-payment-item">';
            echo '<p><strong>Order Amount: <span id="pi-payment-value">' . esc_html($total) . '</span></strong>';
            echo ' <button type="button" class="copy-btn" data-copy-target="pi-payment-value">Copy Amount</button></p>';
            echo '</div>';

            // Hide the address but make it copyable (copy full text)
            echo '<div class="pi-payment-item">';
            echo '<p><strong>Store Address: <span id="pi-payment-address" style="display:none;">' . esc_html($this->pi_address) . '</span></strong>';
            echo ' <button type="button" class="copy-btn" data-copy-target="pi-payment-address">Copy Address</button></p>';
            echo '</div>';

            echo '<label for="pi_transaction_hash" style="color: #6f42c1;">Enter Transaction Hash:</label>';
            echo '<input type="text" id="pi_transaction_hash" name="pi_transaction_hash" class="input-text" required />';
            echo '</div>';

            // Display question mark and GitHub icon
            echo '<div class="tooltip-container">';
            echo '<i class="fas fa-question-circle tooltip-icon"></i>'; // Question mark 

            // GitHub icon with link
            echo '<a href="https://github.com/moaazelsharkawy/Woocommerce-Pi-Network-Gateway" target="_blank" class="github-link">';
            echo '<i class="fab fa-github github-icon"></i>'; // GitHub icon from Font Awesome
            echo '</a>';
            echo '</div>';

            // Display "See how to pay" in a separate line
            echo '<div class="payment-help">';
            echo '<span class="help-text" id="video-help">See how to pay</span>';
            echo '</div>';

            // Include script directly in the payment fields
            ?>
            <script>
                function copyText(text, message) {
                    var tempInput = document.createElement("input");
                    tempInput.setAttribute("type", "text");
                    tempInput.setAttribute("value", text);
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand("copy");
                    document.body.removeChild(tempInput);

                    // Show success message using SweetAlert
                    Swal.fire({
                        title: "Copied Successfully",
                        text: message,
                        icon: "success",
                        confirmButtonText: "OK"
                    });
                }

                jQuery(document).ready(function($) {
                    // Use jQuery for proper interaction
                    $(document).on('click', '.copy-btn', function() {
                        var targetId = $(this).data('copy-target');
                        var element = $('#' + targetId);

                        if (element.length) {
                            var value = element.text().trim();
                            var message = ""; // Custom message

                            if (targetId === 'pi-payment-value') {
                                // Copy only the number when clicking "Copy Amount"
                                value = value.replace(/[^\d.-]/g, ''); // Keep numbers and decimal points
                                message = "Payment amount copied to clipboard!";
                            } else if (targetId === 'pi-payment-address') {
                                // Copy the address
                                message = "Payment address copied to clipboard!";
                            }

                            copyText(value, message); // Copy text with custom message
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Target element for copying not found.',
                            });
                        }
                    });

                    $('#pi-instructions-btn').on('click', function() {
                        let instructionsText = `<?php echo esc_js($this->description); ?>`; // Fetch text from settings

                        // Split text into lines and convert to an ordered list
                        let instructionsArray = instructionsText.split("\n"); // Split text into lines
                        let orderedList = "<ol style='text-align:left; direction:ltr; font-size:1em; line-height:1.6;'>";
                        
                        instructionsArray.forEach(line => {
                            if (line.trim() !== "") { // Ignore empty lines
                                orderedList += `<li>${line.trim()}</li>`;
                            }
                        });

                        orderedList += "</ol>";

                        Swal.fire({
                            title: "Quick Payment Instructions",
                            html: orderedList,
                            icon: "info",
                            confirmButtonText: "OK",
                            width: 320,
                            heightAuto: false,
                            customClass: {
                                popup: 'pi-instructions-popup'
                            }
                        });
                    });

                    // Show hidden text when clicking the question mark
                    $('.tooltip-icon').on('click', function() {
                        Swal.fire({
                            title: 'Gateway Information',
                            text: 'This gateway is secure and easy to use, developed by Salla Developer, Version V1.2, and connected to Pi Blockchain API.',
                            icon: 'info',
                            confirmButtonText: 'Close'
                        });
                    });

                    // When clicking "See how to pay"
                    $('#video-help').on('click', function() {
                        Swal.fire({
                            title: 'How to Pay',
                            html: '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">' +
                                  '<iframe src="https://www.youtube.com/embed/NZ9uLrEdoPA" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allowfullscreen></iframe>' +
                                  '</div>',
                            width: 800,
                            showCloseButton: true,
                            showConfirmButton: false,
                        });
                    });
                });
            </script>
            <?php
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $transaction_hash = sanitize_text_field($_POST['pi_transaction_hash']);

            // Validate the hash
            if (empty($transaction_hash) || !preg_match('/^[a-f0-9]{64}$/', $transaction_hash)) {
                wc_add_notice('Invalid transaction hash. Please ensure you entered the correct data. For help, click on "See how to pay".', 'error');
                return;
            }

            // Check if the hash has been used before
            $args = array(
                'meta_key'    => '_pi_transaction_hash',
                'meta_value'  => $transaction_hash,
                'post_type'   => 'shop_order',
                'post_status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
                'posts_per_page' => -1,
            );
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                wc_add_notice('This hash has been used in a previous order. Please use a new payment hash.', 'error');
                return;
            }

            // Save the hash in the order
            $order->update_meta_data('_pi_transaction_hash', $transaction_hash);
            $order->save();

            // Build the verification URL using the hash
            $url = "https://api.mainnet.minepi.com/transactions/$transaction_hash";
            
            // Send a GET request to fetch transaction details
            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                wc_add_notice('Error verifying payment. Please try again.', 'error');
                return;
            }

            // Parse the response data
            $transaction_data = json_decode(wp_remote_retrieve_body($response), true);

            // Check if the transaction exists and is valid
            if (empty($transaction_data) || !isset($transaction_data['hash'])) {
                wc_add_notice('Failed to connect to Pi Network server. Please try again.', 'error');
                return;
            }

            // Verify that the hash matches
            if ($transaction_data['hash'] !== $transaction_hash) {
                wc_add_notice('Transaction hash does not match the entered hash. Please ensure you entered the correct data.', 'error');
                return;
            }

            // Build the payment verification URL for the address
            $payments_url = "https://api.mainnet.minepi.com/accounts/{$this->pi_address}/payments?order=desc&include_failed=false";

            // Send a GET request to fetch payment records
            $response = wp_remote_get($payments_url);
            
            if (is_wp_error($response)) {
                wc_add_notice('Error connecting to the verification server. Please try again.', 'error');
                return;
            }

            $payments_data = json_decode(wp_remote_retrieve_body($response), true);

            // Check if payment data exists
            if (empty($payments_data['_embedded']['records'])) {
                wc_add_notice('No payments recorded for this address.', 'error');
                return;
            }

            // Search for the transaction in the payment records
            $transaction_found = false;
            foreach ($payments_data['_embedded']['records'] as $payment) {
                if ($payment['transaction_hash'] === $transaction_hash) {
                    $transaction_found = true;
                    
                    // Verify that the payment is directed to this address
                    if ($payment['to'] !== $this->pi_address) {
                        wc_add_notice('Transaction is not directed to the store address.', 'error');
                        return;
                    }

                    // Verify the amount matches
                    $expected_amount = floatval($order->get_total());
                    $actual_amount   = floatval($payment['amount']);

                    // Calculate the allowed variance (5% of the expected amount)
                    $allowed_variance = $expected_amount * 0.05;

                    // Check if the difference between the amounts is within the allowed margin
                    if (abs($actual_amount - $expected_amount) > $allowed_variance) {
                        wc_add_notice('The paid amount does not match the order amount.', 'error');
                        return;
                    }

                    break;
                }
            }

            if (!$transaction_found) {
                wc_add_notice('Transaction not found in the records of the specified address.', 'error');
                return;
            }

            // Verify the transaction status
            if ($transaction_data['successful'] !== true) {
                wc_add_notice('Transaction was not successful.', 'error');
                return;
            }

            // Verify the transaction time against the order creation time (10 minutes only)
            $transaction_time = strtotime($transaction_data['created_at']); // Transaction creation time
            $order_time = strtotime($order->get_date_created()); // Order creation time

            // Calculate the time difference in seconds
            $time_difference = abs($transaction_time - $order_time);

            // If the difference is more than 10 minutes (600 seconds), reject the transaction
            if ($time_difference > 600) {
                wc_add_notice('The transaction hash entered exceeds the allowed time difference (10 minutes) between the transaction time and the order confirmation time. If you have already paid and your internet was disconnected, please contact support via WhatsApp.', 'error');
                return;
            }

            // If the transaction is valid, complete the payment process
            $order->update_status('on-hold');  // Change status to "On Hold"    
    
            // Add a note with the hash to the order
            $order->add_order_note('Payment confirmed via Pi Network.<br>Transaction Hash: ' . $transaction_hash);

            // Redirect the user to the thank you page
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        private function validate_transaction($transaction_data, $transaction_hash, $order) {
            $expected_address = $this->get_option('pi_address');
            $expected_amount = floatval($order->get_total());

            return isset($transaction_data['hash'], $transaction_data['to'], $transaction_data['amount'], $transaction_data['status']) &&
                   $transaction_data['hash'] === $transaction_hash &&
                   $transaction_data['to'] === $expected_address &&
                   floatval($transaction_data['amount']) === $expected_amount &&
                   $transaction_data['status'] === 'successful';
        }
    }
}

function add_pi_gateway_class($methods) {
    $methods[] = 'WC_Gateway_Pi_Payment';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_pi_gateway_class');

function display_pi_transaction_hash_in_admin_order($order) {
    $transaction_hash = $order->get_meta('_pi_transaction_hash', true);
    if ($transaction_hash) {
        $split_hash = wordwrap($transaction_hash, 32, "<br>", true);
        echo '<p><strong>' . __('Pi Transaction Hash', 'text-domain') . ':</strong> <span id="pi_transaction_hash_display">' . $split_hash . '</span></p>';
    } else {
        echo '<p><strong>' . __('Pi Transaction Hash', 'text-domain') . ':</strong> No hash</p>';
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'display_pi_transaction_hash_in_admin_order');
