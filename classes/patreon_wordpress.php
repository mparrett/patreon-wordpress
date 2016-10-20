<?php 

/*
Plugin Name: Patreon
Plugin URI:
Description: Stay close with the Artists & Creators you're supporting
Version: 1.0
Author: Ben Parry
Author URI: http://uiux.me
*/

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

class Patreon_Wordpress
{
    private static $Patreon_Routing;
    private static $Patreon_Frontend;
    private static $Patreon_Posts;

    public function __construct()
    {
        include 'patreon_login.php';
        include 'patreon_routing.php';
        include 'patreon_frontend.php';
        include 'patreon_posts.php';
        include 'patreon_api.php';
        include 'patreon_oauth.php';

        self::$Patreon_Routing = new Patreon_Routing;
        self::$Patreon_Frontend = new Patreon_Frontend;
        self::$Patreon_Posts = new Patreon_Posts;

        add_action('wp_head', array($this, 'updatePatreonUser'));
    }

    public static function getPatreonUser($user)
    {
        /* get user meta data and query patreon api */
        $user_meta = get_user_meta($user->ID);
        
        if (isset($user_meta['patreon_access_token'][0])) {
            $api_client = new Patreon_API($user_meta['patreon_access_token'][0]);
            $user = $api_client->fetch_user();
            return $user;
        }

        return false;
    }

    /**
     * Updates the WordPress user metadata from the Patreon user
     */
    public static function updatePatreonUser()
    {
        /* check if current user is loggedin, get ID */
        $user = wp_get_current_user();
        if ($user == false) {
            return false;
        }

        /* query Patreon API to get users patreon details */
        $user_reponse = self::getPatreonUser($user);
        if ($user_reponse == false) {
            return false;
        }

        $patreon_user = $user_reponse['data']['attributes'];

        /* all the details you want to update on wordpress user account */
        update_user_meta($user->ID, 'patreon_user', $patreon_user['vanity']);
        update_user_meta($user->ID, 'patreon_created', $patreon_user['created']);
        update_user_meta($user->ID, 'user_firstname', $patreon_user['first_name']);
        update_user_meta($user->ID, 'user_lastname', $patreon_user['last_name']);
    }

    /**
     * Get the creator ID from the Patreon API
     */
    public static function getPatreonCreatorID()
    {
        $api_client = new Patreon_API(get_option('patreon-creators-access-token', false));
        $user_response = $api_client->fetch_campaign_and_patrons();

        if (empty($user_response)) {
            return false;
        }

        $creator_id = false;

        if (array_key_exists('data', $user_response)) {
            foreach ($user_response['included'] as $obj) {
                if ($obj["type"] == "user") {
                    $creator_id = $obj['id'];
                    break;
                }
            }
        }

        return $creator_id;
    }

    /**
     * Returns the Patronage level or false
     */
    public static function getUserPatronage()
    {
        $user = wp_get_current_user();
        if ($user == false) {
            return false;
        }

        /* get current users meta data */
        $user_meta = get_user_meta($user->ID);

        $user_response = self::getPatreonUser($user);

        if ($user_response == false) {
            return false;
        }

        $pledge = false;
        if (array_key_exists('included', $user_response)) {
            foreach ($user_response['included'] as $obj) {
                if ($obj["type"] == "pledge" && $obj["relationships"]["creator"]["data"]["id"] == get_option('patreon-creator-id', false)) {
                    $pledge = $obj;
                    break;
                }
            }
        }

        if ($pledge != false) {
            return self::getUserPatronageLevel($pledge);
        }

        return false;
    }

    /**
     * Get the Patronage amount in cents
     */
    public static function getUserPatronageLevel($pledge)
    {
        $patronage_level = 0;

        if (isset($pledge['attributes']['amount_cents'])) {
            $patronage_level = $pledge['attributes']['amount_cents'];
        }

        return $patronage_level;
    }

    /**
     * Check if Patronage is nonzero and positive
     */
    public static function isPatron()
    {
        $user_patronage = self::getUserPatronage($user);

        return $user_patronage > 0;
    }

    public static function getAuthURL()
    {
        $client_id = get_option('patreon-client-id', false);

        if ($client_id == false) {
            return '';
        }

        // TODO: Handle logged in Patreon

        $href = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$client_id.'&redirect_uri='.
            urlencode(site_url().'/patreon-authorization/');

        return $href;
    } 
    
}
