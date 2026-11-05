@props(['href', 'preview', 'name', 'mime' => null])
{{-- Opens the attachment viewer modal on a plain click; with JS off (or ctrl/middle-click) it simply downloads. --}}
<a href="{{ $href }}"
   data-attachment-preview="{{ $preview }}"
   data-attachment-download="{{ $href }}"
   data-attachment-name="{{ $name }}"
   data-attachment-mime="{{ $mime }}"
   title="Click to preview"
   {{ $attributes }}>{{ $name }}</a>
