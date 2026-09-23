<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MasterController;
/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Текущий мастер приходит в заголовке X-Master-Id и уже разложен
| в атрибуты запроса middleware'ом ResolveCurrentMaster:
|
|     $master = $request->attributes->get('current_master');
|
| Здесь нужно написать три роута — см. README.md.
|
*/

Route::get('/ping', fn () => ['ok' => 'aaaaaaaaaaaaaaaaaaaaaaaa']);

Route::post('/referrals/attach', [MasterController::class, 'attach']);
Route::get('/referrals/my', [MasterController::class, 'myReferral']);
Route::get('/referrals/earnings', [MasterController::class, 'earnings']);
