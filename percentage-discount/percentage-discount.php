add_action( 'woocommerce_cart_calculate_fees', 'apply_discount_for_pickup_method', 10, 1 );

function apply_discount_for_pickup_method( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    // Проверяем, выбрал ли пользователь вариант доставки
    $eligible_shipping_methods = array('local_pickup:1');
    $selected_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

    if ( count( array_intersect( $eligible_shipping_methods, $selected_shipping_methods ) ) > 0 ) {
        $excluded_products = array(); // ID товаров и их вариаций, для которых скидка не применяется
        $discount_percentage = 10; // Процент скидки
        $total_discount = 0;

        // Рассчитываем скидку, исключая определенные товары и их вариации
        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            $parent_product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

            if ( ! in_array( $parent_product_id, $excluded_products ) ) {
                $total_discount += ( $cart_item['line_total'] * $discount_percentage / 100 );
            }
        }

        // Добавляем скидку в корзину, если она рассчитана
        if ( $total_discount > 0 ) {
            $cart->add_fee( sprintf( __( '%d%% discount for pickup shipping method', 'woocommerce' ), $discount_percentage ), -$total_discount );
        }
    }
}
