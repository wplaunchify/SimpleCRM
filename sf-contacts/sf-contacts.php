<?php
/**
 * Plugin Name: SF Front End CRM
 * Version: 1.0
 * Author: Spencer Forman (help@wplaunchify.com)
 * Author URI: https://wplaunchify.com
 * Description: A simple CRM system for WordPress
 * License: GPL2
 */

class SfFrontEndCRM {
     
/**
* Constructor. Called when plugin is initialised
*/

function __construct() {

    add_action( 'init', array( $this, 'register_custom_post_type' ) );
    add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) ); 
    add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
    add_filter( 'manage_edit-customer_columns', array( $this, 'add_table_columns' ) );
    add_action( 'manage_customer_posts_custom_column', array( $this, 'output_table_columns_data'), 10, 2 );
    add_filter( 'manage_edit-customer_sortable_columns', array( $this, 'define_sortable_table_columns') );
    add_action( 'admin_init', array( $this, 'add_custom_caps'), 999 );  
    add_shortcode( 'post_form', array( $this, 'sf_create_customer_form') );
    add_action( 'wp_enqueue_scripts', array( $this, 'sf_register_plugin_styles' ) );
    add_action( 'wp_ajax_sf_process_form', array( $this, 'sf_process_form') );
    add_action( 'wp_ajax_nopriv_sf_process_form', array( $this, 'sf_process_form') );

    if ( is_admin() ) {
        add_filter( 'posts_join', array ($this, 'search_meta_data_join' ) );
        add_filter( 'posts_where', array( $this, 'search_meta_data_where' ) );
        add_filter( 'posts_groupby', array( $this, 'search_meta_data_groupby' ) );
    }  
}


/**
 * Register and enqueue style sheet.
 */

function sf_register_plugin_styles() {
    wp_register_style( 'sf-main-style', plugins_url( 'sf-contacts/css/sf-contacts.css' ) );
    wp_enqueue_style( 'sf-main-style' );
    wp_enqueue_script( 'sf-main-js',  plugin_dir_url( __FILE__ ) . 'js/options.js', array('jquery') );
    wp_localize_script( 'jquery', 'ajax_url', array( 'url' => admin_url( 'admin-ajax.php' ) ) );

}


/**
 * Registers a Custom Post Type called customer
 */

function register_custom_post_type() {
    register_post_type( 'customer', array(
        'labels' => array(
            'name'               => _x( 'Customers', 'post type general name', 'sf' ),
            'singular_name'      => _x( 'Customer', 'post type singular name', 'sf' ),
            'menu_name'          => _x( 'Customers', 'admin menu', 'sf' ),
            'name_admin_bar'     => _x( 'Customer', 'add new on admin bar', 'sf' ),
            'add_new'            => _x( 'Add New', 'customer', 'sf' ),
            'add_new_item'       => __( 'Add New Customer', 'sf' ),
            'new_item'           => __( 'New Customer', 'sf' ),
            'edit_item'          => __( 'Edit Customer', 'sf' ),
            'view_item'          => __( 'View Customer', 'sf' ),
            'all_items'          => __( 'All Customers', 'sf' ),
            'search_items'       => __( 'Search Customers', 'sf' ),
            'parent_item_colon'  => __( 'Parent Customers:', 'sf' ),
            'not_found'          => __( 'No customers found.', 'sf' ),
            'not_found_in_trash' => __( 'No customers found in Trash.', 'sf' ),
        ),
         
        // Frontend
        'has_archive' => false,
        'public' => false,
        'publicly_queryable' => false,
         
        // Admin
        'capabilities' => array(
            'edit_others_posts'     => 'edit_others_customers',
            'delete_others_posts'   => 'delete_others_customers',
            'delete_private_posts'  => 'delete_private_customers',
            'edit_private_posts'    => 'edit_private_customers',
            'read_private_posts'    => 'read_private_customers',
            'edit_published_posts'  => 'edit_published_customers',
            'publish_posts'         => 'publish_customers',
            'delete_published_posts'=> 'delete_published_customers',
            'edit_posts'            => 'edit_customers'   ,
            'delete_posts'          => 'delete_customers',
            'edit_post'             => 'edit_customer',
            'read_post'             => 'read_customer',
            'delete_post'           => 'delete_customer',
        ),
        'map_meta_cap' => true,
        'menu_icon' => 'dashicons-businessman',
        'menu_position' => 10,
        'query_var' => true,
        'show_in_menu' => true,
        'show_ui' => true,
        'supports' => array(
            'title',
            'author',
            'comments',
        ),
    ) );    
}

