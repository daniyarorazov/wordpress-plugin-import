<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
    <style>
        .table-form {
            border-collapse:separate;
            border-spacing: 0 1em;
        }

        .table-form tr td{
            background: #dfdfdf;
            padding: 20px 10px 20px 10px;
        }
    </style>
</head>
</html>

<?php

// Options for naming plugin
/*
Plugin Name: Import Products (Wines)
Description: A plugin for importing products into WooCommerce
Version: 1.2
Author: Daniyar
Text Domain: import-products-wines
*/


// Load Composer autoloader.
$autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
    require_once $autoloader;
}

use Automattic\WooCommerce\Client;

// Connect to REST API WOOCOMMERCE (Change this fields depends yours project)
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

// Create import products page inside Woocommerce products
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


// Getting SKU of products from products list
function get_product_by_sku( $sku ) {
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
    if ($product_id) {
        $product = wc_get_product($product_id);

        return $product;
    }
    return null;
}


// Get elems with inside options in obj and create from obj to string like ("elem1, elem2, elem3")
// In our situation, we are using for property style and variety in the data.json model
function getElemsTrueOption($product_option_obj) {
    $list = [];
    $array = (array) $product_option_obj;
    foreach ($array as $key => $value) {
        if ($value === true) {
            array_push($list, $key);
        }
    }
    return $list;
}




