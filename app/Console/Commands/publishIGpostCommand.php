<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PostCronController;
use App\Http\Controllers\ShortenLinkController;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Request;

class publishIGpostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:publish-i-gpost-command';

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
        $posts = PostCronController::get_instagram_posts(true);
        if (isset($posts['media']) && !empty($posts['media'])) {
            // set up the facebook object for current environment.
            $fb = new Facebook([
                'app_id' => 'your app id',
                'app_secret' => 'your app secret',
                'default_graph_version' => 'v16.0',
            ]);

            // set up the facebook app access token for current environment.
            $access_token = 'your access token';

            // get all pages that are accessed by this app.
            $tokens_response = $fb->get('/me/accounts?limit=1000&fields=name,instagram_business_account,access_token&access_token=' . $access_token);
            $ge = $tokens_response->getGraphEdge();
            $data_response_decoded = $ge->asArray(); // return array without data key.
            while ($fb->next($ge)) {
                $ge = $fb->next($ge);
                $data_response_decoded = array_merge($data_response_decoded, $ge->asArray());
            }

            // set up a mapping array between instagram (key) and facebook (value) pages ids.
            $pages_insta_to_face = array();
            foreach ($data_response_decoded as $value) {
                if (isset($value['instagram_business_account']['id'])) {
                    $pages_insta_to_face[$value['instagram_business_account']['id']] = $value;
                }
            }

            // loop through posts.
            foreach ($posts['media'] as $image_post) {

                $readable_result = '';
                $igUserId =  $image_post['page_id'];
                $message = $image_post['text'];
                $url = $image_post['url'];
                $post_id_from_database = $image_post['post_id'];

                // if access token is not set, try get it, and continue on fail.
                if (!isset($pages_insta_to_face[$igUserId]['access_token'])) {
                    $error = 'local access_token is not set.';

                    // update the post as published fail.
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_instagram_post($post_request, $post_id_from_database);
                    continue;
                }

                // set page access token
                $fb->setDefaultAccessToken($pages_insta_to_face[$igUserId]['access_token']);

                $body = [];
                // if the link is a google drive link, change it to a direct link, otherwise leave it as it is.
                preg_match_all("/https:\/\/drive.google.com\/file\/d\/(.*)\//", $url, $matches);
                if (isset($matches[1][0])) {
                    $id = $matches[1][0];
                    $url = "https://drive.google.com/uc?export=download&id=" . $id . "&t=" . strtotime('now');
                }
                /* start edit
                    Code added by worood to shorten insta link
                    */
                $post_request = new Request([
                    'link' => $url,

                ]);
                $shorten_link = ShortenLinkController::shorten_organic_instagram_link($post_request, true);
                $url = $shorten_link['data']['shorten_link'];



                /* end edit
                    Code added by worood to shorten insta link
                    */

                $body = array(
                    'image_url' =>   $url,
                    'caption' => $message
                );


                // try create the media.
                try {
                    // Returns a `FacebookFacebookResponse` object
                    $response = $fb->post(
                        '/' . $igUserId . '/media',
                        $body,
                        $access_token
                    );
                } catch (FacebookResponseException $e) {
                    if (isset($e->getResponseData()['error']['error_user_msg'])) {
                        $error = 'creating media, Graph returned an error: ' . $e->getResponseData()['error']['error_user_msg'];
                    } else {

                        $error = 'creating media, Graph returned an error: ' . $e->getMessage();
                    }
                    // update the post as published fail.
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_instagram_post($post_request, $post_id_from_database);
                    continue;
                } catch (FacebookSDKException $e) {
                    $error = 'creating media, Facebook SDK returned an error: ' . $e->getResponseData()['error_user_msg'];
                    // update the post as published fail.
                    $post_request = new HttpRequest([
                        'is_published' => 2,
                        'cron_error_message' => $error,
                    ]);
                    PostCronController::update_instagram_post($post_request, $post_id_from_database);
                    continue;
                }

                $graphNode = $response->getGraphNode();
                if (isset($graphNode) && $graphNode != '') {
                    $readable_result = $graphNode->asArray();
                    if (isset($readable_result['id']) && !empty($readable_result['id'])) {
                        echo 'the media ' . $readable_result['id'] . ' was created ';
                        echo '<br />';
                        $media_id = $readable_result['id'];
                        $status_code = '';
                        do {
                            // keep checking the upload status until finished or exception
                            try {
                                // Returns a `FacebookFacebookResponse` object
                                $response = $fb->get(
                                    $media_id . '?fields=status_code,status',
                                    $access_token
                                );
                            } catch (FacebookResponseException $e) {
                                if (isset($e->getResponseData()['error']['error_user_msg']))
                                    $error = 'fetching status, Graph returned an error: ' . $e->getResponseData()['error']['error_user_msg'];
                                else
                                    $error = 'fetching status, Graph returned an error: ' . $e->getMessage();
                                // update the post as published fail.
                                $post_request = new HttpRequest([
                                    'is_published' => 2,
                                    'cron_error_message' => $error,
                                ]);
                                PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                continue 2;
                            } catch (FacebookSDKException $e) {
                                $error = 'fetching status, Facebook SDK returned an error: ' . $e->getMessage();
                                // update the post as published fail.
                                $post_request = new HttpRequest([
                                    'is_published' => 2,
                                    'cron_error_message' => $error,
                                ]);
                                PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                continue 2;
                            }
                            $graphNode = $response->getGraphNode();
                            $publish_result = $graphNode->asArray();
                            $status_code = $publish_result['status_code'];

                            // wait 2 seconds for each loop execute.
                            sleep(2);
                            if ($status_code == 'ERROR') {
                                $error = 'STATUS: ERROR';
                                // update the post as published fail.
                                $post_request = new HttpRequest([
                                    'is_published' => 2,
                                    'cron_error_message' => $error,
                                ]);
                                PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                break;
                            }
                        } while ($status_code != 'FINISHED');

                        // if media uploaded successfully, return the media id and link it to the post.
                        if ($status_code == 'FINISHED') {
                            // create the container of the previous media.
                            try {
                                // Returns a `FacebookFacebookResponse` object
                                $response = $fb->post(
                                    '/' . $igUserId . '/media_publish',
                                    array(
                                        'creation_id' => $media_id
                                    ),
                                    $access_token
                                );
                            } catch (FacebookResponseException $e) {
                                if (isset($e->getResponseData()['error']['error_user_msg']))
                                    $error = 'publishing media, Graph returned an error: ' . $e->getResponseData()['error']['error_user_msg'];
                                else
                                    $error = 'publishing media, Graph returned an error: ' . $e->getMessage();
                                $post_request = new HttpRequest([
                                    'is_published' => 2,
                                    'cron_error_message' => $error,
                                ]);
                                PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                continue;
                            } catch (FacebookSDKException $e) {
                                $error = 'publishing media, Facebook SDK returned an error: ' . $e->getMessage();
                                $post_request = new HttpRequest([
                                    'is_published' => 2,
                                    'cron_error_message' => $error,
                                ]);
                                PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                continue;
                            }
                            $insert_container_result = $response->getGraphNode();

                            // retrieve the media container id.
                            if (isset($insert_container_result) && $insert_container_result != '') {
                                $readable_container_result = $insert_container_result->asArray();

                                // echo '<pre>'; print_r($readable_container_result); echo '</pre>';

                                if (isset($readable_container_result['id']) && !empty($readable_container_result['id'])) {
                                    // $update_post_status = \Model\scheduled_instagram_posts::update_post_after_publish($post_id_from_database,'the Post  '.$readable_container_result['id'].' was published ',1);
                                    $msg = 'the post ' . $readable_container_result['id'] . ' was created';
                                    echo $msg;
                                    echo '<br />';
                                    try {
                                        // Returns a `FacebookFacebookResponse` object
                                        $response = $fb->get(
                                            $readable_container_result['id'] . '?fields=permalink',
                                            $access_token
                                        );
                                    } catch (FacebookResponseException $e) {
                                        if (isset($e->getResponseData()['error']['error_user_msg']))
                                            $error = 'fetching permalink, Graph returned an error: ' . $e->getResponseData()['error']['error_user_msg'];
                                        else
                                            $error = 'fetching permalink, Graph returned an error: ' . $e->getMessage();
                                        $post_request = new HttpRequest([
                                            'is_published' => 2,
                                            'cron_error_message' => $error,
                                        ]);
                                        PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                        continue;
                                    } catch (FacebookSDKException $e) {
                                        $error = 'fetching permalink, Facebook SDK returned an error: ' . $e->getMessage();
                                        $post_request = new HttpRequest([
                                            'is_published' => 2,
                                            'cron_error_message' => $error,
                                        ]);
                                        PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                        continue;
                                    }
                                    $graphNode = $response->getGraphNode();
                                    $readable_get_permalink = $graphNode->asArray();
                                    echo ($readable_get_permalink['permalink']);
                                    echo '<br /><br />';
                                    $post_request = new HttpRequest([
                                        'is_published' => 1,
                                        'cron_error_message' => $msg,
                                        'cron_post_id' => $readable_container_result['id'],
                                        'cron_post_link' => $readable_get_permalink['permalink'],
                                    ]);
                                    PostCronController::update_instagram_post($post_request, $post_id_from_database);
                                }
                            }
                        }
                    } else {
                        echo '<pre>';
                        print_r('error creating media container');
                        echo '</pre>';
                    }
                }
            }
        } else {
            echo "There is no posts need to be published currently.";
        }
        return 0;
    }
}
