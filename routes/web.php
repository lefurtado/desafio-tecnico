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
    $base = realpath(storage_path('app/exports'));
    $full = realpath(storage_path('app/' . $path));

    abort_if(!$full || !$base || !str_starts_with($full, $base . DIRECTORY_SEPARATOR), 404);
    abort_unless(is_file($full), 404);

    return response()->download($full, 'alunos.xlsx');
})->name('export.download')->middleware('auth');