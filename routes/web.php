<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/media/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);
    abort_unless(file_exists($fullPath), 404);
    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
    ]);
})->where('path', '.*');
