<?php

namespace App\Http\Controllers;

use App\Models\facebookPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacebookPageController extends Controller
{
    public function index(): JsonResponse
    {
        $facebook_pages = facebookPage::paginate(20);
        if ($facebook_pages) {
            return response()->json($facebook_pages, 200);
        } else {
            return response()->json('no facebook pages');
        }
    }

    public function show($id): JsonResponse
    {
        $facebook_page = facebookPage::findOrFail($id);
        return response()->json($facebook_page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'page_name' => 'required|unique:facebook_pages,page_name',
            'id_from_facebook' => 'required|unique:facebook_pages,id_from_facebook'
        ]);

        $facebook_page = facebookPage::create($request->all());

        return response()->json('facebook page is added');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $facebook_page = facebookPage::findOrFail($id);
        $this->validate($request, [
            'page_name' => 'required',
            'id_from_facebook' => 'required'
        ]);
        $facebook_page->page_name = $request->page_name;
        $facebook_page->id_from_facebook = $request->id_from_facebook;
        $facebook_page->update();
        return response()->json('facebook page updated');
    }

    public function destroy($id): JsonResponse
    {
        $facebook_page = facebookPage::findOrFail($id);
        $facebook_page->delete();
        return response()->json('facebook page deleted');
    }
}
