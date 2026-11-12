<?php

return [

    /*
    | Philippine VAT (NIRC Sec. 106/108) — standard rate on VAT-registered
    | suppliers' sales. Receipts show VAT-INCLUSIVE prices, so input tax is
    | extracted from the gross: gross x rate / (100 + rate). Override only if
    | the law changes.
    */
    'vat_rate' => (float) env('CAMS_VAT_RATE', 12.0),

    /*
    | Email copy of every in-app notification (the bell). Master switch.
    | MAIL_* in .env decides where it goes; with MAIL_MAILER=log nothing is
    | sent, it is only written to storage/logs/laravel.log.
    */
    'email_notifications' => (bool) env('CAMS_EMAIL_NOTIFICATIONS', true),

    /*
    | false (default): the email is sent right after the page response, so the
    | user is not kept waiting and no queue worker is needed.
    | true: the email goes on the queue (QUEUE_CONNECTION) and needs
    | `php artisan queue:work` running; failed sends are retried 3 times.
    */
    'email_use_queue' => (bool) env('CAMS_EMAIL_USE_QUEUE', false),

];