// Plugin page
function import_products_page_callback()
{
    echo '<div class="wrap">';
    echo '<h1>Import Products (Wines)</h1>';
    echo '<form action="' . esc_url($_SERVER['REQUEST_URI']) . '" method="post" enctype="multipart/form-data">';
    echo '<table class="table-form">';
    echo '<tr><td><b>JSON File:</b></td><td><input  type="file" name="import_file" /></td><td><input class="btn btn-primary" type="submit" name="submit_file" value="Import Products with JSON" /></td></tr>';
    echo '<tr><td><b>REST API Link:</b></td><td><input type="text" name="import_link" /></td><td><input class="btn btn-primary" type="submit" name="submit_link" value="Import REST API" /></td></tr>';
    echo '<tr><td><b>Or empty custom product:</b></td><td></td><td><input class="btn btn-primary" type="submit" name="submit_empty_product" value="Create empty product with custom fields of Wine" /></td></tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';

    // If we want to put our json data for importing products
    if (isset($_POST['submit_file'])) {
        $file_path = $_FILES['import_file']['tmp_name'];
        $file_data = file_get_contents($file_path);
        $products = json_decode($file_data, true);

        foreach ($products as $product) {

            $product_sku = $product['regNum'];

            $existing_product_id = get_product_by_sku($product_sku);

            if ( $existing_product_id ) {
                $product_id = $existing_product_id->id;
                wp_update_post(array(
                    'ID' => $product_id,
                    'post_title' => $product['title'],
                    'post_status' => 'publish',
                    'post_type' => 'product',
                    'sale_price' => $product['priceWS'],
                ));


                // Images properties
                $regnum = intval($product['regNum']);
                $url = "https://portal.facecounter.eu/pwc_api/api/v1/Stocks/Imports/Image/$regnum";
                $headers = array(
                    'APIKey: 1B63ED8F-A419-441D-B468-01112A917CD3'
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $response = curl_exec($ch);
                curl_close($ch);

                // Save the image data to a file
                $filename = 'image_'.$product['regNum'].'.jpg';
                $file_path = WP_CONTENT_DIR . '/uploads/' . $filename; // Replace with the desired file path
                $file = fopen($file_path, 'w');
                fwrite($file, $response);
                fclose($file);
                if (!get_post_thumbnail_id($product_id)) {
                    // Attach the image to the product
                    $attachment_id = wp_insert_attachment(array(
                        'post_title' => $filename,
                        'post_content' => '',
                        'post_status' => 'inherit',
                        'post_mime_type' => 'image/jpeg' // Replace with the actual MIME type of the image
                    ), $file_path);

                    if (!is_wp_error($attachment_id)) {
                        set_post_thumbnail($existing_product_id->id, $attachment_id);
                    }
                }

                // All properties of Product
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

                $elems = getElemsTrueOption($product['variety']);
                foreach ($elems as $key => $value){
                    $meta_key = 'Odrůda_' . $key;
                    update_post_meta($product_id, $meta_key, $value);
                }

                $elems = getElemsTrueOption($product['style']);
                foreach ($elems as $key => $value){
                    $meta_key = 'Styl_' . $key;
                    update_post_meta($product_id, $meta_key, $value);
                }


                update_post_meta($product_id, 'Sklenička', $product['glass']);
                update_post_meta($product_id, '_price', $product['priceWS']);
                update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
                if ($product['retailPriceExclVAT'] and $product['eshopPriceVAT'] < doubleval($product['retailPriceExclVAT']) * 1.21) {
                    update_post_meta($product_id, '_regular_price', doubleval($product['retailPriceExclVAT']) * 1.21);
                    update_post_meta($product_id, '_sale_price', $product['eshopPriceVAT']);
                } else {
                    update_post_meta($product_id, '_regular_price', doubleval($product['eshopPriceVAT']));
                }


                echo '<div class="alert alert-success" role="alert">Updated info product: '.$product['title'].'</div>';
            } else {
                // Create a new product
                $new_product = array(
                    'post_title'    => $product['title'],
                    'post_status'   => 'publish',
                    'post_type'     => 'product',
                    'sale_price'    => $product['priceWS'],
                );

                $product_id = wp_insert_post($new_product);

                // Images properties
                $regnum = intval($product['regNum']);
                $url = "https://portal.facecounter.eu/pwc_api/api/v1/Stocks/Imports/Image/$regnum";
                $headers = array(
                    'APIKey: YOUR_API_KEY'
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $response = curl_exec($ch);
                curl_close($ch);

                // Save the image data to a file
                $filename = 'image_'.$product['regNum'].'.jpg';
                $file_path = WP_CONTENT_DIR . '/uploads/' . $filename; // Replace with the desired file path
                $file = fopen($file_path, 'w');
                fwrite($file, $response);
                fclose($file);
                if (!get_post_thumbnail_id($product_id)) {
                    // Attach the image to the product
                    $attachment_id = wp_insert_attachment(array(
                        'post_title' => $filename,
                        'post_content' => '',
                        'post_status' => 'inherit',
                        'post_mime_type' => 'image/jpeg' // Replace with the actual MIME type of the image
                    ), $file_path);

                    if (!is_wp_error($attachment_id)) {
                        set_post_thumbnail($product_id, $attachment_id);
                    }
                }

                // All properties of Product
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
                $elems = getElemsTrueOption($product['variety']);
                foreach ($elems as $key => $value){
                    $meta_key = 'Odrůda_' . $key;
                    update_post_meta($product_id, $meta_key, $value);
                }

                $elems = getElemsTrueOption($product['style']);
                foreach ($elems as $key => $value){
                    $meta_key = 'Styl_' . $key;
                    update_post_meta($product_id, $meta_key, $value);
                }
                update_post_meta($product_id, 'Sklenička', $product['glass']);
                update_post_meta($product_id, '_price', $product['priceWS']);
                update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
                update_post_meta($product_id, '_regular_price', doubleval($product['retailPriceExclVAT']) * 1.21);
                update_post_meta($product_id, '_sale_price', $product['eshopPriceVAT']);
                echo '<div class="alert alert-success" role="alert">Product imported successfully: '. $product['title'] .'</div>';
            }
        }
    }


    // If we want to put data from API for importing products
    if (isset($_POST['submit_link'])) {
        $import_link = $_POST['import_link'];
        $response = wp_remote_get($import_link);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $products = json_decode($body, true);
            foreach ($products as $product) {

                $product_sku = $product['regNum'];

                // Check if a product with this SKU already exists
                $existing_product_id = get_product_by_sku( $product_sku );

                // If product existing, we update info
                if ( $existing_product_id ) {
                    $product_id = $existing_product_id->id;
                    wp_update_post(array(
                        'ID' => $product_id,
                        'post_title' => $product['title'],
                        'post_status' => 'publish',
                        'post_type' => 'product',
                        'sale_price' => $product['priceWS'],
                    ));

                    // Images properties
                    $regnum = intval($product['regNum']);
                    $url = "https://portal.facecounter.eu/pwc_api/api/v1/Stocks/Imports/Image/$regnum";
                    $headers = array(
                        'APIKey: 1B63ED8F-A419-441D-B468-01112A917CD3'
                    );

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $response = curl_exec($ch);
                    curl_close($ch);

                    // Save the image data to a file
                    $filename = 'image_'.$product['regNum'].'.jpg';
                    $file_path = WP_CONTENT_DIR . '/uploads/' . $filename; // Replace with the desired file path
                    $file = fopen($file_path, 'w');
                    fwrite($file, $response);
                    fclose($file);
                    if (!get_post_thumbnail_id($product_id)) {
                        // Attach the image to the product
                        $attachment_id = wp_insert_attachment(array(
                            'post_title' => $filename,
                            'post_content' => '',
                            'post_status' => 'inherit',
                            'post_mime_type' => 'image/jpeg' // Replace with the actual MIME type of the image
                        ), $file_path);

                        if (!is_wp_error($attachment_id)) {
                            set_post_thumbnail($existing_product_id->id, $attachment_id);
                        }
                    }


                    // Update properties of product
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
                    $elems = getElemsTrueOption($product['variety']);
                    foreach ($elems as $key => $value){
                        $meta_key = 'Odrůda_' . $key;
                        update_post_meta($product_id, $meta_key, $value);
                    }

                    $elems = getElemsTrueOption($product['style']);
                    foreach ($elems as $key => $value){
                        $meta_key = 'Styl_' . $key;
                        update_post_meta($product_id, $meta_key, $value);
                    }
                    update_post_meta($product_id, 'Sklenička', $product['glass']);
                    update_post_meta($product_id, '_price', $product['priceWS']);
                    update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
                    update_post_meta($product_id, '_regular_price', doubleval($product['retailPriceExclVAT']) * 1.21);
                    update_post_meta($product_id, '_sale_price', $product['eshopPriceVAT']);

                    echo '<div class="alert alert-success" role="alert">Updated info product: '.$product['title'].'</div>';
                } else {
                    // Create a new product
                    $new_product = array(
                        'post_title'    => $product['title'],
                        'post_status'   => 'publish',
                        'post_type'     => 'product',
                        'sale_price'    => $product['priceWS'],
                    );

                    $product_id = wp_insert_post($new_product);

                    // Images properties
                    $regnum = intval($product['regNum']);
                    $url = "https://portal.facecounter.eu/pwc_api/api/v1/Stocks/Imports/Image/$regnum";
                    $headers = array(
                        'APIKey: 1B63ED8F-A419-441D-B468-01112A917CD3'
                    );

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $response = curl_exec($ch);
                    curl_close($ch);

                    // Save the image data to a file
                    $filename = 'image_'.$product['regNum'].'.jpg';
                    $file_path = WP_CONTENT_DIR . '/uploads/' . $filename; // Replace with the desired file path
                    $file = fopen($file_path, 'w');
                    fwrite($file, $response);
                    fclose($file);
                    if (!get_post_thumbnail_id($product_id)) {
                        // Attach the image to the product
                        $attachment_id = wp_insert_attachment(array(
                            'post_title' => $filename,
                            'post_content' => '',
                            'post_status' => 'inherit',
                            'post_mime_type' => 'image/jpeg' // Replace with the actual MIME type of the image
                        ), $file_path);

                        if (!is_wp_error($attachment_id)) {
                            set_post_thumbnail($product_id, $attachment_id);
                        }
                    }

                    // All properties of Product
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
                    $elems = getElemsTrueOption($product['variety']);
                    foreach ($elems as $key => $value){
                        $meta_key = 'Odrůda_' . $key;
                        update_post_meta($product_id, $meta_key, $value);
                    }

                    $elems = getElemsTrueOption($product['style']);
                    foreach ($elems as $key => $value){
                        $meta_key = 'Styl_' . $key;
                        update_post_meta($product_id, $meta_key, $value);
                    }


                    update_post_meta($product_id, 'Sklenička', $product['glass']);
                    update_post_meta($product_id, '_price', $product['priceWS']);
                    update_post_meta($product_id, 'Počet lahví v kartonu', $product['bottlesInCarton']);
                    update_post_meta($product_id, '_regular_price', doubleval($product['retailPriceExclVAT']) * 1.21);
                    update_post_meta($product_id, '_sale_price', $product['eshopPriceVAT']);
                    echo '<div class="alert alert-success" role="alert">Product imported successfully: '. $product['title'] .'</div>';
                }
            }
        }
    }
    // Create new empty product
    if (isset($_POST['submit_empty_product'])) {
        $regNum = mt_rand(1000000000000, 9999999999999);

        $existing_product_id = get_product_by_sku( $regNum );

        while ($existing_product_id) {
            $regNum = mt_rand(1000000000000, 9999999999999);
            $existing_product_id = get_product_by_sku( $regNum );
        }

        $json_data = array(
            'post_title' => 'New Empty Product',
            'rangeOfGoods' => 4,
            'post_status'   => 'publish',
            'post_type'     => 'product',
            'regNum' => $regNum,
        );

        $product_id = wp_insert_post($json_data);

        wp_set_object_terms($product_id, explode(',', $json_data['rangeOfGoods']), 'product_cat');
        update_post_meta($product_id, '_sku', $json_data['regNum']);
        update_post_meta($product_id, '_product_details', $json_data);
        echo '<div class="alert alert-success" role="alert">New product has been created!</div>';
    }
}