/**
* Registers a Meta Box on our Customer Custom Post Type, called 'Customer Details'
*/

function register_meta_boxes() {
    add_meta_box( 'customer-details', 'Customer Details', array( $this, 'output_meta_box' ), 'customer', 'normal', 'high' );   
}

/**
* Outputs a Customer Details meta box
*/

function output_meta_box($post) {

    $t = get_post_meta( $post->ID, '_timestamp', true );
    $name = get_post_meta( $post->ID, '_customer_name', true );
    $email = get_post_meta( $post->ID, '_customer_email', true );
    $phone = get_post_meta( $post->ID, '_customer_phone', true );
    $budget = get_post_meta( $post->ID, '_customer_budget', true );
    $message = get_post_meta( $post->ID, '_customer_message', true );
  


    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'save_customer', 'customers_nonce' );


    // Timestamp (for Chicago) from 3rd party API (https://timezonedb.com/references/get-time-zone)
    $request = wp_remote_get( 'http://api.timezonedb.com/v2/get-time-zone?key=QJNKVTGSN1E5&format=json&fields=timestamp&by=zone&zone=America/Chicago' );
    if( is_wp_error( $request ) ) {
        return false; // Bail early
    }
    $body = wp_remote_retrieve_body( $request );
    $timestamp = json_decode( $body );
    $t=$timestamp->timestamp;


    // Output labels and fields
    echo '<table class="form-table">';
    echo '<tbody>';
    echo ( '<input type="hidden" name="timestamp" id="timestamp" value="' . $t . '" />' );

    echo '<tr class="customer-datetime">';
    echo('<th>Current Date/Time:</th>'); 
    echo('<td>' . date("l F jS, Y \@ h:i:s A",$t) . '</td>');
    echo '</tr>';

    echo '<tr class="customer-name">';
    echo ( '<th><label for="customer_name">' . __( 'Name', 'sf' ) . '</label></th>' );
    echo ( '<td><input type="text" maxlength="45" size="50" name="customer_name" id="customer_name" value="' . esc_attr( $name ) . '" /></td>' );
    echo '</tr>';


    echo '<tr class="customer-email">';
    echo ( '<th><label for="customer_email">' . __( 'Email Address', 'sf' ) . '</label></th>' );
    echo ( '<td><input type="email" maxlength="45" size="50" name="customer_email" id="customer_email" value="' . esc_attr( $email ) . '" /></td>' );
    echo '</tr>';

    echo '<tr class="customer-phone">';
    echo ( '<th><label for="customer_phone">' . __( 'Phone Number', 'sf' ) . '</label></th>' );
    echo ( '<td><input type="tel" maxlength="45" size="50" name="customer_phone" id="customer_phone" value="' . esc_attr( $phone ) . '" /></td>' );
    echo '</tr>';

    echo '<tr class="customer-budget">';
    echo ( '<th><label for="customer_budget">' . __( 'Desired Budget', 'sf' ) . '</label></th>' );
    echo ( '<td><input type="number" maxlength="45" size="50" name="customer_budget" id="customer_budget" value="' . esc_attr( $budget ) . '" /></td>' );
    echo '</tr>';

    echo '<tr class="customer-message">';
    echo ( '<th><label for="customer_message">' . __( 'Message', 'sf' ) . '</label></th>' );
    echo ( '<td><textarea rows="5" cols="49" name="customer_message" id="customer_message" />' . esc_attr( $message ) . '</textarea></td>' );
    echo '</tr>';

    echo '</tbody>';
    echo '</table>';

}

