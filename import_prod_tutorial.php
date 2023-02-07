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
function import_products_page_callback()
{
    echo '<div class="wrap">';
    echo '<h1>Import Products</h1>';
    echo '<form action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post" enctype="multipart/form-data">';
    echo '<table>';
    echo '<tr><td>JSON File:</td><td><input type="file" name="import_file" /></td></tr>';
    echo '<tr><td>REST API Link:</td><td><input type="text" name="import_link" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_file" value="Import Products" />';
    echo '<input type="submit" name="submit_link" value="Import REST API" />';
    echo '<input type="submit" name="submit_add_test" value="Import custom" />';
    echo '</form>';
    echo '</div>';

    if (isset($_POST['submit_add_test'])) {
        $table_data = array(
            array(
                'Name' => 'Product 1',
                'SKU' => 'P1-SKU',
                'Stock' => 10,
                'Price' => 15.99,
                'Categories' => 'Category 1, Category 2',
                'Tags' => 'Tag 1, Tag 2',
                'Featured' => true,
                'Date' => '2023-02-06',
                'Custom Field 1' => 'Value 1',
                'Custom Field 2' => 'Value 2',
                'Custom Field 3' => 'Value 3',
                'Custom Field 4' => 'Value 4'
            ),
            array(
                'Name' => 'Product 2',
                'SKU' => 'P2-SKU',
                'Stock' => 5,
                'Price' => 20.99,
                'Categories' => 'Category 2, Category 3',
                'Tags' => 'Tag 2, Tag 3',
                'Featured' => false,
                'Date' => '2023-02-07',
                'Custom Field 1' => 'Value 5',
                'Custom Field 2' => 'Value 6',
                'Custom Field 3' => 'Value 7',
                'Custom Field 4' => 'Value 8'
            )
        );

        foreach ($table_data as $product) {
            $post_id = wp_insert_post(array(
                'post_title' => $product['Name'],
                'post_status' => 'publish',
                'post_type' => 'product',
                'post_date' => $product['Date']
            ));

            wp_set_object_terms($post_id, explode(',', $product['Categories']), 'product_cat');
            wp_set_object_terms($post_id, explode(',', $product['Tags']), 'product_tag');
            update_post_meta($post_id, '_sku', $product['SKU']);
            update_post_meta($post_id, '_stock', $product['Stock']);
            update_post_meta($post_id, '_regular_price', $product['Price']);
            update_post_meta($post_id, '_featured', $product['Featured']);
            update_post_meta($post_id, 'Custom Field 1', $product['Custom Field 1']);
            update_post_meta($post_id, 'Custom Field 2', $product['Custom Field 2']);
            update_post_meta($post_id, 'Custom Field 3', $product['Custom Field 3']);
            update_post_meta($post_id, 'Custom Field 4', $product['Custom Field 4']);
        }
    }
    if (isset($_POST['submit_file'])) {
        $file_path = $_FILES['import_file']['tmp_name'];
        $file_data = file_get_contents($file_path);
        $products = json_decode($file_data, true);

        foreach ($products as $product) {
            $new_product = array(
                'post_title'    => $product['title'],
                'post_status'   => 'publish',
                'post_type'     => 'product',
                'sale_price'    => $product['priceWS'],
            );
            $product_id = wp_insert_post($new_product);

            wp_set_object_terms($product_id, explode(',', $product['rangeOfGoods']), 'product_cat');
            update_post_meta($product_id, '_sku', $product['regNum']);
            update_post_meta($product_id, 'Kategorie', $product['rangeOfGoods']);
            update_post_meta($product_id, 'Země', $product['country']);
            update_post_meta($product_id, 'Vinařství', $product['winery']);
            update_post_meta($product_id, 'Oblast', $product['area']);
            update_post_meta($product_id, 'Objem', $product['volume']);
            update_post_meta($product_id, 'Ročník', $product['vintage']);
            update_post_meta($product_id, 'Apelace', $product['appeals']);
            update_post_meta($product_id, 'Dekantace', $product['decantation']);
//            update_post_meta($product_id, 'Odrůda', $product['variety']);
//            update_post_meta($product_id, 'Styl', $product['style']);
            update_post_meta($product_id, 'Sklenička', $product['glass']);
            update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
            update_post_meta($product_id, 'Základní cena bez DPH', $product['retailPriceExclVAT']);
            update_post_meta($product_id, 'Akční cena včetně DPH', $product['eshopPriceVAT']);
        }

        echo '<div>Products imported successfully!</div>';
    }

    if (isset($_POST['submit_link'])) {
        $import_link = $_POST['import_link'];
        $response = wp_remote_get($import_link);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $products = json_decode($body, true);
            foreach ($products as $product) {
                $new_product = array(
                    'post_title'    => $product['title'],
                    'post_status'   => 'publish',
                    'post_type'     => 'product',
                    'sale_price'    => $product['priceWS'],
                );
                $product_id = wp_insert_post($new_product);

                wp_set_object_terms($product_id, explode(',', $product['rangeOfGoods']), 'product_cat');
                update_post_meta($product_id, '_sku', $product['regNum']);
                update_post_meta($product_id, 'Kategorie', $product['rangeOfGoods']);
                update_post_meta($product_id, 'Země', $product['country']);
                update_post_meta($product_id, 'Vinařství', $product['winery']);
                update_post_meta($product_id, 'Oblast', $product['area']);
                update_post_meta($product_id, 'Objem', $product['volume']);
                update_post_meta($product_id, 'Ročník', $product['vintage']);
                update_post_meta($product_id, 'Apelace', $product['appeals']);
                update_post_meta($product_id, 'Dekantace', $product['decantation']);
//            update_post_meta($product_id, 'Odrůda', $product['variety']);
//            update_post_meta($product_id, 'Styl', $product['style']);
                update_post_meta($product_id, 'Sklenička', $product['glass']);
                update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
                update_post_meta($product_id, 'Základní cena bez DPH', $product['retailPriceExclVAT']);
                update_post_meta($product_id, 'Akční cena včetně DPH', $product['eshopPriceVAT']);
            }

            echo '<div>Products imported successfully!</div>';
        }
    }
}