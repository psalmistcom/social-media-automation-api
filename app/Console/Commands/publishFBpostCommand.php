<?php

namespace App\Console\Commands;

use App\Http\Controllers\PostCronController;
use App\Http\Controllers\ShortenLinkController;
use DateTime;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;

class publishFBpostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:publish-f-bpost-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $posts = PostCronController::get_facebook_posts(true);
        if ((isset($posts['images']) && !empty($posts['images'])) ||
            (isset($posts['links']) && !empty($posts['links']))
        ) {
            // set up the facebook object for current environment.
            $fb = new Facebook([
                'app_id' => 'your app id',
                'app_secret' => 'your app secret',
                'default_graph_version' => 'v16.0',
            ]);

            // set up the facebook app access token for current environment.
            $access_token = 'your access token';

            $page_access_tokens = [];
            $tokens_response = $fb->get('/me/accounts?limit=1000&fields=name,access_token&access_token=' . $access_token);
            $ge = $tokens_response->getGraphEdge();
            $data_response_decoded = $ge->asArray(); // return array without data key
            if (!empty($data_response_decoded)) {
                foreach ($data_response_decoded as $page_access_token) {
                    $page_access_tokens[$page_access_token['id']] = $page_access_token;
                }
            }

            foreach ($posts['images'] as $image_post) {
                $dateToPublish = $image_post['post_date'];
                $date = new DateTime($dateToPublish);
                $timestamp = $date->format('U');
                $dateToPublish =  $timestamp;
                $post_id = $image_post['post_id'];
                $page_id = $image_post['page_id'];
                $message = $image_post['text'];
                $url = $image_post['url'];
                preg_match_all("/https:\/\/drive.google.com\/file\/d\/(.*)\//", $url, $matches);
                if (isset($matches[1][0])) {
                    $id = $matches[1][0];
                    $url = "https://drive.google.com/uc?export=download&id=" . $id . "&t=" . strtotime('now');
                }

                $newToken = '';
                if (array_key_exists($page_id, $page_access_tokens)) {
                    $newToken = $page_access_tokens[$page_id];
                } else {
                    try {
                        // Returns a `FacebookFacebookResponse` object
                        $response = $fb->get(
                            '/' . $page_id . '?fields=access_token&access_token=' .
                                $access_token
                        );
                    } catch (FacebookResponseException $e) {
                        $error = 'Generating new access token, Graph returned an error: ' . $e->getMessage();
                        // update the post as published fail.
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                        continue;
                    } catch (FacebookSDKException $e) {
                        $error = 'Generating new access token, Facebook SDK returned an error: ' . $e->getMessage();
                        // update the post as published fail.
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                        continue;
                    }
                    $graphNode = $response->getGraphNode();
                    $pageAccessToken = $graphNode->asArray();
                    $page_access_tokens[$page_id] = $pageAccessToken;
                    $newToken = $page_access_tokens[$page_id];
                }
                if ($newToken == '') {
                    $error = 'empty access token.';
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                }

                $page_access_token = $newToken;

                $fb->setDefaultAccessToken($page_access_token['access_token']);
                $insert_result = '';

                $payload = array(
                    'message' => $message,
                    'url' => $url,
                    'published' => 'false',
                    'scheduled_publish_time' => $dateToPublish,
                );

                try {
                    $insert_result = $fb->sendRequest('POST', "/" . $page_id . "/photos", $payload);
                } catch (FacebookResponseException $e) {
                    if (isset($e->getResponseData()['error']['error_user_msg']))
                        $error = 'Posting photo, Graph returned an error: ' . $e->getResponseData()['error']['error_user_msg'];
                    else
                        $error = 'Posting photo, Graph returned an error: ' . $e->getMessage();
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                } catch (FacebookSDKException $e) {
                    $error = 'Posting photo, Facebook SDK returned an error: ' . $e->getMessage();
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                }

                if (isset($insert_result) && $insert_result != '') {
                    $readable_result = $insert_result->getGraphNode()->asArray();

                    if (isset($readable_result['id']) && !empty($readable_result['id'])) {
                        $success_msg = 'the post ' . $readable_result['id'] . ' was created';
                        $fb_post_id = explode('_', $readable_result['id']);
                        $post_link = 'https://facebook.com/' . end($fb_post_id);
                        $post_request = new HttpRequest([
                            'is_published' => 1,
                            'cron_error_message' => $success_msg,
                            'cron_post_id' => $readable_result['id'],
                            'cron_post_link' => $post_link,
                        ]);
                        echo $success_msg;
                        echo '<br />';
                        echo $post_link;
                        echo '<br /><br />';
                        PostCronController::update_facebook_post($post_request, $post_id);
                    } else {
                        $error = 'something is wrong in creating Post ';
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                    }
                }
            }

            foreach ($posts['links'] as $link_post) {
                $dateToPublish = $link_post['post_datetime'];
                $date = new DateTime($dateToPublish);
                $timestamp = $date->format('U');
                $dateToPublish =  $timestamp;
                $post_id = $link_post['post_id'];
                $page_id = $link_post['page_id'];
                $message = $link_post['text'];
                $url = $link_post['url'];
                $post_request = new HttpRequest([
                    'link' => $url,
                    'date' => date('Y-m-d', $dateToPublish),
                ]);
                $shorten_link = ShortenLinkController::shorten_organic_facebook_link($post_request, true);
                $shorten_link = $shorten_link['data']['shorten_link'];

                $newToken = '';
                if (array_key_exists($page_id, $page_access_tokens)) {
                    $newToken = $page_access_tokens[$page_id];
                } else {
                    try {
                        // Returns a `FacebookFacebookResponse` object
                        $response = $fb->get(
                            '/' . $page_id . '?fields=access_token&access_token=' .
                                $access_token
                        );
                    } catch (FacebookResponseException $e) {
                        $error = 'Generating new access token, Graph returned an error: ' . $e->getMessage();
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                        continue;
                    } catch (FacebookSDKException $e) {
                        $error = 'Generating new access token, Facebook SDK returned an error: ' . $e->getMessage();
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                        continue;
                    }
                    $graphNode = $response->getGraphNode();
                    $pageAccessToken = $graphNode->asArray();
                    $page_access_tokens[$page_id] = $pageAccessToken;
                    $newToken = $page_access_tokens[$page_id];
                }

                if ($newToken == '') {
                    $error = 'empty access token.';
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                }
                $page_access_token = $newToken;
                $fb->setDefaultAccessToken($page_access_token['access_token']);
                $insert_result = '';

                $payload = array(
                    'message' => $message,
                    'link' => $shorten_link,
                    'published' => 'false',
                    'scheduled_publish_time' => $dateToPublish,
                );
                try {
                    $insert_result = $fb->sendRequest('POST', "/" . $page_id . "/feed", $payload);
                } catch (FacebookResponseException $e) {
                    $error = 'Posting link, Graph returned an error: ' . $e->getMessage();
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                } catch (FacebookSDKException $e) {
                    $error = 'Posting link, Facebook SDK returned an error: ' . $e->getMessage();
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_facebook_post($post_request, $post_id);
                    echo $error;
                    echo '<br />';
                    continue;
                }

                if (isset($insert_result) && $insert_result != '') {
                    $readable_result = $insert_result->getGraphNode()->asArray();

                    if (isset($readable_result['id']) && !empty($readable_result['id'])) {
                        $success_msg = 'the post ' . $readable_result['id'] . ' was created';
                        $fb_post_id = explode('_', $readable_result['id']);
                        $post_link = 'https://facebook.com/' . end($fb_post_id);
                        $post_request = new HttpRequest([
                            'is_published' => 1,
                            'cron_error_message' => $success_msg,
                            'cron_post_id' => $readable_result['id'],
                            'cron_post_link' => $post_link,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);

                        echo $success_msg;
                        echo '<br />';
                        echo $post_link;
                        echo '<br /><br />';
                    } else {
                        $error = 'something is wrong in creating Post ';
                        $post_request = new HttpRequest([
                            'is_published' => 2,
                            'cron_error_message' => $error,
                        ]);
                        PostCronController::update_facebook_post($post_request, $post_id);
                        echo $error;
                        echo '<br />';
                    }
                }
            }
        } else {
            echo "There is no posts need to be published currently.";
        }
        return 0;
    }
}
