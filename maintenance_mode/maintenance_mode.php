// Activate WordPress Maintenance Mode

function wp_maintenance_mode(){

    if(!current_user_can('edit_themes') || !is_user_logged_in()){

        wp_die('<h1 style="color:red">Сайт находится на техническом обслуживании.</h1><br />В настоящее время проводятся плановые технические работы. Вскоре мы возобновим работу!');

    }

}

add_action('get_header', 'wp_maintenance_mode');