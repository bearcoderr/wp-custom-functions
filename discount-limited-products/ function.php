
// Добавляем новую страницу "Настройки скидки" в админку
add_action('admin_menu', 'register_discount_settings_page');

function register_discount_settings_page() {
    add_menu_page(
        'Настройки скидки',      // Название страницы
        'Скидка 50%',            // Название в меню
        'manage_options',        // Права доступа
        'discount_settings',     // Слаг страницы
        'discount_settings_page',// Функция вывода
        'dashicons-tag',         // Иконка
        25                       // Позиция в меню
    );
}

// Функция рендера страницы настроек
function discount_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки скидки 50%</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('discount_settings_group');
            do_settings_sections('discount_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
// Регистрируем настройки
add_action('admin_init', 'register_discount_settings');

function register_discount_settings() {
    register_setting('discount_settings_group', 'discount_min_qty');
    register_setting('discount_settings_group', 'discount_excluded_categories');

    add_settings_section(
        'discount_main_section',
        'Основные настройки',
        null,
        'discount_settings'
    );

    add_settings_field(
        'discount_min_qty',
        'Минимальное количество товаров',
        'discount_min_qty_callback',
        'discount_settings',
        'discount_main_section'
    );

    add_settings_field(
        'discount_excluded_categories',
        'Исключаемые категории',
        'discount_excluded_categories_callback',
        'discount_settings',
        'discount_main_section'
    );
}

// Поле для минимального количества товаров
function discount_min_qty_callback() {
    $value = get_option('discount_min_qty', 2);
    echo '<input type="number" name="discount_min_qty" value="' . esc_attr($value) . '" min="1">';
}

// Поле для исключенных категорий
function discount_excluded_categories_callback() {
    $value = get_option('discount_excluded_categories', '');
    echo '<input type="text" name="discount_excluded_categories" value="' . esc_attr($value) . '" placeholder="Введите ID через запятую">';
}
add_action('woocommerce_before_calculate_totals', 'apply_cart_discount', 10, 1);

function apply_cart_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    // Загружаем настройки из админки
    $min_qty = (int) get_option('discount_min_qty', 2); // Количество товаров, на которые действует скидка
    $excluded_categories = get_option('discount_excluded_categories', '');
    $excluded_categories = array_map('intval', explode(',', $excluded_categories));

    $eligible_items = []; // Сюда складываем товары, на которые можно дать скидку

    // Проверяем товары в корзине
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $is_excluded = false;

        foreach ($categories as $category) {
            if (in_array($category->term_id, $excluded_categories)) {
                $is_excluded = true;
                break;
            }
        }

        if (!$is_excluded) {
            for ($i = 0; $i < $cart_item['quantity']; $i++) {
                $eligible_items[] = [
                    'key'   => $cart_item_key,
                    'price' => $cart_item['line_total'] / $cart_item['quantity'], // Цена за 1 шт.
                ];
            }
        }
    }

    // Если товаров для скидки недостаточно – не применяем скидку
    if (count($eligible_items) < $min_qty) {
        return;
    }

    // Сортируем товары по цене (от дешёвых к дорогим)
    usort($eligible_items, function ($a, $b) {
        return $a['price'] <=> $b['price'];
    });

    // Берём только первые N товаров (самые дешёвые)
    $discountable_items = array_slice($eligible_items, 0, $min_qty);

    // Вычисляем сумму скидки (50% только для нужного количества товаров)
    $discount = array_sum(array_column($discountable_items, 'price')) * 0.5;

    // Добавляем скидку в корзину с уточнением количества товаров
    $cart->add_fee("Акция: 50% скидка (на {$min_qty} товара)", -$discount);
}

