<?php

use Illuminate\Support\Facades\Route;

Route::get('/match-test', function () {
    return view('match-test');
});


// https://api.cricapi.com/v1/cricScore?apikey=475271a3-7ff9-4d1f-96e0-103303f8312f

//     {
//       "id": "41647a7d-2dce-4a26-8192-52e54ef65161",
//       "dateTimeGMT": "2026-03-17T14:00:00",
//       "matchType": "t20",
//       "status": "Royal Riders Punjab need 127 runs in 79 balls",
//       "ms": "live",
//       "t1": "Royal Riders Punjab",
//       "t2": "Southern Super Stars [SSS]",
//       "t1s": "53/1 (6.5)",
//       "t2s": "179/7 (20)",
//       "series": "Legends League Cricket 2026"
//     },

