<?php


add_action( 'woocommerce_order_status_completed', 'update_reward_points_in_mailchimp' );

function update_reward_points_in_mailchimp( $order_id ) {

   
    $order = wc_get_order( $order_id );

    if ( is_object( $order) ) { 

        $user_id = $order->get_user_id();

        $user = new WP_User($user_id);

        $is_subscribed = false;

        if ( $user ) {
            
            $email = $user->user_email;
            $new_reward_points = (float) get_user_meta( $user_id, '_reward_points', true );
            $is_subscribed = get_user_meta( $user_id, 'mailchimp_woocommerce_is_subscribed', true );

            $list_id = 'abcde';
            $username = 'your_username';
            $api_key = 'YOUR-KEY-us15';

            $hash = md5( strtolower( trim( $email ) ) );

        } 
        
        if ( $is_subscribed ) {
            
            $data = array( 
                'email_address' => $email,
                'merge_fields' => array( 'MMERGE4' => $new_reward_points ) 
            );

            mailchimp_debug( 'api.update_member', "Updating {$email}", $data );

            try {
                $result = apd_mailchimp_api_request( "lists/$list_id/members/$hash?skip_merge_validation=true", $data, $username, $api_key );
                mailchimp_debug( 'api.update_member', "Get results with {$email} when updating", $result );		
            } catch ( Exception $e ) {
                mailchimp_debug( 'api.update_member', "Got an exception with {$email} when updating", $e );		
            }
        }
        else {
            mailchimp_debug( 'api.update_member', "Will not update {$email} because they are not subscribed", $data );
        }
    }       
}


/**
 * @param $url
 * @param $body
 *
 * @return array|bool|mixed|object|null
 * @throws MailChimp_WooCommerce_Error
 * @throws MailChimp_WooCommerce_RateLimitError
 * @throws MailChimp_WooCommerce_ServerError
 */
function apd_mailchimp_api_request( $url, $body, $username, $api_key ) {

    $curl = curl_init();

    $json = json_encode( $body );

    $env = mailchimp_environment_variables();

    $curl_options = array(
        CURLOPT_USERPWD        => "{$username}:{$api_key}",
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_URL            => 'https://us15.api.mailchimp.com/3.0/' . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLINFO_HEADER_OUT    => true,
        CURLOPT_HTTPHEADER     => array(
                'content-type: application/json',
                'accept: application/json',
                "user-agent: MailChimp for WooCommerce/{$env->version}; PHP/{$env->php_version}; WordPress/{$env->wp_version}; Woo/{$env->wc_version};",
            )
    );

    // automatically set the proper outbound IP address
    if ( ( $outbound_ip = mailchimp_get_outbound_ip() ) && ! in_array( $outbound_ip, mailchimp_common_loopback_ips() ) ) {
        $curl_options[ CURLOPT_INTERFACE ] = $outbound_ip;
    }

    $curl_options[ CURLOPT_POSTFIELDS ] = $json;

    curl_setopt_array( $curl, $curl_options );

    $response = curl_exec( $curl );

    $err  = curl_error( $curl );
    $info = curl_getinfo( $curl );
    curl_close( $curl );

    if ( $err ) {
        return new MailChimp_WooCommerce_Error( 'CURL error :: ' . $err, 500 );
    }

    $data = json_decode( $response, true );

    return $data;
}