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

class Patreon_Login
{
    /**
     * Create a new user or login, utilizing the Patreon API's user info response
     */
    public static function createUserFromPatreon($user_response, $tokens)
    {
        global $wpdb;

        $patreon_user = $user_response['data']['attributes'];

        $email = $patreon_user['email'];

        $repl_name = !empty($patreon_user['first_name']) ? $patreon_user['first_name'] : 'Patron';
        
        if (!empty($patreon_user['last_name']))
            $repl_name .= '_' . $patreon_user['last_name'];

        $name = strtolower(str_replace(' ', '', $repl_name));
        
        if (validate_username($name) && username_exists($name) == false) {
            $username = sanitize_user($name, true);
        } else {
            // Assign a username based on the first part of their email
            $username = explode('@', $patreon_user['email']);
            $username = strtolower(sanitize_user($username[0]));
        }

        // If it already exists, or the username with a suffix exists, append the next sequential suffix
        if (username_exists($username)) {
            $suffix = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 + SUBSTR(user_login, %d) FROM $wpdb->users WHERE user_login REGEXP %s ORDER BY 1 DESC LIMIT 1",
                strlen($username) + 2, '^' . $username . '(\.[0-9]+)?$'));

            if (!empty($suffix)) {
                $username .= ".{$suffix}";
            }
        }

        $user = get_user_by('email', $email);

        // Handle creating new user
        if (!$user) {
            $user = self::createAndLoginNewUser($username, $email, $tokens, $patreon_user);
            if ($user) {
                // Login
                self::_login_user($user);
            } else {
                // Error
            }
        } else {
            // Valid existing user

            /* update user meta data with patreon data */
            self::_update_all_meta($user->ID, $tokens, $patreon_user);

            // Uncommon: log user into existing wordpress account with matching email address -- if not disabled
            if (get_option('patreon-disable-auto-login', false)) {
                // Redirect and manual login
                wp_redirect(wp_login_url().'?patreon-msg=login_with_patreon', '301');
                exit;
            }

            // Login
            self::_login_user($user);
        }
    }

    /**
     * Create wordpress user if no account exists with provided email address
     */
    private static function createAndLoginNewUser($username, $email, $tokens, $patreon_user)
    {
        $user_id = wp_create_user($username, wp_generate_password(12, false), $email);

        if (!$user_id) {
            /* wordpress account creation failed #HANDLE_ERROR */
            return false;
        }

        $user = get_user_by('id', $user_id);

        // Update WP user meta data with patreon data
        self::_update_all_meta($user_id, $tokens, $patreon_user);

        // Update extra minted field
        update_user_meta($user_id, 'patreon_token_minted', microtime());
        
        return $user;
    }

    /**
     * Logins in an existing user fetched with get_user_by
     */
    private static function _login_user($user)
    {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);
    }

    /**
     * Updates WordPress metadata from Patreon user
     */
    private static function _update_all_meta($user_id, $tokens, $patreon_user)
    {
        update_user_meta($user_id, 'patreon_refresh_token', $tokens['refresh_token']);
        update_user_meta($user_id, 'patreon_access_token', $tokens['access_token']);
        update_user_meta($user_id, 'patreon_user', $patreon_user['vanity']);
        update_user_meta($user_id, 'patreon_created', $patreon_user['created']);
        update_user_meta($user_id, 'user_firstname', $patreon_user['first_name']);
        update_user_meta($user_id, 'user_lastname', $patreon_user['last_name']);
    }
}