/**
* Saves the meta box field data
*/

function save_meta_boxes( $post_id ) {
 
    // Check if our nonce is set.
    if ( ! isset( $_POST['customers_nonce'] ) ) {
        return $post_id;    
    }
 
    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['customers_nonce'], 'save_customer' ) ) {
        return $post_id;
    }
 
    // Check this is the Customer Custom Post Type
    if ( 'customer' != $_POST['post_type'] ) {
        return $post_id;
    }
 
    // Check the logged in user has permission to edit this post
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return $post_id;
    }
 
    // OK to save meta data
    $t = sanitize_text_field( $_POST['timestamp'] );
    update_post_meta( $post_id, '_timestamp', $t );

    $name = sanitize_text_field( $_POST['customer_name'] );
    update_post_meta( $post_id, '_customer_name', $name );

    $email = sanitize_text_field( $_POST['customer_email'] );
    update_post_meta( $post_id, '_customer_email', $email );
     
    $phone = sanitize_text_field( $_POST['customer_phone'] );
    update_post_meta( $post_id, '_customer_phone', $phone );

    $budget = sanitize_text_field( $_POST['customer_budget'] );
    update_post_meta( $post_id, '_customer_budget', $budget );

    $message = sanitize_text_field( $_POST['customer_message'] );
    update_post_meta( $post_id, '_customer_message', $message );

}


/**
* Adds table columns to the Customers WP_List_Table
*/

function add_table_columns( $columns ) {

    $columns['timestamp'] = __( 'Timestamp', 'sf' );
    $columns['customer_name'] = __( 'Name', 'sf' );
    $columns['customer_email'] = __( 'Email', 'sf' );
    $columns['customer_phone'] = __( 'Phone', 'sf' );
    $columns['customer_budget'] = __( 'Budget', 'sf' );
    $columns['customer_message'] = __( 'Message', 'sf' );
     
    return $columns;
     
}


/**
* Outputs our Customer field data, based on the column requested
*/

function output_table_columns_data( $columnName, $post_id ) {
    switch ( $columnName ) {

        case 'timestamp';
        $t = get_post_meta( $post_id, '_timestamp', true );
        if (!empty ($t)){
        echo date("l F jS, Y \@ h:i:s A",$t);
        }
        break;

        case 'customer_name';
        $name = get_post_meta( $post_id, '_customer_name', true );
        echo $name;
        break;

        case 'customer_email';
        $email = get_post_meta( $post_id, '_customer_email', true );
        echo $email;
        break;

        case 'customer_phone';
        $phone = get_post_meta( $post_id, '_customer_phone', true );
        echo $phone;
        break;

        case 'customer_budget';
        $budget = get_post_meta( $post_id, '_customer_budget', true );
        if (!empty($budget)){
            echo '$'. $budget;
        }
        break;

        case 'customer_message';
        $message = get_post_meta( $post_id, '_customer_message', true );
        //echo $message;
        echo mb_strimwidth( $message, 0, 50, "...");
        break;
    }
}


/**
* Defines which Customer columns are sortable
*/

function define_sortable_table_columns( $columns ) {
 
    $columns['timestamp'] = __( 'Timestamp', 'sf' );
    $columns['customer_name'] = __( 'Name', 'sf' );
    $columns['customer_email'] = __( 'Email', 'sf' );
    $columns['customer_phone'] = __( 'Phone', 'sf' );
    $columns['customer_budget'] = __( 'Budget', 'sf' );
    $columns['customer_message'] = __( 'Message', 'sf' );
     
    return $columns;
     
}


/**
* Adds a join to the WordPress meta table
*/

