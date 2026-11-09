<?php

use App\Qcm\Livewire\TakeExam;
use Illuminate\Support\Facades\Route;

// MVP : l'application s'ouvre sur le module QCM (PRD QCM §1.3).
Route::redirect('/', '/qcm')->name('home');

// Passage d'un examen, hors du panel : le candidat ne voit que l'examen
// (PRD QCM UC-12, plan QCM §6.2).
Route::livewire('/examens/{exam}', TakeExam::class)->name('qcm.examens.passer');
Route::livewire('/examens/{exam}/apercu', TakeExam::class)->name('qcm.examens.apercu');
