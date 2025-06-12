<?php
// Add these routes to your Laravel routes/web.php

Route::get('/queue-monitor', [App\Http\Controllers\QueueMonitorController::class, 'index']);
Route::get('/queue-monitor/status', [App\Http\Controllers\QueueMonitorController::class, 'status']);
Route::get('/queue-monitor/periodic-check', [App\Http\Controllers\QueueMonitorController::class, 'periodicCheck']);