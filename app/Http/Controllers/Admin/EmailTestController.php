<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin-only tool to check the mail settings (MAIL_* in .env): sends one plain
 * test message right now and shows the real result or error. Nothing is queued
 * and no workflow record is involved.
 */
class EmailTestController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        return view('admin.email-test.index', ['settings' => $this->settings()]);
    }

    public function send(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $data = $request->validate([
            'to' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $started = microtime(true);
        $error = null;

        try {
            Mail::raw($data['body'], function ($message) use ($data) {
                $message->to($data['to'])->subject($data['subject']);
            });
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Email test failed: '.$error);
        }

        return back()->withInput()->with('email_test', [
            'ok' => $error === null,
            'to' => $data['to'],
            'mailer' => config('mail.default'),
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $error,
            'hint' => $error ? $this->hintFor($error) : null,
        ]);
    }

    /** The mail settings in effect right now (the password is never shown). */
    private function settings(): array
    {
        $default = config('mail.default');
        $smtp = config('mail.mailers.smtp', []);
        $username = $smtp['username'] ?? null;

        return [
            'Mailer' => $default,
            'Host' => $smtp['host'] ?? null,
            'Port' => $smtp['port'] ?? null,
            'Scheme' => ($smtp['scheme'] ?? null) ?: 'smtp (STARTTLS when offered)',
            'Username' => $username ? Str::mask($username, '*', 3) : null,
            'From address' => config('mail.from.address'),
            'From name' => config('mail.from.name'),
            'App URL (used in email links)' => config('app.url'),
            'Email notifications' => config('cams.email_notifications') ? 'On' : 'Off',
        ];
    }

    /** Plain-language next step for the most common failures. */
    private function hintFor(string $error): string
    {
        $e = strtolower($error);

        return match (true) {
            str_contains($e, 'connection could not be established'),
            str_contains($e, 'connection refused'),
            str_contains($e, 'timed out'),
            str_contains($e, 'getaddrinfo'),
            str_contains($e, 'unable to connect') => 'The server could not be reached. Check MAIL_HOST and MAIL_PORT, and that a firewall or antivirus is not blocking the port.',

            str_contains($e, 'failed to authenticate'),
            str_contains($e, 'authenticat'),
            str_contains($e, '535') => 'The server rejected the username or password. Check MAIL_USERNAME and MAIL_PASSWORD. Gmail and Microsoft 365 usually need an app password, not the normal account password.',

            str_contains($e, 'starttls'),
            str_contains($e, 'tls'),
            str_contains($e, 'ssl'),
            str_contains($e, 'certificate') => 'The encryption does not match the port. Port 587 normally uses MAIL_SCHEME=smtp (STARTTLS); port 465 uses MAIL_SCHEME=smtps.',

            str_contains($e, 'relay'),
            str_contains($e, 'not permitted'),
            str_contains($e, 'sender address rejected') => 'The server refused to send for this sender. Make sure MAIL_FROM_ADDRESS is an address the account is allowed to send as.',

            str_contains($e, 'does not comply with rfc') => 'One of the email addresses is not valid.',

            default => 'See storage/logs/laravel.log for the full error. If you just edited .env, run php artisan config:clear and try again.',
        };
    }
}
