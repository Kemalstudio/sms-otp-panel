<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Absolute (or base_path relative) path to the service account JSON issued
    | by the Firebase console. Nothing is read from it until the first message
    | is actually pushed, so the app boots fine without it — the OTP job will
    | simply fail the delivery and mark the log as failed.
    |
    */

    'credentials' => env('FIREBASE_CREDENTIALS_PATH'),

    'project_id' => env('FIREBASE_PROJECT_ID'),

];
