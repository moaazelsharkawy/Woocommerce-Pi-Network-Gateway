  <?php
// telegram-notifications.php

if (!defined('ABSPATH')) exit; // حماية من الوصول المباشر

// دالة إرسال الإشعارات إلى Telegram باستخدام username
function send_telegram_notification($username, $message) {
    $bot_token = 'your api key here'; // استبدل ب token البوت الخاص بك

    // جلب chat_id من username
    $chat_id = get_chat_id_from_username($username);
    if (empty($chat_id)) {
        error_log('فشل في الحصول على chat_id لليوزر: ' . $username);
        return false;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log('فشل إرسال الإشعار إلى Telegram.');
        return false;
    }

    return true;
}

// دالة لتحويل username إلى chat_id
function get_chat_id_from_username($username) {
    $bot_token = 'your bot api key here'; // استبدل ب token البوت الخاص بك

    // جلب chat_id من username باستخدام getUpdates
    $url = "https://api.telegram.org/bot{$bot_token}/getUpdates";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (isset($data['result'])) {
        foreach ($data['result'] as $update) {
            if (isset($update['message']['from']['username'])) {
                if ($update['message']['from']['username'] === str_replace('@', '', $username)) {
                    return $update['message']['from']['id'];
                }
            }
        }
    }

    return null; // إذا لم يتم العثور على chat_id
}

// إضافة حقل إدخال يوزر Telegram إلى صفحة إعدادات الحساب
add_action('woocommerce_edit_account_form', 'add_telegram_username_field');
function add_telegram_username_field() {
    $user_id = get_current_user_id();
    $telegram_username = get_user_meta($user_id, 'telegram_username', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="telegram_username">يوزر Telegram:</label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="telegram_username" id="telegram_username" value="<?php echo esc_attr($telegram_username); ?>" />
    </p>
    <?php
}

// حفظ قيمة اليوزر عند تحديث الحساب
add_action('woocommerce_save_account_details', 'save_telegram_username_field');
function save_telegram_username_field($user_id) {
    if (isset($_POST['telegram_username'])) {
        $telegram_username = sanitize_text_field($_POST['telegram_username']);
        update_user_meta($user_id, 'telegram_username', $telegram_username);
        error_log('تم حفظ يوزر Telegram: ' . $telegram_username . ' للمستخدم: ' . $user_id);
    }
}
 
