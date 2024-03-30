<?php
/*
Plugin Name: Cost of Goods
Description: Simple plugin for managing Cost of Goods.
Version: 1.0.0
Author: Spiros G.
Author URI: https://www.spirosg.dev/
*/

// Add Cost of Goods Field for Single Product in General Tab
add_action('woocommerce_product_options_general_product_data', 'product_cost_of_goods_field');

// Add Cost of Goods Field for Variations
add_action('woocommerce_product_after_variable_attributes', 'variation_cost_of_goods_field', 10, 3);

// Display Cost of Goods Field for Single Product in General Tab
function product_cost_of_goods_field() {
    if (isset($_GET['post'])) {
        $product = wc_get_product($_GET['post']);
        if ($product->is_type('simple')) {
            woocommerce_wp_text_input(array(
                'id' => 'cog_cost',
                'label' => __('Cost of Goods', 'woocommerce'),
                'placeholder' => '0.00',
                'desc_tip' => 'true',
                'description' => __('Enter the cost of goods for this product.', 'woocommerce'),
                'value' => get_post_meta(get_the_ID(), 'cog_cost', true),
            ));
        }
    }
}

// Display Cost of Goods Field for Variations
function variation_cost_of_goods_field($loop, $variation_data, $variation) {
    woocommerce_wp_text_input(array(
        'id' => 'cog_cost[' . $variation->ID . ']',
        'label' => __('Cost of Goods', 'woocommerce'),
        'placeholder' => '0.00',
        'desc_tip' => 'true',
        'description' => __('Enter the cost of goods for this variation.', 'woocommerce'),
        'value' => get_post_meta($variation->ID, 'cog_cost', true),
    ));
}

// Save Cost of Goods Field
function save_cost_of_goods_field($post_id) {
    if (isset($_POST['cog_cost'])) {
        $cost_of_goods = $_POST['cog_cost'];
        
        if (is_array($cost_of_goods)) {
            // For variations
            foreach ($cost_of_goods as $variation_id => $value) {
                update_post_meta($variation_id, 'cog_cost', wc_clean($value));
            }
        } else {
            // For single product
            update_post_meta($post_id, 'cog_cost', wc_clean($cost_of_goods));
        }
    }
}
add_action('woocommerce_process_product_meta', 'save_cost_of_goods_field');



