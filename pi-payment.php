

<?php
/*
Plugin Name: WooCommerce Pi Network Gateway
Plugin URI: https://salla-shop.com
Description: بوابة دفع Pi Network لمتجر WooCommerce
Version: 1.4
Author: Moaaz
Author URI: https://salla-shop.com
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woocommerce-pi-network-gateway
Domain Path: /languages
*/


if (!defined('ABSPATH')) exit; // حماية من الوصول المباشر

// تسجيل عملة Pi Network في WooCommerce
add_filter('woocommerce_currencies', 'register_pi_currency');
function register_pi_currency($currencies) {
    $currencies['PI'] = __('Pi Network', 'woocommerce');
    return $currencies;
}

// تحديد رمز عملة Pi Network
add_filter('woocommerce_currency_symbol', 'add_pi_currency_symbol', 10, 2);
function add_pi_currency_symbol($currency_symbol, $currency) {
    if ($currency === 'PI') {
        $currency_symbol = 'pi';
    }
    return $currency_symbol;
}

function enqueue_font_awesome() {
    if (is_checkout() || is_cart()) { // تحميلها فقط في صفحة الدفع أو السلة
        if (!wp_style_is('font-awesome', 'enqueued')) { // التحقق من عدم تحميلها مسبقًا
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
        }
    }
}

add_action('wp_enqueue_scripts', 'enqueue_font_awesome');

add_action('plugins_loaded', 'init_pi_payment_gateway');

function load_pi_payment_scripts() {
    if (is_checkout()) {
        error_log('تحميل ملفات Pi Payment...'); // سجل رسالة للتحقق
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

        // استدعاء ملف التنسيقات مع إصدار ديناميكي
        wp_enqueue_style(
            'pi-payment-styles', // المعرف الفريد للتنسيقات
            plugin_dir_url(__FILE__) . 'pi-payment.css', // المسار إلى ملف التنسيقات
            array(), // لا توجد تبعيات
            filemtime(plugin_dir_path(__FILE__) . 'pi-payment.css') // إصدار ديناميكي بناءً على وقت تعديل الملف
        );
    }
}
add_action('wp_enqueue_scripts', 'load_pi_payment_scripts');

// استدعاء ملف إرسال الإشعارات إلى Telegram
include plugin_dir_path(__FILE__) . 'telegram-notifications.php';



function init_pi_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_Pi_Payment extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'pi_payment';
            $this->method_title = 'Pi Network Payment';
            $this->method_description = 'بوابة دفع Pi Network للشراء عبر العملة الرقمية Pi.';
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
                    'title'       => 'تفعيل/تعطيل',
                    'type'        => 'checkbox',
                    'label'       => 'تفعيل Pi Network Gateway',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => 'العنوان',
                    'type'        => 'text',
                    'description' => 'العنوان الذي يظهر في صفحة الدفع.',
                    'default'     => 'Pi Network Payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'الوصف',
                    'type'        => 'textarea',
                    'description' => 'الوصف الذي يظهر للمستخدم في صفحة الدفع.',
                    'default'     => 'أرسل قيمة الطلب الي عنوان Pi Network التالي:',
                ),
                'pi_address' => array(
                    'title'       => 'عنوان Pi Network',
                    'type'        => 'text',
                    'description' => 'العنوان الذي سيتم إرسال الدفع إليه.',
                    'default'     => '',
                ),
            );
        }

