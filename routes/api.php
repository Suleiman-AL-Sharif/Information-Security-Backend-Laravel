<?php
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('login',[UserController::class,'login']);
Route::post('register',[UserController::class,'register']);

Route::group(['middleware'=>['auth:api']],function(){
    Route::post('logout',[UserController::class,'logout']);
    Route::post('info',[UserController::class,'info']);
    Route::post('showInfo',[UserController::class,'showInfo']);
    Route::post('generateKeyPair',[UserController::class,'generateKeyPair']);
    Route::post('data',[UserController::class,'data']);
    Route::get('showData',[UserController::class,'showData']);
});


Route::group(['middleware'=>['auth:api','checkCode']],function(){

    Route::get('equation',[UserController::class,'equation']);
    Route::post('doctorKeys',[UserController::class,'doctorKeys']);

});
