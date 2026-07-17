<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'OopSeller API', 'documentation' => '/api/v1/health']));