public function payment_fields() {
    echo '<div class="pi-payment-container">';
    
echo '<button type="button" id="pi-instructions-btn" class="pi-instructions-btn">
        <img src="' . plugins_url('assets/salla-shop-pi.jpg', __FILE__) . '" alt="تعليمات الدفع">
        تعليمات للدفع
      </button>';


    
// عرض قيمة الدفع للنسخ (نسخ الرقم فقط)
 
$total = strip_tags(WC()->cart->get_total()); 



echo '<div class="pi-payment-item">';
echo '<p>
        <strong>الاجمالي :</strong>
        <span id="pi-payment-value" class="order-value">' . esc_html($total) . '</span>
        <button type="button" class="copy-btn" data-copy-target="pi-payment-value">
          <i class="fas fa-copy"></i>
          <span class="copy-feedback"></span>
        </button>
      </p>';
echo '</div>';

echo '<div class="pi-payment-item">';
echo '<p>
        <strong>عنوان المول:</strong>
        <span id="pi-payment-address">' . esc_html(substr($this->pi_address, 0, 4)) . '</span>
        <span id="pi-payment-address-full" style="display: none;">' . esc_html($this->pi_address) . '</span>
        <button type="button" class="copy-btn" data-copy-target="pi-payment-address-full">
          <i class="fas fa-copy"></i>
          <span class="copy-feedback"></span>
        </button>
      </p>';
echo '</div>';

echo '<label for="pi_transaction_hash" style="color: #000;">أدخل هاش المعاملة:</label>';
echo '<input type="text" id="pi_transaction_hash" name="pi_transaction_hash" class="input-text" placeholder="Transaction" required />';


// تعديل عرض علامة الاستفهام وأيقونة GitHub
echo '<div class="tooltip-container">';
echo '<i class="fas fa-question-circle tooltip-icon"></i>'; // علامة الاستفهام 

// أيقونة GitHub مع الرابط
echo '<a href="https://github.com/moaazelsharkawy/Woocommerce-Pi-Network-Gateway" target="_blank" class="github-link">';
echo '<i class="fab fa-github github-icon"></i>'; // أيقونة GitHub من Font Awesome
echo '</a>';
echo '</div>';


    // تضمين السكربت مباشرة في الـ payment fields
    ?>
   
   <script>
    
function copyText(text) {
if (navigator.clipboard && window.isSecureContext) {
return navigator.clipboard.writeText(text);
} else {
var tempInput = document.createElement("input");
tempInput.value = text;
document.body.appendChild(tempInput);
tempInput.select();
document.execCommand("copy");
document.body.removeChild(tempInput);
return Promise.resolve();
}
}

jQuery(document).ready(function($) {
$(document).on('click', '.copy-btn', function() {
var targetId = $(this).data('copy-target'); // الحصول على الـ ID للعنصر المستهدف
var element = $('#' + targetId); // البحث عن العنصر باستخدام الـ ID
var $btn = $(this); // حفظ الزر الذي تم النقر عليه

if (element.length) {
var value = element.text().trim();

// التحقق مما إذا كان العنصر المستهدف هو "pi-payment-value" لإزالة الرموز
if (targetId === "pi-payment-value") {
value = value.replace(/[^0-9.]/g, ''); // الاحتفاظ بالأرقام والفاصلة العشرية فقط
}

var message = "تم النسخ بنجاح!";

copyText(value)
.then(function() {
console.log("Copy succeeded.");
var $feedback = $btn.find('.copy-feedback');
$feedback.text(message).css({'opacity':'1','display':'block'});
setTimeout(function() {
$feedback.fadeOut(300, function() {
$(this).text('').css({'opacity':'0'});
});
}, 2000);
})
.catch(function(err) {
console.error("Copy failed:", err);
var $feedback = $btn.find('.copy-feedback');
$feedback.text("فشل النسخ.").css({'opacity':'1','display':'block'});
setTimeout(function() {
$feedback.fadeOut(300, function() {
$(this).text('').css({'opacity':'0'});
});
}, 2000);
});

// تغيير الأيقونة مؤقتاً من fa-copy إلى fa-check
$btn.find('i').removeClass('fa-copy').addClass('fa-check');
setTimeout(function(){
$btn.find('i').removeClass('fa-check').addClass('fa-copy');
}, 1500);
} else {
console.log("Target element not found for copying.");
}
});





    $('#pi-instructions-btn').on('click', function() {
    let instructionsText = `<?php echo esc_js($this->description); ?>`; // جلب النص من الإعدادات

    // تقسيم النص إلى أسطر وتحويله إلى قائمة مرتبة
    let instructionsArray = instructionsText.split("\n"); // تقسيم النص إلى أسطر
    let orderedList = "<ol style='text-align:right; direction:rtl; font-size:1em; line-height:1.6;'>";
    
    instructionsArray.forEach(line => {
        if (line.trim() !== "") { // تجاهل الأسطر الفارغة
            orderedList += `<li>${line.trim()}</li>`;
        }
    });

    orderedList += "</ol>";

    Swal.fire({
        title: "تعليمات سريعة للدفع",
        html: orderedList,
        icon: "info",
        confirmButtonText: "تم",
        width: 320,
        heightAuto: false,
        customClass: {
            popup: 'pi-instructions-popup'
        }
    });
});




let isSwalOpen = false; // متغير لتتبع حالة نافذة SweetAlert

$('form.checkout').on('submit', function(e) {
    if ($('#payment_method_pi_payment').is(':checked')) {
        if (isSwalOpen) {
            return false; // إذا كانت نافذة SweetAlert مفتوحة بالفعل، توقف
        }
        isSwalOpen = true; // تعيين حالة النافذة إلى "مفتوحة"

        // إظهار نافذة التحميل مع أيقونة الإغلاق وعدم الإغلاق التلقائي
        Swal.fire({
            title: 'التحقق من الدفع',
            html: ' يرجى الانتظار، جاري التحقق من الدفع مع Pi Blockchain...  <i class="fa-solid fa-triangle-exclamation" style="color: #FFA500; font-size: 20px;"></i> لا تغلق النافذة إلا في حال ظهور خطأ.',
    icon: 'warning',
            showConfirmButton: false,
            showCloseButton: true, // عرض أيقونة الإغلاق
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: $('form.checkout').serialize(),
            success: function(response) {
                isSwalOpen = false;
                if (response.result !== 'error') {
                    window.location.href = response.redirect;
                }
            },
            error: function() {
                isSwalOpen = false;
            }
        });

        return false; // منع إعادة تحميل الصفحة
    }
});



    



$('.tooltip-icon').on('click', function() {
    Swal.fire({         title: 'معلومات عن البوابة',         text: 'هذه البوابة آمنة وسهلة، مطورة من Salla Developer، الإصدار V1.4، ومتصلة بـ Pi Blockchain API.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'شرح الدفع',
        cancelButtonText: 'إغلاق'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'كيف يتم الدفع',
                html: '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">' +
      '<iframe src="https://www.youtube.com/embed/pcEc-27r_rI" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allowfullscreen></iframe>' +
      '</div>',

                width: 800,
                showCloseButton: true,
                showConfirmButton: false,
            });
        }
    });
});


    });
