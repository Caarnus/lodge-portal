<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $version->title }}</title>
    <style>body {
            font-family: serif;
            max-width: 7in;
            margin: auto
        }

        @media print {
            body {
                max-width: none
            }
        }</style>
</head>
<body><h1>{{ $version->title }}</h1>
<p>{{ $version->publication_date }}</p>{!! $version->body_html !!}</body>
</html>
