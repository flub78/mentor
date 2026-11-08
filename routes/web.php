<?php

use Illuminate\Support\Facades\Route;

// MVP : l'application s'ouvre sur le module QCM (PRD QCM §1.3).
Route::redirect('/', '/qcm')->name('home');
