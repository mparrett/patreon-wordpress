<?php

/*
Plugin Name: Patreon
Plugin URI:
Description: Stay close with the Artists & Creators you're supporting
Version: 1.0
Author: Ben Parry
Author URI: http://uiux.me
*/

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (is_admin()) {
    add_action('admin_menu', 'patreon_plugin_setup');
    add_action('admin_init', 'patreon_plugin_register_settings');
}

function patreon_plugin_register_settings()
{
    // whitelist options
    register_setting('patreon-options', 'patreon-disable-auto-login');
    register_setting('patreon-options', 'patreon-client-id');
    register_setting('patreon-options', 'patreon-client-secret');
    register_setting('patreon-options', 'patreon-creators-access-token');
    register_setting('patreon-options', 'patreon-creators-refresh-token');
    register_setting('patreon-options', 'patreon-creators-access-token-expires');
    register_setting('patreon-options', 'patreon-creator-id');
    register_setting('patreon-options', 'patreon-paywall-img-url');
    register_setting('patreon-options', 'patreon-paywall-img-url-2');
    register_setting('patreon-options', 'patreon-rewrite-rules-flushed');
    register_setting('patreon-options', 'patreon-above-button-html');
    register_setting('patreon-options', 'patreon-login-wp-message-html');
    register_setting('patreon-options', 'patreon-auth-success-url');
    register_setting('patreon-options', 'patreon-auth-error-url');
}

function patreon_plugin_setup()
{
    add_menu_page('Patreon', 'Patreon', 'manage_options', 'patreon-plugin', 'patreon_plugin_setup_page');
}

