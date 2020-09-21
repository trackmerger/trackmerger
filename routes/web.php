<?php

use App\Http\Controllers\MainContoller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [MainContoller::class, 'showForm'])->name('start');
Route::post('/check', [MainContoller::class, 'check'])->name('check');
Route::post('/output', [MainContoller::class, 'output'])->name('output');
