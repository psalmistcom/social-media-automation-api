<?php

namespace App\Http\Controllers;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;

class FacebookInsightsController extends Controller
{
    public function index()
    {
        $value = 0;
        $fb = new Facebook([
            'app_id' => '****',
            'app_secret' => '***',
            'default_graph_version' => 'v16.0',
        ]);

        $access_token = '';
        $page_id = '';
        try {
            // set up the facebook app access token for current environment.
            $token = $fb->get('/' . $page_id . '?fields=access_token', $access_token);
            $graphNode = $token->getGraphNode();
            $pageAccessToken = $graphNode->asArray();
            $tokens_response = $fb->get('/' . $page_id . '/insights?metric=impressions&metric_value=total_value&period=day&since=01-01-2023&until=01-02-2023', $pageAccessToken['access_token']);
            $ge = $tokens_response->getGraphEdge();
            $x = $ge->asArray();
            for ($time = 0; $time < 30; $time++) {
                $value += $x['0']['values'][$time]['value'];
            }
        } catch (FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
        return response()->json($value);
    }
}
