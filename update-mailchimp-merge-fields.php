<?php

function update_reward_points_in_mailchimp( $user_id, $new_reward_points ) {
		
    $is_subscribed = get_user_meta( $user_id, 'mailchimp_woocommerce_is_subscribed', true );

    $list_id = '0abcde98765';

    $hash = md5( strtolower( trim( $email ) ) );


    if ( $is_subscribed ) {
        $data = array( 'merge_fields' => array( 'MMERGE4' => $new_reward_points ) );

        mailchimp_debug( 'api.update_member', "Updating {$email}", $data );

        try {
            return $this->put( "lists/$list_id/members/$hash?skip_merge_validation=true", $data );
        } catch ( Exception $e ) {

            if ( $data['status'] !== 'subscribed' || ! mailchimp_string_contains( $e->getMessage(), 'compliance state' ) ) {

                mailchimp_debug( 'api.update_member', "Got an exception with {$email} when updating", $e );		
                throw $e;
            }

            $data['status'] = 'pending';
            $result         = $this->patch( "lists/$list_id/members/$hash?skip_merge_validation=true", $data );
            mailchimp_log( 'api', "{$email} was in compliance state, sending the double opt in message" );
            return $result;

        }

    }
    else {
        mailchimp_debug( 'api.update_member', "Will not update {$email} because they are not subscribed", $data );
    }
    
}