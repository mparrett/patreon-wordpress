<?php

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Patreon_API
{
    public $lastResponseCode;
    private $access_token;

    public function __construct($access_token)
    {
        if (!function_exists('curl_init')) {
            echo "This plugin requires CURL";
            die;
        }
        $this->access_token = $access_token;
    }

    public function fetch_user()
    {
        return $this->__get_json("current_user");
    }

    public function fetch_campaign_and_patrons()
    {
        return $this->__get_json("current_user/campaigns?include=rewards,creator,goals,pledges");
    }

    public function fetch_campaign()
    {
        return $this->__get_json("current_user/campaigns?include=rewards,creator,goals");
    }

    private function __get_json($suffix)
    {
        $api_endpoint = "https://api.patreon.com/oauth2/api/" . $suffix;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $authorization_header = "Authorization: Bearer " . $this->access_token;
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization_header));
        $this->lastResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ret = json_decode(curl_exec($ch), true);
        return $ret;
    }
}
