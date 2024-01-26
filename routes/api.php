<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacebookPageController;
use App\Http\Controllers\InstagramPageController;
use App\Http\Controllers\PlanFacebookPostsController;
use App\Http\Controllers\PlanInstagramPostsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/user-profile', [AuthController::class, 'userProfile']);
});

Route::resource('facebook', FacebookPageController::class);
Route::resource('instagram', InstagramPageController::class);

Route::resource('plan_facebook_post', PlanFacebookPostsController::class)->only(['index', 'store', 'destroy']);
Route::resource('plan_instagram_post', PlanInstagramPostsController::class)->only(['index', 'store', 'destroy']);
