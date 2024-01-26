<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShortenLinkController extends Controller
{
    public static function shorten_organic_facebook_link(Request $request, $in_laravel = false)
    {

        $accept = [
            'link',
            'date',
        ];
        $data = $request->only($accept);
        if (!isset($data['link']) || !isset($data['date']))
            return response(['message' => "missing 'link' or 'date' parameter."]);
        $original_url = $data['link'];
        $link_for = 'Organic Facebook';
        $campaign = strtoupper(date('M-Y', strtotime($data['date']))) . '-PLAN';
        $utm_source = 'facebook';
        $utm_medium = 'social';
        $utm_campaign = $campaign;

        $query = array(
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
        );

        $extend_link = $original_url . '?' . http_build_query($query);

        $data = array(
            'long_url' => $extend_link
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.apilayer.com/short_url/hash",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: text/plain",
                "apikey: your api key"
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data['long_url']
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response_decoded = json_decode($response);
        if (!isset($response_decoded) || !isset($response_decoded->short_url))
            return false;
        $shorten_link = $response_decoded->short_url;
        $data = array(
            'original_url' => $original_url,
            'link_for' => $link_for,
            'campaign' => $campaign,
            'keyword' => null,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'extend_link' => $extend_link,
            'shorten_link' => $shorten_link,
        );
        if ($in_laravel) {
            return ['message' => 'success', 'data' => $data];
        }
        return response(['message' => 'success', 'data' => $data]);
    }

    public static function shorten_organic_instagram_link(Request $request, $in_laravel = false)
    {

        $accept = [
            'link',
        ];
        $data = $request->only($accept);
        if (!isset($data['link']))
            return response(['message' => "missing 'link' "]);
        $original_url = $data['link'];
        $link_for = 'Organic Insta';
        $campaign = ' may -PLAN';
        $utm_source = 'insta';
        $utm_medium = 'social';
        $utm_campaign = $campaign;

        $query = array(
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
        );

        $extend_link = $original_url . '?' . http_build_query($query);

        $data = array(
            'long_url' => $extend_link
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.apilayer.com/short_url/hash",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: text/plain",
                "apikey: your api key"
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data['long_url']
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response_decoded = json_decode($response);

        if (!isset($response_decoded) || !isset($response_decoded->short_url))
            return false;
        $shorten_link = $response_decoded->short_url;
        $data = array(
            'original_url' => $original_url,
            'link_for' => $link_for,
            'campaign' => $campaign,
            'keyword' => null,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'extend_link' => $extend_link,
            'shorten_link' => $shorten_link,
        );
        if ($in_laravel) {
            return ['message' => 'success', 'data' => $data];
        }
        return response(['message' => 'success', 'data' => $data]);
    }
}