function search_meta_data_join($join) {
    global $wpdb;
         
    // Only join the post meta table if we are performing a search
    if ( empty ( get_query_var( 's' ) ) ) {
        return $join;
    }
         
    // Only join the post meta table if we are on the Customers Custom Post Type
    if ( 'customer' != get_query_var( 'post_type' ) ) {
        return $join;
    }
         
    // Join the post meta table
    $join .= 'LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';

    return $join;
}


/**
* Adds a where clause to the WordPress meta table for searches in the WordPress Administration
*/

function search_meta_data_where($where) {
    global $wpdb;
 
    // Only join the post meta table if we are performing a search
    if ( empty ( get_query_var( 's' ) ) ) {
            return $where;
        }
     
    // Only join the post meta table if we are on the Customer Custom Post Type
    if ( 'customer' != get_query_var( 'post_type' ) ) {
        return $where;
    }
     
     
    // Inject our WHERE clause in between the start of the query and the rest of the query

   $where = preg_replace(
            "/\(\s*" . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(" . $wpdb->posts . ".post_title LIKE $1) OR (" . $wpdb->postmeta . ".meta_value LIKE $1)", $where );


    // Return revised WHERE clause
    return $where;
}

function search_meta_data_groupby($groupby) {
    global $wpdb;

        // Only join the post meta table if we are performing a search
    if ( empty ( get_query_var( 's' ) ) ) {
            return $groupby;
        }
     
    // Only join the post meta table if we are on the Customer Custom Post Type
    if ( 'customer' != get_query_var( 'post_type' ) ) {
        return $groupby;
    }

    $groupby = "$wpdb->posts.ID";
    return $groupby;
}


/**
* Assign capabilities only to admin
*/
 
function add_custom_caps() {
 
 // Add any roles you'd like to administer the custom post types
 $roles = array('administrator');
 
 // Loop through each role and assign capabilities
 foreach($roles as $the_role) { 
 
      $role = get_role($the_role);
 
              $role->add_cap( 'edit_others_customers' );
              $role->add_cap( 'delete_others_customers');
              $role->add_cap( 'delete_private_customers' );
              $role->add_cap( 'edit_private_customers' );
              $role->add_cap( 'read_private_customers' );
              $role->add_cap( 'edit_published_customers' );
              $role->add_cap( 'publish_customers' );
              $role->add_cap( 'delete_published_customers' );
              $role->add_cap( 'edit_customers' );
              $role->add_cap( 'delete_customers' );
              $role->add_cap( 'edit_customer' );
              $role->add_cap( 'read_customer' );
              $role->add_cap( 'delete_customer' );
              $role->add_cap( 'read' ); 
 }
}

/**
* Front End Form and shortcode atts
*/

function sf_create_customer_form( $atts, $content = null ) { // puts form on page via shortcode

    ob_start(); 

    $atts = shortcode_atts( array( // allow admin to change labels and layout of fields
        'name'    => 'Name',
        'phone'   => 'Phone',
        'email'   => 'Email',
        'budget'  => 'Budget',
        'message' => 'Message',
        'name_max'      => '',
        'phone_max'     => '',
        'email_max'     => '',
        'budget_max'    => '',
        'message_max'   => '',
        'message_rows'  => '3',
        'message_cols'  => '',
    ), $atts, 'sf_create_customer' );

    // Timestamp (for Chicago) from 3rd party API (https://timezonedb.com/references/get-time-zone)
    $request = wp_remote_get( 'http://api.timezonedb.com/v2/get-time-zone?key=QJNKVTGSN1E5&format=json&fields=timestamp&by=zone&zone=America/Chicago' );
    if( is_wp_error( $request ) ) {
        return false; // Bail early
    }
    $body = wp_remote_retrieve_body( $request );
    $timestamp = json_decode( $body );
    $t=$timestamp->timestamp;

    ?>

    <div class="flex-body">
        <div class="container">
            <?php if( current_user_can('administrator') ) { ?> 
                <form id="sf_create_post" action="" method="POST">
                    <p class="message"></p>
                    <ul id="post_content" class="flex-outer">
                      <li>
                        <input type="hidden" class="required" id="timestamp" name="timestamp" value="<?php echo esc_attr( $t ); ?>">
                      </li>

                      <li>
                        <label for="customer_name"><?php echo esc_attr( $atts['name'] ); ?></label>
                        <input type="text" class="required" id="customer_name" name="customer_name" maxlength="<?php echo esc_attr( $atts['name_max'] ); ?>" placeholder="Enter Customer name here">
                      </li>
                      <li>
                        <label for="customer_email"><?php echo esc_attr( $atts['email'] ); ?></label>
                        <input type="email" class="required" id="customer_email" name="customer_email" maxlength="<?php echo esc_attr( $atts['email_max'] ); ?>" placeholder="Enter Customer email here">
                      </li>
                      <li>
                        <label for="customer_phone"><?php echo esc_attr( $atts['phone'] ); ?></label>
                        <input type="tel" class="required" id="customer_phone" name="customer_phone" maxlength="<?php echo esc_attr( $atts['phone_max'] ); ?>" placeholder="Enter Customer phone here">
                      </li>
                      <li>
                        <label for="customer_budget"><?php echo esc_attr( $atts['budget'] ); ?></label>
                        <input type="number" id="customer_budget" name="customer_budget" maxlength="<?php echo esc_attr( $atts['budget_max'] ); ?>" placeholder="Enter Customer budget here (number only)">
                      </li>

                      <li>
                        <label for="customer_message"><?php echo esc_attr( $atts['message'] ); ?></label>
                        <textarea class="required" data-gramm_editor="false" rows="<?php echo esc_attr( $atts['message_rows'] ); ?>" cols="<?php echo esc_attr( $atts['message_cols'] ); ?>" id="customer_message" name="customer_message" maxlength="<?php echo esc_attr( $atts['message_max'] ); ?>" placeholder="Enter Customer message here"></textarea>
                      </li>
                      <li>
                        <label for="submit"></label>
                        <?php wp_nonce_field('customer_nonce', 'customer_nonce_field'); ?>
                        <input class="submit" type="submit" name="customer_submit" value="<?php _e('Submit Customer Info', 'sf'); ?>"/>
                       </li>
                    </ul>
                </form>
            <?php } else { ?>
                <p class="message"><?php _e('This form is for admins only', 'sf'); ?></p>
            <?php } ?>
        </div>
    </div>

    <?php 
    return ob_get_clean();
}

/**
* Front End Form and Processing
*/

function sf_process_form() {
    $params = array();
    $response = array();
    parse_str($_POST['form'], $params);

    if(isset($params['customer_nonce_field']) && wp_verify_nonce($params['customer_nonce_field'], 'customer_nonce')) {   
        $customer_info = array(
            'post_title' => esc_attr(strip_tags($params['customer_name'])),
            'post_type' => 'customer',
            'post_status' => 'publish',
        );

        $post_id = wp_insert_post($customer_info);

        if($post_id) {
            update_post_meta($post_id, '_timestamp', esc_attr(strip_tags($params['timestamp'])));
            update_post_meta($post_id, '_customer_name', esc_attr(strip_tags($params['customer_email'])));
            update_post_meta($post_id, '_customer_email', esc_attr(strip_tags($params['customer_email'])));
            update_post_meta($post_id, '_customer_phone', esc_attr(strip_tags($params['customer_phone'])));
            update_post_meta($post_id, '_customer_budget', esc_attr(strip_tags($params['customer_budget'])));
            update_post_meta($post_id, '_customer_message', esc_attr(strip_tags($params['customer_message'])));

            $response['done'] = true;
            $response['message'] = __('Thank you! A new customer has been created.', 'sf');
        } else {
            $response['done'] = false;
            $response['message'] = __('Something went wrong.', 'sf');
        }
    } else {
        $response['done'] = false;
        $response['message'] = __('Something went wrong.', 'sf');
    }

    die(json_encode($response));
}

// end constructor
} 
$SfFrontEndCRM = new SfFrontEndCRM;
?>