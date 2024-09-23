<?php

/*
  Plugin Name: Product Price Fetcher
  Plugin URI: https://github.com/kendoace/product-price-fetcher/
  GitHub Plugin URI: kendoace/product-price-fetcher/
  Description: Fetch Product Prices from .csv file.
  Version 1.21
  Author: Aleksandar
  Author URI: https://www.linkedin.com/in/aleksandar-petreski/
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include_once(dirname( __FILE__ ) . '/simple_html_dom.php');

class ProductPriceFetcherPlugin {
  
  function __construct() {
    add_action('admin_menu', array($this, 'ourMenu'));
    add_action('admin_init', array($this, 'ourSettings'));
  }

  function ourSettings() {
    add_settings_section('replacement-text-section', null, null, 'word-filter-options');
    register_setting('replacementFields', 'replacementText');
  }

  function ourMenu() {
    $mainPageHook = add_menu_page('Fetch Product Price', 'Product Price Fetcher', 'manage_options', 'fetchproductprice', array($this, 'fetchProductPricePage'), 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMCAyMEMxNS41MjI5IDIwIDIwIDE1LjUyMjkgMjAgMTBDMjAgNC40NzcxNCAxNS41MjI5IDAgMTAgMEM0LjQ3NzE0IDAgMCA0LjQ3NzE0IDAgMTBDMCAxNS41MjI5IDQuNDc3MTQgMjAgMTAgMjBaTTExLjk5IDcuNDQ2NjZMMTAuMDc4MSAxLjU2MjVMOC4xNjYyNiA3LjQ0NjY2SDEuOTc5MjhMNi45ODQ2NSAxMS4wODMzTDUuMDcyNzUgMTYuOTY3NEwxMC4wNzgxIDEzLjMzMDhMMTUuMDgzNSAxNi45Njc0TDEzLjE3MTYgMTEuMDgzM0wxOC4xNzcgNy40NDY2NkgxMS45OVoiIGZpbGw9IiNGRkRGOEQiLz4KPC9zdmc+', 100);
    // add_action("load-{$mainPageHook}", array($this, 'mainPageAssets'));
  }

  // function mainPageAssets() {
  //   wp_enqueue_style('filterAdminCss', plugin_dir_url(__FILE__) . 'styles.css');
  // }

  function handleForm() {
    if (wp_verify_nonce($_POST['ourNonce'], 'fetchProductPrice') AND current_user_can('manage_options')) {

      if(isset($_FILES["file"]) && $_FILES["file"]["error"] == 0) {

        $allowedFileTypes = array("csv" => "text/csv");
        $filename = $_FILES["file"]["name"];
        $filetype = $_FILES["file"]["type"];
        $filesize = $_FILES["file"]["size"];
   
        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
 
        //Check if file extension is valid.
        if(!array_key_exists($extension, $allowedFileTypes)) {
           throw new Exception("Invalid file Format");
        }
   
        // Verify file size - 25MB max.
        $maxsize = 25 * 1024 * 1024;
        if($filesize > $maxsize){
            throw new Exception("The File size is larger than the allowed limit.");
        }
   
        // Verify The MIME type of the file.
        if(in_array($filetype, $allowedFileTypes)) {
         
          $handle = fopen($_FILES['file']['tmp_name'], "r");
          $headers = fgetcsv($handle, 1000, ",");

          $updatedCount = 0; // Initialize the counter for updated products

          while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

            $get_url = $data[0];
            $sku = $data[1];
            $html = file_get_html( $get_url );

            if ($html) {

              foreach($html->find('body') as $article) {

                // Find the price of the current product
                if( $price_holder = $article->find('#base_price', 0) ) {
                    $item['price'] = trim( $price_holder->plaintext );
                } elseif( $price_holder = $article->find('#primary .woocommerce-Price-amount bdi', 0) ) {
                    $item['price'] = trim( $price_holder->plaintext );
                } elseif( $price_holder = $article->find('span.wsm-cat-price-price-value.wsmjs-product-price', 0) ) {
                    $item['price'] = trim( $price_holder->plaintext );
                } else {
                    $item['price'] = 'unknown';
                }
              }
              $html->clear();
              unset($html);

              $price = str_replace(" ", "", $item['price']);
              $price = preg_replace('/[\$,]/', '', $price);

              if ($price != 'unknown') {
                $product = new WC_Product(wc_get_product_id_by_sku($sku));
                if( $product instanceof WC_Product ) {
                  $regular_price = floatval($product->get_regular_price());
                  $new_price = floatval($price);
                  if ($regular_price < $new_price) {
                    $product_id = $product->get_id();
                    update_post_meta( $product_id, '_regular_price', $price );
                    update_post_meta( $product_id, '_price', $price );
                    wp_update_post( array( 'ID' => $product_id ) );
                    $updatedCount++;
                  }
                }
              }

            }

          }

          fclose($handle);
          ?>

          <div class="updated">
            <p><strong><?php echo $updatedCount; ?></strong> product prices were successfully updated.</p>
          </div>
        <?php } else { ?>
            <div class="error">
              <p>Sorry, you do not have permission to perform that action.</p>
            </div>
        <?php }
      }
    }
  }

  function fetchProductPricePage() { ?>
    <div class="wrap">
      <h1>Product Price Fetcher</h1>
      <?php if (isset($_POST["justsubmitted"])) $this->handleForm() ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="justsubmitted" value="true">
        <?php wp_nonce_field('fetchProductPrice', 'ourNonce') ?>
        <label for="fileSelect"><p>Upload <strong>.csv</strong> file with two columns: "url" and "sku".</p></label>
        <input id="fileSelect" type="file" name="file" accept=".csv" />
        <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Prices">
      </form>
    </div>
  <?php }

}

$ProductPriceFetcherPlugin = new ProductPriceFetcherPlugin();