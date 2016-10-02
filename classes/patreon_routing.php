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

class Patreon_Routing
{
    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('generate_rewrite_rules', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'query_vars'));
        add_action('parse_request', array($this, 'parse_request'));
        add_action('init', array($this, 'force_rewrite_rules'));
    }

    public function activate()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    public function deactivate()
    {
        global $wp_rewrite;
        remove_action('generate_rewrite_rules', 'add_rewrite_rules');
        $wp_rewrite->flush_rules();
    }

    public function force_rewrite_rules()
    {
        global $wp_rewrite;
        if (get_option('patreon-rewrite-rules-flushed', false) == false) {
            $wp_rewrite->flush_rules();
            update_option('patreon-rewrite-rules-flushed', true);
        }
    }

    public function add_rewrite_rules($wp_rewrite)
    {
        $rules = array(
            'patreon-authorization\/?$' => 'index.php?patreon-oauth=true',
        );

        $wp_rewrite->rules = $rules + (array)$wp_rewrite->rules;
    }

    public function query_vars($public_query_vars)
    {
        array_push($public_query_vars, 'patreon-oauth');
        array_push($public_query_vars, 'code');
        return $public_query_vars;
    }

    public function parse_request(&$wp)
    {
        if (!array_key_exists('patreon-oauth', $wp->query_vars)) {
            return;
        }

        if (!array_key_exists('code', $wp->query_vars)) {
            wp_redirect(home_url());
            exit;
        }

        if (!get_option('patreon-client-id', false) || !get_option('patreon-client-secret', false)) {
            /* redirect to homepage because of oauth client_id or secure_key error #HANDLE_ERROR */
            wp_redirect(home_url());
            exit;
        }

        $oauth_client = new Patreon_Oauth;

        $tokens = $oauth_client->get_tokens($wp->query_vars['code'], site_url().'/patreon-authorization/');

        if (array_key_exists('error', $tokens)) {
            /* redirect to homepage because of some error #HANDLE_ERROR */
            wp_redirect(home_url());
            exit;
        }

        /* redirect to homepage successfully #HANDLE_SUCCESS */
        $api_client = new Patreon_API($tokens['access_token']);
        $user_response = $api_client->fetch_user();
        $user = Patreon_Login::createUserFromPatreon($user_response, $tokens);

        wp_redirect(home_url(), 302);
        
        exit;
    }
}
