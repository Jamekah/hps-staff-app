<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin Seeding
    |--------------------------------------------------------------------------
    |
    | Read by Database\Seeders\SuperAdminSeeder. Set these in .env — never
    | commit real credentials. Accessed via config() so seeding also works
    | when the configuration is cached (e.g. on Laravel Cloud).
    |
    */

    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
    'email' => env('SUPER_ADMIN_EMAIL'),
    'password' => env('SUPER_ADMIN_PASSWORD'),

];
