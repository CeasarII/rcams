<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'cams:mail-test {email : Address to send the test message to}';

    protected $description = 'Send a test email to check the MAIL_* settings in .env';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $this->line("Mailer: {$mailer}  |  From: ".config('mail.from.address'));

        try {
            Mail::raw('This is a test message from '.config('app.name').'. If you can read it, email is working.', function ($message) {
                $message->to($this->argument('email'))->subject('['.config('app.name').'] Test email');
            });
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'MAIL_MAILER is "log", so nothing was sent: the message was written to storage/logs/laravel.log. Set MAIL_MAILER=smtp to send for real.'
            : 'Sent. Check the inbox (and spam) of '.$this->argument('email').'.');

        return self::SUCCESS;
    }
}
