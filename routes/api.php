<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login','Auth\LoginController@login');

Route::post('register','Auth\RegisterController@register');

Route::group(['middleware'=>'auth:api'], function(){

    Route::get('list_files','S3Controller@listFiles');

    Route::post('download_files','S3Controller@download');

    Route::post('download_decrypt_files','S3Controller@download_decrypt');

    Route::post('edit_files','S3Controller@edit');

    Route::post('remove_files','S3Controller@remove');

    Route::post('search_files','S3Controller@search');

    Route::post('upload_files','S3Controller@upload');
});