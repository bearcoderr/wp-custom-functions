<?php
/*
Plugin Name: Система ступенчатых скидок
Description: Добавляет автоматические скидки в корзину на основе суммы заказа с настройкой диапазонов и купонов
Version: 1.5
Author: Alekseii Achkasov
Text Domain: bearcoder.ru
*/

// Предотвращаем прямой доступ к файлу
if (!defined('ABSPATH')) {
    exit;
}

// Добавляем страницу настроек в меню WooCommerce
add_action('admin_menu', 'td_register_settings_page');
function td_register_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Настройки ступенчатых скидок',
        'Ступенчатые скидки',
        'manage_woocommerce',
        'tiered-discounts',
        'td_settings_page_content'
    );
}

// Регистрируем настройки
add_action('admin_init', 'td_register_settings');
function td_register_settings() {
    register_setting('td_settings_group', 'td_discount_ranges');
    register_setting('td_settings_group', 'td_allowed_coupons');
    register_setting('td_settings_group', 'td_coupon_mode');
    register_setting('td_settings_group', 'td_is_active');
}

// Страница настроек
function td_settings_page_content() {
    // Сохранение данных
    if (isset($_POST['td_save_settings'])) {
        if (!check_admin_referer('td_save_settings_nonce')) {
            echo '<div class="error"><p>Ошибка проверки безопасности. Настройки не сохранены.</p></div>';
        } else {
            $ranges = array();
            if (
                isset($_POST['td_range_min']) &&
                isset($_POST['td_range_value']) &&
                isset($_POST['td_range_type']) &&
                is_array($_POST['td_range_min']) &&
                count($_POST['td_range_min']) === count($_POST['td_range_value']) &&
                count($_POST['td_range_value']) === count($_POST['td_range_type'])
            ) {
                $mins = $_POST['td_range_min'];
                $values = $_POST['td_range_value'];
                $types = $_POST['td_range_type'];
                for ($i = 0; $i < count($mins); $i++) {
                    if (is_numeric($mins[$i]) && is_numeric($values[$i])) {
                        $ranges[] = array(
                            'min' => floatval($mins[$i]),
                            'value' => floatval($values[$i]),
                            'type' => in_array($types[$i], ['percent', 'fixed']) ? $types[$i] : 'percent'
                        );
                    }
                }
            }
            update_option('td_discount_ranges', $ranges);

            $coupons = isset($_POST['td_coupon']) && is_array($_POST['td_coupon'])
                ? array_filter(array_map('trim', $_POST['td_coupon']))
                : array();
            update_option('td_allowed_coupons', $coupons);

            $coupon_mode = isset($_POST['td_coupon_mode'])
                ? sanitize_text_field($_POST['td_coupon_mode'])
                : 'exclude';
            update_option('td_coupon_mode', $coupon_mode);

            echo '<div class="updated"><p>Настройки успешно сохранены!</p></div>';
        }
    }

    // Обработка активации/деактивации
    if (isset($_POST['td_activate'])) {
        if (check_admin_referer('td_activate_nonce')) {
            update_option('td_is_active', 'yes');
            echo '<div class="updated"><p>Функционал активирован!</p></div>';
        }
    } elseif (isset($_POST['td_deactivate'])) {
        if (check_admin_referer('td_deactivate_nonce')) {
            update_option('td_is_active', 'no');
            echo '<div class="updated"><p>Функционал деактивирован!</p></div>';
        }
    }

    // Получаем текущие значения
    $ranges = get_option('td_discount_ranges', array());
    $coupons = get_option('td_allowed_coupons', array());
    $coupon_mode = get_option('td_coupon_mode', 'exclude');
    $is_active = get_option('td_is_active', 'yes');

    if (empty($coupons)) {
        $coupons = array('');
    }
    if (empty($ranges)) {
        $ranges = array(array('min' => 0, 'value' => 0, 'type' => 'percent'));
    }
    ?>
    <div class="wrap">
        <h1>Настройки ступенчатых скидок</h1>

        <!-- Форма активации/деактивации -->
        <div style="margin-bottom: 20px;">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('td_activate_nonce'); ?>
                <input type="hidden" name="td_activate" value="1">
                <input type="submit" class="button button-primary" value="Активировать" <?php echo $is_active === 'yes' ? 'disabled' : ''; ?>>
            </form>
            <form method="post" style="display: inline; margin-left: 10px;">
                <?php wp_nonce_field('td_deactivate_nonce'); ?>
                <input type="hidden" name="td_deactivate" value="1">
                <input type="submit" class="button" value="Деактивировать" <?php echo $is_active === 'no' ? 'disabled' : ''; ?>>
            </form>
            <p>Текущий статус: <?php echo $is_active === 'yes' ? '<span style="color: green;">Активен</span>' : '<span style="color: red;">Неактивен</span>'; ?></p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('td_save_settings_nonce'); ?>
            <input type="hidden" name="td_save_settings" value="1">

            <div style="display: flex; gap: 20px;">
                <!-- Левый столбец: Диапазоны скидок -->
                <div style="flex: 1; min-width: 0;">
                    <h2>Диапазоны скидок</h2>
                    <p>Укажите минимальные суммы и значения скидок (процент или фиксированная сумма).</p>
                    <table class="wp-list-table widefat" id="td-ranges-table">
                        <thead>
                            <tr>
                                <th>Минимальная сумма</th>
                                <th>Значение скидки</th>
                                <th>Тип скидки</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="td-ranges-body">
                            <?php foreach ($ranges as $index => $range) : ?>
                                <tr>
                                    <td><input type="number" name="td_range_min[]" value="<?php echo esc_attr($range['min']); ?>" step="0.01" min="0" required></td>
                                    <td><input type="number" name="td_range_value[]" value="<?php echo esc_attr($range['value']); ?>" step="0.01" min="0" required></td>
                                    <td>
                                        <select name="td_range_type[]">
                                            <option value="percent" <?php selected($range['type'], 'percent'); ?>>Процент (%)</option>
                                            <option value="fixed" <?php selected($range['type'], 'fixed'); ?>>Фиксированная сумма</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="button td-remove-row">Удалить</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><button type="button" class="button" id="td-add-range">Добавить диапазон</button></p>
                </div>

                <!-- Правый столбец: Купоны и режим -->
                <div style="flex: 1; min-width: 0;">
                    <h2>Настройки купонов</h2>
                    <p>Выберите режим работы с купонами:</p>
                    <select name="td_coupon_mode" style="width: 100%; margin-bottom: 10px;">
                        <option value="exclude" <?php selected($coupon_mode, 'exclude'); ?>>Исключать указанные купоны (скидка не работает с ними)</option>
                        <option value="include" <?php selected($coupon_mode, 'include'); ?>>Применять скидку только с указанными купонами</option>
                        <option value="always_except_others" <?php selected($coupon_mode, 'always_except_others'); ?>>Применять всегда, кроме других купонов</option>
                    </select>

                    <p>Укажите коды купонов:</p>
                    <table class="wp-list-table widefat" id="td-coupons-table">
                        <thead>
                            <tr>
                                <th>Код купона</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="td-coupons-body">
                            <?php foreach ($coupons as $coupon) : ?>
                                <tr>
                                    <td><input type="text" name="td_coupon[]" value="<?php echo esc_attr($coupon); ?>" required></td>
                                    <td><button type="button" class="button td-remove-row">Удалить</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><button type="button" class="button" id="td-add-coupon">Добавить купон</button></p>
                </div>
            </div>

            <?php submit_button('Сохранить настройки'); ?>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#td-add-range').click(function() {
            $('#td-ranges-body').append(
                '<tr>' +
                    '<td><input type="number" name="td_range_min[]" value="0" step="0.01" min="0" required></td>' +
                    '<td><input type="number" name="td_range_value[]" value="0" step="0.01" min="0" required></td>' +
                    '<td><select name="td_range_type[]"><option value="percent">Процент (%)</option><option value="fixed">Фиксированная сумма</option></select></td>' +
                    '<td><button type="button" class="button td-remove-row">Удалить</button></td>' +
                '</tr>'
            );
        });

        $('#td-add-coupon').click(function() {
            $('#td-coupons-body').append(
                '<tr>' +
                    '<td><input type="text" name="td_coupon[]" value="" required></td>' +
                    '<td><button type="button" class="button td-remove-row">Удалить</button></td>' +
                '</tr>'
            );
        });

        $(document).on('click', '.td-remove-row', function() {
            if ($(this).closest('tbody').find('tr').length > 1) {
                $(this).closest('tr').remove();
            }
        });
    });
    </script>
    <?php
}

