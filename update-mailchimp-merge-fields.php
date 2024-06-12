<?php


add_action( 'woocommerce_order_status_completed', 'update_reward_points_in_mailchimp' );


function update_reward_points_in_mailchimp( $order_id ) {

    $order = wc_get_order( $order_id );

    if ( is_object( $order) ) {

        $user_id = $order->get_user_id();
        $new_reward_points = (float) get_user_meta( $user_id, '_reward_points', true );
        $is_subscribed = get_user_meta( $user_id, 'mailchimp_woocommerce_is_subscribed', true );

        $list_id = '0abcde98765';

        $hash = md5( strtolower( trim( $email ) ) );

        if ( $is_subscribed ) {
            $data = array( 'merge_fields' => array( 'MMERGE4' => $new_reward_points ) );

            mailchimp_debug( 'api.update_member', "Updating {$email}", $data );

            try {
                $result = $this->put( "lists/$list_id/members/$hash?skip_merge_validation=true", $data );

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