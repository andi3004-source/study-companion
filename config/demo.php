<?php

/**
 * Demo account for reviewers running the app locally (Database\Seeders\DemoUserSeeder
 * and the "Akun demo" box on the login page). Never enabled in production.
 * The password is only ever read from .env — there is no default.
 */
return [
    'enabled' => (bool) env('DEMO_ACCOUNT_ENABLED', false),

    'email' => env('DEMO_ACCOUNT_EMAIL', 'demo@studycompanion.test'),

    'password' => env('DEMO_ACCOUNT_PASSWORD'),
];