</script>

    <?php
}



   public function process_payment($order_id) {
    $order = wc_get_order($order_id);
    $transaction_hash = sanitize_text_field($_POST['pi_transaction_hash']);

    // التحقق من صحة الهاش
    if (empty($transaction_hash) || !preg_match('/^[a-f0-9]{64}$/', $transaction_hash)) {
        wc_add_notice('هاش المعاملة غير صالح. تأكد من إدخال الهاش بشكل صحيح.', 'error');
        return array('result' => 'error'); // إرجاع false لإيقاف العملية
    }

    // التحقق من عدم استخدام الهاش مسبقًا
    $args = array(
        'meta_key'    => '_pi_transaction_hash',
        'meta_value'  => $transaction_hash,
        'post_type'   => 'shop_order',
        'post_status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
        'posts_per_page' => -1,
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        wc_add_notice('تم استخدام هذا الهاش في طلب سابق. يرجى استخدام هاش الدفع الجديد.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    // حفظ الهاش في الطلب
    $order->update_meta_data('_pi_transaction_hash', $transaction_hash);
    $order->save();

    // بناء رابط التحقق باستخدام الهاش
    $url = "https://api.mainnet.minepi.com/transactions/$transaction_hash";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wc_add_notice('خطأ في التحقق من الدفع. حاول مرة أخرى.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    $transaction_data = json_decode(wp_remote_retrieve_body($response), true);

    // التحقق من أن المعاملة صحيحة
    if (empty($transaction_data) || !isset($transaction_data['hash'])) {
        wc_add_notice('فشل الاتصال بخادم Pi Network. حاول مرة أخرى ولا تستخدم PI TEST.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    if ($transaction_data['hash'] !== $transaction_hash) {
        wc_add_notice('هاش المعاملة لا يتطابق مع المدخل.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    if ($transaction_data['successful'] !== true) {
        wc_add_notice('المعاملة غير ناجحة.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    // التحقق من المدفوعات الموجهة للعنوان
    $payments_url = "https://api.mainnet.minepi.com/accounts/{$this->pi_address}/payments?order=desc&include_failed=false";
    $response = wp_remote_get($payments_url);

    if (is_wp_error($response)) {
        wc_add_notice('خطأ في الاتصال بخادم التحقق.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    $payments_data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($payments_data['_embedded']['records'])) {
        wc_add_notice('لا توجد مدفوعات مسجلة لهذا العنوان.', 'error');
        return array('result' => 'error');// إرجاع false لإيقاف العملية
    }

    // البحث عن المعاملة في قائمة المدفوعات
    $transaction_found = false;
    foreach ($payments_data['_embedded']['records'] as $payment) {
        if ($payment['transaction_hash'] === $transaction_hash) {
            $transaction_found = true;

            if ($payment['to'] !== $this->pi_address) {
                wc_add_notice('المعاملة غير موجهة لعنوان المول.', 'error');
                return array('result' => 'error'); // إرجاع false لإيقاف العملية
            }

            // جلب المبالغ
            $expected_amount = floatval($order->get_total());
            $actual_amount   = floatval($payment['amount']);

            // حساب هامش التفاوت
            $max_acceptable_difference = $expected_amount * 0.05; // 5٪
            $low_acceptable_difference = $expected_amount * 0.02; // 2٪

            // التحقق من الفرق بين المبلغين
            $difference = $expected_amount - $actual_amount;

            if ($difference <= $low_acceptable_difference) {
                $order->update_status('processing');
                $order->add_order_note('تم تأكيد الدفع.<br>هاش المعاملة: ' . $transaction_hash);
            } elseif ($difference > $low_acceptable_difference && $difference <= $max_acceptable_difference) {
                $order->update_status('price-diff');
                $order->add_order_note('تم استلام دفعة أقل بقليل من المطلوب (' . number_format($actual_amount, 2) . ' Pi). يرجى مراجعة الإدارة.');
            } else {
                // إذا كان الفرق أكبر من 5٪ → وضع الطلب في حالة "قيد الانتظار" بدلاً من الرفض
                $order->update_status('on-hold');
                $order->add_order_note('قيمة الدفع أقل بنسبة كبيرة من قيمة الطلب الحالي. راسل الدعم لدفع الفرق أو طلب استرداد.');

                // إرسال بريد إلكتروني للعميل
                $customer_email = $order->get_billing_email();
                $subject = "إشعار: دفعة غير مكتملة لطلبك #" . $order->get_id();
                $message = "عزيزي العميل،\n\nلقد تم استلام دفعة لطلبك ولكن المبلغ المدفوع أقل من المطلوب بنسبة كبيرة. يرجى التواصل مع الدعم لدفع الفرق أو طلب استرداد.\n\nهاش المعاملة: " . $transaction_hash . "\n\nشكراً لتعاملك معنا.";
                wp_mail($customer_email, $subject, $message);

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
            break;
        }
    }

    if (!$transaction_found) {
        wc_add_notice('لم يتم العثور على المعاملة في سجلات العنوان.', 'error');
        return array('result' => 'error'); // إرجاع false لإيقاف العملية
    }

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
        echo '<p><strong>' . __('Pi Transaction Hash', 'text-domain') . ':</strong> لا يوجد هاش</p>';
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'display_pi_transaction_hash_in_admin_order');


add_action('woocommerce_order_refunded', 'update_order_status_to_refunded', 10, 2);

function update_order_status_to_refunded($order_id, $refund_id) {
    $order = wc_get_order($order_id);

    // التأكد أن حالة الطلب ليست "مسترد" بالفعل
    if ($order->get_status() !== 'refunded') {
        $order->update_status('refunded', 'تم استرداد المبلغ بالكامل للعميل.');
    }
}


// إرسال إشعار عند تغيير حالة الطلب أو إضافة ملاحظة
add_action('woocommerce_order_status_changed', 'send_order_notification_to_telegram', 10, 4);
add_action('woocommerce_order_note_added', 'send_order_note_notification_to_telegram', 10, 2);

function send_order_notification_to_telegram($order_id, $old_status, $new_status, $order) {
    $user_id = $order->get_user_id();
    $telegram_username = get_user_meta($user_id, 'telegram_username', true);

    if (empty($telegram_username)) {
        error_log('لم يتم العثور على يوزر Telegram للمستخدم: ' . $user_id);
        return;
    }

    // بيانات الطلب
    $order_total = $order->get_total();
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name = $order->get_billing_last_name();
    $billing_email = $order->get_billing_email();
    $shipping_address = $order->get_formatted_shipping_address();

    // إزالة علامات <br/> من عنوان الشحن
    $shipping_address = str_replace('<br/>', "\n", $shipping_address);

    // إذا لم يكن عنوان الشحن متاحًا، استخدم عنوان الفاتورة
    if (empty($shipping_address)) {
        $shipping_address = $order->get_formatted_billing_address();
        $shipping_address = str_replace('<br/>', "\n", $shipping_address);
    }

    // تفاصيل المنتجات
    $products_details = "🛍️ المنتجات المطلوبة:\n";
    foreach ($order->get_items() as $item_id => $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $products_details .= " - {$product_name} (الكمية: {$quantity})\n";
    }

    // الحصول على أسماء حالات الطلب
    $order_statuses = wc_get_order_statuses();

    // بناء الرسالة
    $message = "🛒 تفاصيل الطلب #{$order_id}\n\n";
    $message .= "👤 pioneer: {$billing_first_name} {$billing_last_name}\n";
    $message .= "📧 البريد الإلكتروني: {$billing_email}\n\n";
    $message .= "📦 بيانات الشحن:\n";
    $message .= "{$shipping_address}\n\n";
    $message .= $products_details . "\n"; // إضافة تفاصيل المنتجات
    $message .= "💳 قيمة الطلب: {$order_total} Pi\n"; // إضافة رمز العملة Pi
    $message .= "🔍 حالة الطلب: قيد التنفيذ (الفرق حتى 2%)\n\n";
    $message .= "📢 تم تحديث حالة طلبك:\n";
    $message .= "🔄 الحالة السابقة: " . $order_statuses['wc-' . $old_status] . "\n";
    $message .= "✅ الحالة الجديدة: " . $order_statuses['wc-' . $new_status] . "\n\n";
    $message .= "شكرًا لثقتك بمول سلة شوب باي! 🎉";

    if (send_telegram_notification($telegram_username, $message)) {
        error_log('تم إرسال الإشعار بنجاح للطلب #' . $order_id);
    } else {
        error_log('فشل إرسال الإشعار للطلب #' . $order_id);
    }
}

function send_order_note_notification_to_telegram($order_id, $note_id) {
    // الحصول على كائن الطلب
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("لم يتم العثور على الطلب #" . $order_id);
        return;
    }

    // الحصول على كائن الملاحظة
    $note = wc_get_order_note($note_id);
    if (!$note) {
        error_log("لم يتم العثور على الملاحظة #" . $note_id);
        return;
    }

    // التحقق من أن الملاحظة ليست للعميل
    if ($note->customer_note) {
        return; // إذا كانت الملاحظة للعميل، لا ترسل إشعارًا
    }

    // الحصول على معلومات المستخدم
    $user_id = $order->get_user_id();
    $telegram_username = get_user_meta($user_id, 'telegram_username', true);

    if (empty($telegram_username)) {
        error_log("لم يتم العثور على يوزر Telegram للمستخدم: " . $user_id);
        return;
    }

    // بناء رسالة الملاحظة
    $message = "🔔 تحديث جديد على طلبك #{$order_id}:\n\n";
    $message .= "📝 {$note->content}\n\n"; // نص الملاحظة
    $message .= "شكراً لتعاملك معنا!";

    // إرسال الإشعار
    if (send_telegram_notification($telegram_username, $message)) {
        error_log("تم إرسال إشعار الملاحظة بنجاح للطلب #" . $order_id);
    } else {
        error_log("فشل إرسال إشعار الملاحظة للطلب #" . $order_id);
    }
}