// Логика расчета скидки с округлением
add_action('woocommerce_cart_calculate_fees', 'td_apply_tiered_discount', 20, 1);
function td_apply_tiered_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $is_active = get_option('td_is_active', 'yes');
    if ($is_active !== 'yes') {
        return;
    }

    $ranges = get_option('td_discount_ranges', array());
    $allowed_coupons = get_option('td_allowed_coupons', array());
    $coupon_mode = get_option('td_coupon_mode', 'exclude');

    $applied_coupons = $cart->get_applied_coupons();
    $has_coupons = !empty($applied_coupons);
    $coupon_match = false;

    if ($has_coupons) {
        foreach ($applied_coupons as $coupon) {
            if (in_array(strtolower($coupon), array_map('strtolower', $allowed_coupons))) {
                $coupon_match = true;
                break;
            }
        }

        if ($coupon_mode === 'exclude' && $coupon_match) {
            return;
        }
        if ($coupon_mode === 'include' && !$coupon_match && $has_coupons) {
            return;
        }
        if ($coupon_mode === 'always_except_others' && !$coupon_match && $has_coupons) {
            return;
        }
    }

    $cart_total = $cart->get_subtotal();

    usort($ranges, function($a, $b) {
        return $a['min'] - $b['min'];
    });

    $discount_amount = 0;
    $discount_type = '';
    $discount_value = 0;
    foreach ($ranges as $range) {
        if ($cart_total >= $range['min']) {
            $discount_value = $range['value'];
            $discount_type = $range['type'];
            if ($discount_type === 'percent') {
                $discount_amount = $cart_total * ($discount_value / 100);
            } else {
                $discount_amount = $discount_value;
            }
        } else {
            break;
        }
    }

    // Округляем сумму скидки до целого числа
    if ($discount_amount > 0) {
        $discount_amount = round($discount_amount); // Округление до целого числа
        $discount_text = $discount_type === 'percent'
            ? sprintf('Скидка %g%% (%s)', $discount_value, wc_price(-$discount_amount))
            : sprintf('Скидка (%s)', wc_price(-$discount_amount));

        $cart->add_fee(
            $discount_text,
            -$discount_amount,
            true
        );
    }
}

// Ссылка на настройки в списке плагинов
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'td_add_settings_link');
function td_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=tiered-discounts') . '">Настройки</a>';
    array_unshift($links, $settings_link);
    return $links;
}