@extends('layouts.app')

@section('title', 'Email Test')

@section('content')
    @php($result = session('email_test'))

    <div class="page-header">
        <div>
            <h1 class="page-title mb-0">Email Test</h1>
            <div class="text-muted small">Send one test message to check the mail (SMTP) settings.</div>
        </div>
    </div>

    @if ($result)
        @if ($result['ok'])
            <div class="alert alert-success">
                <div class="fw-semibold">
                    {{ $result['mailer'] === 'log' ? 'Accepted, but not actually sent.' : 'Message sent to '.$result['to'].'.' }}
                </div>
                <div class="small">
                    @if ($result['mailer'] === 'log')
                        MAIL_MAILER is <code>log</code>, so the message was only written to <code>storage/logs/laravel.log</code>.
                        Set MAIL_MAILER=smtp (and the host, port, username and password) to send for real.
                    @else
                        The mail server accepted it in {{ number_format($result['ms']) }} ms. Check the inbox of {{ $result['to'] }} (and the spam folder).
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-danger">
                <div class="fw-semibold">Sending failed ({{ number_format($result['ms']) }} ms)</div>
                @if ($result['hint'])
                    <div class="small mb-2">{{ $result['hint'] }}</div>
                @endif
                <div class="small text-break"><span class="text-muted">Error:</span> <code>{{ $result['error'] }}</code></div>
            </div>
        @endif
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Test message</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.email-test.send') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="to" class="form-label">Send to</label>
                            <input type="email" id="to" name="to" required maxlength="190"
                                   class="form-control @error('to') is-invalid @enderror"
                                   value="{{ old('to', 'cbucalan@gmail.com') }}">
                            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" id="subject" name="subject" required maxlength="150"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   value="{{ old('subject', 'CAMS test email') }}">
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="body" class="form-label">Message</label>
                            <textarea id="body" name="body" rows="5" required maxlength="2000"
                                      class="form-control @error('body') is-invalid @enderror">{{ old('body', 'This is a test email from '.config('app.name').', sent on '.now()->timezone('Asia/Manila')->format('M d, Y g:i A').'. If you can read it, the email settings are working.') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Send test email</button>
                        <div class="form-text mt-2">This sends right away (it does not use the queue), so the page waits for the mail server&rsquo;s answer.</div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Current mail settings</div>
                <div class="card-body">
                    @if ($settings['Mailer'] === 'log')
                        <div class="alert alert-warning py-2 small">
                            Mailer is <code>log</code>: emails are written to the log, not sent.
                        </div>
                    @endif

                    <dl class="row mb-0 small">
                        @foreach ($settings as $label => $value)
                            <dt class="col-sm-5 text-muted fw-normal">{{ $label }}</dt>
                            <dd class="col-sm-7 text-break">{{ filled($value) ? $value : '—' }}</dd>
                        @endforeach
                    </dl>

                    <div class="text-muted small mt-3">
                        Changed <code>.env</code>? Run <code>php artisan config:clear</code> so the new values are used.
                        The password is never shown.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
