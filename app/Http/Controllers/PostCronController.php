<?php

namespace App\Http\Controllers;

use App\Models\planfacebookposts;
use App\Models\planinstagramposts;
use App\Notifications\failedPostNotification;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;


class PostCronController extends Controller
{
    public static function get_facebook_posts($in_laravel = false)
    {
        $data = planfacebookposts::query()
            ->whereIn('is_published', [0, 2])
            ->where('post_date', '>', date('Y-m-d H:i:s', strtotime('yesterday')))
            ->where('post_date', '<', date('Y-m-d H:i:s', strtotime('tomorrow')))
            ->get();

        $posts = ['images' => [], 'links' => []];
        foreach ($data as $p) {

            $i = array();
            $i['post_id'] = $p->id;
            $i['text'] = $p->text;
            $i['post_date'] = $p->post_date;
            $i['url'] = $p->image_link;
            $i['page_id'] = $p->facebook_page->id_from_facebook;

            $date = new DateTime($i['post_date'], new DateTimeZone($i['timezone']));
            $timestamp = $date->format('U');
            $now_plus_thirty_minutes = new DateTime('now + 30 minutes', new DateTimeZone($i['timezone']));
            $now_plus_thirty_minutes_timestamp = $now_plus_thirty_minutes->format('U');
            $i['diff'] =  $now_plus_thirty_minutes_timestamp - $timestamp;

            // if the post is in the past, ignore it.
            // twenty minutes needed to schedule a post on Facebook + 10 minutes to add more margin.
            // 10800 seconds = 3 hours, so if the post is farther than 3 hours in the future ignore it.
            if ($i['diff'] > 0 || $i['diff'] < -10800)
                continue;

            $posts['images'][] = $i;

            $posts['images'] = array_slice($posts['images'], 0, 5);
            $posts['links'] = array_slice($posts['links'], 0, 5);

            if ($in_laravel) {
                return $posts;
            }
            return response($posts);
        }
    }

    public static function get_instagram_posts($in_laravel = false)
    {
        $data = planinstagramposts::query()
            ->whereIn('is_published', [0, 2])
            ->where('post_date', '>', date('Y-m-d H:i:s', strtotime('yesterday')))
            ->where('post_date', '<', date('Y-m-d H:i:s', strtotime('tomorrow')))
            ->get();

        $posts = ['images' => [], 'links' => []];
        foreach ($data as $p) {

            $i = array();
            $i['post_id'] = $p->id;
            $i['text'] = $p->text;
            $i['post_date'] = $p->post_date;
            $i['url'] = $p->image_link;
            $i['page_id'] = $p->instagram_page->id_from_instagram;

            $date = new DateTime($i['post_date'], new DateTimeZone($i['timezone']));
            $timestamp = $date->format('U');
            $now_plus_thirty_minutes = new DateTime('now + 30 minutes', new DateTimeZone($i['timezone']));
            $now_plus_thirty_minutes_timestamp = $now_plus_thirty_minutes->format('U');
            $i['diff'] =  $now_plus_thirty_minutes_timestamp - $timestamp;

            // if the post is in the past, ignore it.
            // twenty minutes needed to schedule a post on Facebook + 10 minutes to add more margin.
            // 10800 seconds = 3 hours, so if the post is farther than 3 hours in the future ignore it.
            if ($i['diff'] > 0 || $i['diff'] < -10800)
                continue;

            $posts['images'][] = $i;

            $posts['images'] = array_slice($posts['images'], 0, 5);
            $posts['links'] = array_slice($posts['links'], 0, 5);

            if ($in_laravel) {
                return $posts;
            }
            return response($posts);
        }
    }

    public static function update_facebook_post(Request $request, $id)
    {
        $fb_post = PlanFacebookPosts::find($id);
        if (!$fb_post) {
            return 'fb post with id (' . $fb_post . ') not found.';
        }
        $accept = [
            'is_published',
            'cron_error_message',
            'cron_post_id',
            'cron_post_link',
        ];

        $data = $request->only($accept);
        if ($fb_post->is_published == 0 && isset($data['cron_error_message']) && isset($data['is_published']) && $data['is_published'] == 2) {
            $fb_name = $fb_post->facebook_page->page_name;
            $text = $fb_post->text;
            $post_url = $fb_post->image_link;
            $message = "There is a FB post that failed to be published, details:\n" .
                "*FB Page Name:* " . $fb_name . " . \n" .
                "*Post Text:* " . $text . " . \n" .
                "*Post Date & Time:* " . $fb_post->post_datetime . " . \n" .
                "*Post Link:* " . $post_url . " . \n" .
                "*Post Error:* " . $data['cron_error_message'];
            Notification::send(Auth::user(), new failedPostNotification($message));
        }

        if ($fb_post->is_published == 2 && isset($data['cron_post_link']) && isset($data['is_published']) && $data['is_published'] == 1) {
            $fb_name = $fb_post->facebook_page->page_name;
            $text = $fb_post->text->text;
            $post_url = $fb_post->image_link;
            $message = "There is an *early failed* FB post that succeeded to be published, details:\n" .
                "*FB Page Name:* " . $fb_name . " . \n" .
                "*Post Text:* " . $text . " . \n" .
                "*Post Date & Time:* " . $fb_post->post_datetime . " . \n" .
                "*Post Link:* " . $post_url . " . \n" .
                "*Post Published Link:* " . $data['cron_post_link'];
        }

        $fb_post->update($data);

        return response(['message' => 'success', 'data' => $fb_post]);
    }

    public static function update_instagram_post(Request $request, $id)
    {
        $ig_post = planinstagramposts::find($id);
        if (!$ig_post) {
            return 'fb post with id (' . $ig_post . ') not found.';
        }
        $accept = [
            'is_published',
            'cron_error_message',
            'cron_post_id',
            'cron_post_link',
        ];

        $data = $request->only($accept);
        if ($ig_post->is_published == 0 && isset($data['cron_error_message']) && isset($data['is_published']) && $data['is_published'] == 2) {
            $ig_name = $ig_post->instagram_page->page_name;
            $text = $ig_post->text;
            $post_url = $ig_post->image_link;
            $message = "There is a IG post that failed to be published, details:\n" .
                "*IG Page Name:* " . $ig_name . " . \n" .
                "*Post Text:* " . $text . " . \n" .
                "*Post Date & Time:* " . $ig_post->post_datetime . " . \n" .
                "*Post Link:* " . $post_url . " . \n" .
                "*Post Error:* " . $data['cron_error_message'];
            Notification::send(Auth::user(), new failedPostNotification($message));
        }

        if ($ig_post->is_published == 2 && isset($data['cron_post_link']) && isset($data['is_published']) && $data['is_published'] == 1) {
            $ig_name = $ig_post->instagram_page->page_name;
            $text = $ig_post->text->text;
            $post_url = $ig_post->image_link;
            $message = "There is an *early failed* IG post that succeeded to be published, details:\n" .
                "*IG Page Name:* " . $ig_name . " . \n" .
                "*Post Text:* " . $text . " . \n" .
                "*Post Date & Time:* " . $ig_post->post_datetime . " . \n" .
                "*Post Link:* " . $post_url . " . \n" .
                "*Post Published Link:* " . $data['cron_post_link'];
        }

        $ig_post->update($data);

        return response(['message' => 'success', 'data' => $ig_post]);
    }
}
