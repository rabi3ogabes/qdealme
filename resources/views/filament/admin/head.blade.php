@php($stylesheet = public_path('css/qdealme-admin.css'))
<link rel="stylesheet" href="{{ asset('css/qdealme-admin.css') }}?v={{ is_file($stylesheet) ? filemtime($stylesheet) : '1' }}">
<meta name="theme-color" content="#2e0e1b">