function patreon_plugin_setup_page()
{
    // Set the default for above button html
    $default_above_button = '<style type="text/css">
		.ptrn-button{display:block;margin-bottom:20px!important;}
		.ptrn-button img {width: 272px; height:42px;}
		.patreon-msg {-webkit-border-radius: 6px;-moz-border-radius: 6px;-ms-border-radius: 6px;-o-border-radius: 6px;border-radius: 6px;'.
            'padding:8px;margin-bottom:20px!important;display:block;border:1px solid #E6461A;background-color:#484848;color:#ffffff;}</style>';
            
    if (!get_option('patreon-above-button-html', false)) {
        update_option('patreon-above-button-html', $default_above_button);
    }

    if (!get_option('patreon-login-wp-message-html', false)) {
        update_option('patreon-login-wp-message-html', '<p class="patreon-msg">You can now login with your wordpress username/password.</p>');
    }

    if (!get_option('patreon-paywall-img-url', false)) {
        update_option('patreon-paywall-img-url', 'https://s3-us-west-1.amazonaws.com/widget-images/become-patron-widget-medium.png');
    }
    
    if (!get_option('patreon-paywall-img-url-2', false)) {
        update_option('patreon-paywall-img-url-2', 'https://s3-us-west-1.amazonaws.com/widget-images/become-patron-widget-medium.png');
    }
    
    if (!get_option('patreon-auth-error-url', false)) {
        update_option('patreon-auth-error-url', home_url());
    }

    if (!get_option('patreon-auth-success-url', false)) {
        update_option('patreon-auth-success-url', home_url());
    }
    
    $message = '';

    /* update Patreon creator ID on page load */
    
    if (get_option('patreon-client-id', false) &&
        get_option('patreon-client-secret', false) &&
        get_option('patreon-creators-access-token', false)) {
        
        //$creator_id = Patreon_Wordpress::getPatreonCreatorID();

        if (!$creator_id) {
            /*
            // Attempt to refresh token
            if (Patreon_Wordpress::refreshCreatorToken()) {
                $message = 'Refreshed creator tokens.';
                // Retry getting creator ID
                $creator_id = Patreon_Wordpress::getPatreonCreatorID();
            } else {
                $message = 'Unable to refresh creator tokens.';
            }
            */
        } else {
            //update_option('patreon-creator-id', $creator_id);
        }
    }
    
    $creator_id = get_option('patreon-creator-id');

    $creator_expiry = get_option('patreon-creators-access-token-expires', false);
    
    if ($creator_expiry === false) {
        // Not sure when it expires -- likely entered manually from initial setup
        $creator_expiry = '???';
    } else {
        $creator_expiry = $creator_expiry - time();
        
        if ($creator_expiry >= 86400) {
            $creator_expiry = floor($creator_expiry / 86400) . ' day(s)';
        } elseif ($creator_expiry >= 3600) {
            $creator_expiry = floor($creator_expiry / 3600) . ' hour(s)';
        } elseif ($creator_expiry > 0) {
            $creator_expiry = floor($creator_expiry / 60) . ' minute(s)';
        } else {
            $creator_expiry = '???';
        }
    } ?>

<h1>Patreon API Settings</h1>

<form method="post" action="options.php">
    <?php settings_fields('patreon-options'); ?>
    <?php do_settings_sections('patreon-options'); ?>

    <?php if ($message) {
        ?>
        <br>
        <p><php echo $message; ?></p><br/>
        <?php 
    } ?>

    <?php if (!$creator_id) {
        ?>
    <br/>
    <p>Cannot retrieve creator ID. Error connecting with Patreon.</p>
    <?php 
    } ?>
    
    <h2>API Settings</h2>
    <table class="form-table">
        
        <tr valign="top">
        <th scope="row">Plugin Version</th>
        <td><input type="text" value="<?php echo PATREON_PLUGIN_VERSION; ?>" disabled class="large-text" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Redirect URI</th>
        <td><input type="text" value="<?php echo site_url().'/patreon-authorization/'; ?>" disabled class="large-text" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Client ID</th>
        <td><input type="text" name="patreon-client-id" value="<?php echo esc_attr(get_option('patreon-client-id', '')); ?>" class="large-text" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Client Secret</th>
        <td><input type="text" name="patreon-client-secret" value="<?php echo esc_attr(get_option('patreon-client-secret', '')); ?>" class="large-text" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Creator's Access Token</th>
        <td><input type="text" name="patreon-creators-access-token" value="<?php echo esc_attr(get_option('patreon-creators-access-token', '')); ?>" class="large-text" /> Expires in <?php echo $creator_expiry; ?></td>
        </tr>

        <tr valign="top">
        <th scope="row">Creator's Refresh Token</th>
        <td><input type="text" name="patreon-creators-refresh-token" value="<?php echo esc_attr(get_option('patreon-creators-refresh-token', '')); ?>" class="large-text" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Creator ID</th>
        <td><input type="text" name="patreon-creator-id" value="<?php echo esc_attr(get_option('patreon-creator-id', '')); ?>" class="large-text" /></td>
        </tr>
    
        <tr valign="top">
        <th scope="row">Button URL to login as existing Patron</th>
        <td><input type="text" name="patreon-paywall-img-url" value="<?php echo esc_attr(get_option('patreon-paywall-img-url', '')); ?>" class="large-text" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Button URL for not yet a patron (or not yet paying enough)</th>
        <td><input type="text" name="patreon-paywall-img-url-2" value="<?php echo esc_attr(get_option('patreon-paywall-img-url-2', '')); ?>" class="large-text" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Auth Success URL: Where to send user after successful auth with Patreon</th>
        <td><input type="text" name="patreon-auth-success-url" value="<?php echo esc_attr(get_option('patreon-auth-success-url', '')); ?>" class="large-text" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Auth Error URL: Where to send user if auth error with Patreon (defaults to home)</th>
        <td><input type="text" name="patreon-auth-error-url" value="<?php echo esc_attr(get_option('patreon-auth-error-url', '')); ?>" class="large-text" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Above Button HTML/CSS</th>
        <td><textarea name="patreon-above-button-html" style="width:100%;" rows="6" cols="20"><?php echo esc_textarea(get_option('patreon-above-button-html', '')); ?></textarea></td>
        </tr>

        <tr valign="top">
        <th scope="row">Message once authenticated (Login to WP)</th>
        <td><textarea name="patreon-login-wp-message-html" style="width:100%;" rows="3" cols="20"><?php echo esc_textarea(get_option('patreon-login-wp-message-html', '')); ?></textarea></td>
        </tr>
    </table>

    <?php submit_button(); ?>
</form>

<div>
    <h3>Helpful Info</h3>
    
    <h4>Short codes:</h4>
    <table>
    <thead>
        <tr><th>Code</th><th>Description</th><th>Args</th></tr>
    </thead>
    <tbody>
        <tr><td><em>patreon_login_button</em></td><td>Displays a "Login with Patreon" button</td><td></td></tr>
        <tr><td><em>patreon_content</em></td><td>Displays content from associated slug</td><td>slug</td></tr>
        <tr><td><em>patreon_level</em></td><td>Displays Patreon contribution amount</td><td>fmt,alt</td></tr>
    </tbody>
    </table>
</div>

<?php

}

?>
