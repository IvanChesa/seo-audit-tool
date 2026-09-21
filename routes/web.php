<?php

use Illuminate\Support\Facades\Route;

// The React app has its own router (home, report, history and its 404 page),
// so every path outside the API and the health check returns the same shell.
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api(?:/|$)|up$).*')
    ->name('spa');
