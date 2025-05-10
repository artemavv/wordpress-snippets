<?php 

class Manage_Product_Features {

  
    public static function save_product_features($post_id) {
        // Verify nonce
        if (!isset($_POST['product_features_nonce']) || !wp_verify_nonce($_POST['product_features_nonce'], 'save_product_features')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save features text
        if (isset($_POST['product_features'])) {

            $textbox_version = self::format_product_features_for_save($_POST['product_features']); 

            global $wpdb;

            // Use direct queries to bypass any cache that could be used by get_post_meta
            $version_before_edits = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$post_id} AND meta_key = 'add-features-backup'");

            $jet_element_version = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$post_id} AND meta_key = 'add-features'"); // JE editor is likely already saved its version to the DB

/*
            echo('TEXTOX<br>');
            print_r(serialize($textbox_version));
            echo('<br><br>BEFORE<br>');
            print_r($version_before_edits);
            echo('<br><br>AFTER<br>');
            print_r($jet_element_version);
  */  

            if ( ! empty($version_before_edits) ) { // we have an original

                $latest_version = self::compare_and_select_latest_version(
                    serialize($textbox_version), 
                    $jet_element_version, 
                    $version_before_edits
                );
            }
            else {
                // for the case when there were no features saved, use the any version which is not empty
                $latest_version = $textbox_version ? $textbox_version : unserialize($jet_element_version); 
            }
            /*
            echo('<br><br>FINAL<br>');
            print_r($latest_version);
              die();
            */
            update_post_meta($post_id, 'add-features', unserialize($latest_version));
        }
    }

    /**
     * Format the product features to match the format used by the original editor ( provided by JetPlugin).
     * 
     * @param string $product_features The product features to format.
     * @return array The formatted product features.
     */
    public static function format_product_features_for_save( $product_features ) {
        
        $features_array = explode("\n", $product_features);

        $features_array_prepared = array();

        $count = 0;
        foreach ( $features_array as $feature ) {

            if ( strpos($feature, '*') === 0 ) {
                $feature = trim(str_replace('*', '', $feature));
            }

            if ( !empty($feature) ) {
                $features_array_prepared[ 'item-' . $count] = array( 'add-features' => stripslashes($feature) );

                $count++;
            }
        }

        return $features_array_prepared;
    }

    public static function backup_product_features() {

        $post_id = $_GET['post'];

        if ( ! $post_id ) {
            return;
        }

        global $wpdb;

        // Use direct query to get the features and bypass any cache that could be used by get_post_meta
        $features = $wpdb->get_var("
            SELECT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.post_id = {$post_id} 
            AND pm.meta_key = 'add-features'
            AND p.post_type = 'product'
        ");

        if ( !empty($features) ) { 

            update_post_meta($post_id, 'add-features-backup', unserialize($features));
        }
        
    }

    /**
     * Chooses the latest version of the features
     * 
     * Originally there was an editor provided by JetElements plugin, which saved the features in a serialized format,
     * and used separate text input for each feature line.
     * 
     * Since then we have introduced a new metabox with textarea input, which allows to edit all feature lines at once.
     * 
     * Original editor still saves the feature lines, with default priority 10.
     * Our textbox uses priority 1000, so it runs after the original editor.
     * 
     * We want to allow user to use both editors so we compare version produced by the original editor with the version produced by the textbox.
     * If they are different, we select the latest version (which was actually introduced by user)
     */
    public static function compare_and_select_latest_version($textarea_version, $jet_el_version, $version_before_edits) {

        if ( $textarea_version == $version_before_edits ) {
            return $jet_el_version;
        }

        return $textarea_version;
    }

    public static function render_product_features_meta_box($post) {
        // Add nonce field
        wp_nonce_field('save_product_features', 'product_features_nonce');

        // Get saved value
        $features = get_post_meta($post->ID, 'add-features', true);

        $features_list = '';

        if ( is_array($features) ) {
            foreach ( $features as $feature ) {
                $features_list .= '* ' . $feature['add-features'] . "\n";
            }
        }

        ?>
        <textarea name="product_features" style="width: 100%; min-height: 200px;"><?php echo esc_textarea($features_list); ?></textarea>
        <?php
    }

}

add_action('add_meta_boxes', function() {
    add_meta_box( 'product-features', 'Product Features', array( 'Manage_Product_Features', 'render_product_features_meta_box'), 'product', 'normal', 'high' );
});

// we need to backup the features before the new revision is sent by JetElements editos or by textbox 
add_action('load-post.php', array('Manage_Product_Features', 'backup_product_features')); 

// we need low priority to make sure it runs after other plugins
add_action('save_post', array('Manage_Product_Features', 'save_product_features'), 1000, 1); 