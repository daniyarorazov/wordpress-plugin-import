<?php

/*
Plugin Name: Import Products Tutorial
Description: A plugin for importing products into WooCommerce
Version: 1.0
Author: Daniyar
Text Domain: import-products-tutor
*/


// Load Composer autoloader.
// @link https://github.com/brightnucleus/jasper-client/blob/master/tests/bootstrap.php#L55-L59
$autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
    require_once $autoloader;
}

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

$woocommerce = new Client(
    'https://wordpress',
    'ck_a651750ebea3f32a4a141f4f361825ca67367578',
    'cs_4cccaa22fd82f680aa67ec9d4d5e2cab7b491486',
    [
        'wp_api'  => true,
        'version' => 'wc/v2',
    ]
);


add_action( 'admin_menu', 'add_import_products_page' );

function add_import_products_page() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Import Products',
        'Import Products',
        'manage_options',
        'import-products',
        'import_products_page_callback'
    );
}

function import_products_page_callback() {
    echo '<div class="wrap">';
    echo '<h1>Import Products</h1>';
    echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post" enctype="multipart/form-data">';
    echo '<table>';
    echo '<tr><td>JSON File:</td><td><input type="file" name="import_file" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit" value="Import Products" />';
    echo '</form>';
    echo '</div>';

    if ( isset( $_POST['submit'] ) ) {
        $file_path = $_FILES['import_file']['tmp_name'];
        $file_data = file_get_contents( $file_path );
        $products = json_decode( $file_data, true );



        foreach ( $products as $product ) {
            echo var_dump($product['name']);
            $new_product = array(
                'post_title'    => $product['name'],
                'post_content'  => $product['description'],
                'post_status'   => 'publish',
                'post_type'     => 'product',
            );
            $product_id = wp_insert_post( $new_product );

            update_post_meta( $product_id, '_regular_price', $product['regular_price'] );
            update_post_meta( $product_id, '_manage_stock', $product['manage_stock'] );
            update_post_meta( $product_id, '_stock', $product['stock'] );
            update_post_meta( $product_id, '_weight', $product['weight'] );
            update_post_meta( $product_id, '_product_type', $product['type'] );
            update_post_meta( $product_id, '_has_variations', $product['has_variations'] );
        }

        echo '<div>Products imported successfully!</div>';
    }
}