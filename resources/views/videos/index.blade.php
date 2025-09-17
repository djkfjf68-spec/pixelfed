@extends('layouts.app')

@section('content')

<noscript>
	<div class="container">
		<p class="pt-5 text-center lead">Please enable javascript to view this content.</p>
	</div>
</noscript>

<short-videos :initial-videos="[]"></short-videos>

@endsection

@push('scripts')
<script type="text/javascript" src="{{ mix('js/videos.js') }}"></script>
<script type="text/javascript">window.App.boot()</script>
@endpush

@push('styles')
<style>
body {
	overflow: hidden;
}
.navbar, .footer {
	display: none !important;
}
</style>
@endpush