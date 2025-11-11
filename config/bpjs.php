<?php

return [
    // Kredensial BPJS dibaca dari environment variables
    'consid' => env('BPJS_CONSID'),
    'secretkey' => env('BPJS_SECRETKEY'),
    'user_key' => env('BPJS_USER_KEY'),
];