<?php

return [
    'name' => 'EduRiser PMS',
    'base_url' => '/pms',
    'timezone' => 'Asia/Kolkata',

    // Shared secret the Central Management System's SSO form must also POST
    // (as `sso_token`) alongside `email`, so this endpoint can't be driven
    // by anyone who merely knows a user's email address. Must match exactly
    // on both sides. Rotate by changing it here and in the CMS form.
    'sso_secret' => '1cb629a0e8f57a110fb0e6a1646f5bd82ef166a76d05268be031ee110205f046',
];
