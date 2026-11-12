<x-mail::message>
# {{ $title }}

{{ $body }}

<x-mail::button :url="$url">
Open {{ strtolower($type) }}
</x-mail::button>

You are receiving this because the {{ strtolower($type) }} needs your attention or has an update.
You will be asked to sign in first if you are not already.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
