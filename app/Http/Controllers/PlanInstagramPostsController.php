<?php

namespace App\Http\Controllers;

use App\Models\planinstagramposts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanInstagramPostsController extends Controller
{
    public function index(): JsonResponse
    {
        $instagram_posts = planinstagramposts::paginate(20);
        if ($instagram_posts) {
            return response()->json($instagram_posts);
        } else return response()->json('no instagram posts');
    }

    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'post_date' => 'required',
            'text' => 'required',
            'post_id_from_instagram' => 'required',
            'image_link' => 'required',
            'post_link' => 'required'
        ]);

        planinstagramposts::create($request->all());
        return response()->json('instagram post created');
    }

    public function destroy($id): JsonResponse
    {
        $instagram_post = planinstagramposts::find($id);
        if ($instagram_post) {
            $instagram_post->delete();
            return response()->json('instagram post is deleted');
        } else {
            return response()->json('instagram post not found');
        }
    }
}
