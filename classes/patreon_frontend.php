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

class Patreon_Frontend
{
    public function __construct()
    {
        add_action('login_form', array($this, 'showPatreonButton' ));
        add_action('register_form', array($this, 'showPatreonButton' ));

        add_shortcode('patreon_content', array($this, 'embedPatreonContent'));
        add_filter('the_content', array($this, 'protectContentFromUsers'));

        add_shortcode('patreon_login_button', array($this, 'getPatreonButton'));
    }

    public function showPatreonButton()
    {
        echo $this->getPatreonButton();
    }

    public function getPatreonButton()
    {
        $log_in_img = PATREON_PLUGIN_URL . 'img/log-in-with-patreon-wide@2x.png';

        $href = Patreon_Wordpress::getAuthURL();
        if (!$href) {
            return '';
        }

        /* inline styles, for shame */
        $ret = '';
        $ret .= get_option('patreon-above-button-html', '');

        if (isset($_REQUEST['patreon-msg']) && $_REQUEST['patreon-msg'] == 'login_with_patreon') {
            $ret .= get_option('patreon-login-wp-message-html', '');
        } else {
            $ret .= apply_filters('ptrn/login_button', '<a href="'.$href.'" class="ptrn-button" data-ptrn_nonce="' . 
                wp_create_nonce('patreon-nonce').'"><img src="'.$log_in_img.'" width="272" height="42" /></a>');
        }
        return $ret;
    }

    public function currentPageURL()
    {
        $pageURL = 'http';

        if (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }

        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    public function getPaywallButton1()
    {
        // patreon banner when user patronage not high enough
        // TODO: Pull image dimensions from settings
        $paywall_img = get_option('patreon-paywall-img-url', '');
        $paywall_img_elem = '<img width="375" height="138" class="patreon_paywall_btn_1" src="'.$paywall_img.'"/>';
        $creator_id = get_option('patreon-creator-id', '');

        if ($creator_id == '') {
            // No valid Patreon integration (expired token, etc.?)
            return $paywall_img_elem;    
        }

        $current_url = urlencode(self::currentPageURL());
 
        $ret = '<a href="'.Patreon_Wordpress::getAuthURL().'">'.$paywall_img_elem.'</a>';

        return $ret;
    }
    
    public function getPaywallButton2()
    {
        $paywall_img2 = get_option('patreon-paywall-img-url-2', '');
        $paywall_img_elem2 = '<img width="375" height="138" class="patreon_paywall_btn_2" src="'.$paywall_img2.'"/>';
        $creator_id = get_option('patreon-creator-id', '');

        if ($creator_id == '') {
            // No valid Patreon integration (expired token, etc.?)
            return $paywall_img_elem2;
        }

        $current_url = urlencode(self::currentPageURL());

        $ret = '';
        $ret .= '<a href="https://www.patreon.com/bePatron?u='.$creator_id.'&redirect_uri='.$current_url.'">'.$paywall_img_elem2.'</a>';
        return $ret;
    }
    

    public function displayPatreonCampaignBanner()
    {
        // Display the actual buttons
        $ret = $this->getPaywallButton1();

        $paywall_img2 = get_option('patreon-paywall-img-url-2', '');
        if ($paywall_img2) {
            $ret .= $this->getPaywallButton2();
        }

        return $ret;
    }

    public function embedPatreonContent($args)
    {
        /* example shortcode [patreon_content slug="test-example"]

        /* check if shortcode has slug parameter */
        if (!isset($args['slug']))
            return;

        /* get patreon-content post with matching url slug */
        $patreon_content = get_page_by_path($args['slug'], OBJECT, 'patreon-content');

        if ($patreon_content == false) {
            return 'Patreon content not found.';
        }

        $patreon_level = get_post_meta($patreon_content->ID, 'patreon-level', true);

        if ($patreon_level == 0) {
            return $patreon_content->post_content;
        }

        $user_patronage = Patreon_Wordpress::getUserPatronage();

        if ($user_patronage != false) {
            if (is_numeric($patreon_level) && $user_patronage >= ($patreon_level*100)) {
                return $patreon_content->post_content;
            }
        }

        // Video
        if (isset($args['youtube_id']) && isset($args['youtube_width']) && is_numeric($args['youtube_width']) && isset($args['youtube_height']) && is_numeric($args['youtube_height'])) {
            return '<iframe width="'.$args['youtube_width'].'" height="'.$args['youtube_height'].'" src="https://www.youtube.com/embed/'.$args['youtube_id'].'?rel=0&amp;controls=0&amp;showinfo=0" frameborder="0" allowfullscreen></iframe>';
        }
    
        // Not video
        return self::displayPatreonCampaignBanner();
    }

    public function protectContentFromUsers($content)
    {
        global $post;

        $post_type = get_post_type();

        if ((is_singular('patreon-content') && $post_type == 'patreon-content') 
            || (is_singular() && ($post_type == 'post' || $post_type == 'page'))) {
            $patreon_level = get_post_meta($post->ID, 'patreon-level', true);

            if ($patreon_level == 0) {
                return $content;
            }

            $user_patronage = Patreon_Wordpress::getUserPatronage();

            if ($user_patronage == false || $user_patronage < ($patreon_level*100)) {
                $content = self::displayPatreonCampaignBanner();
            }
        }

        return $content;
    }
}
