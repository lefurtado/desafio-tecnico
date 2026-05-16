<?php

use App\Jobs\ExportStudentsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/export/status', function () {
    return response()->json([
        'locked' => Cache::has(ExportStudentsJob::lockKey(auth()->id())),
    ]);
})->middleware('auth');

Route::get('/export/download', function (Request $request) {
    abort_unless($request->hasValidSignature(), 401);

    $path = (string) $request->query('path');

    abort_if(str_contains($path, '..') || !str_starts_with($path, 'exports/'), 404);

    $fullPath = storage_path("app/{$path}");

    abort_unless(is_file($fullPath), 404);

    return response()->download($fullPath, 'alunos.xlsx');
})->name('export.download')->middleware('auth');