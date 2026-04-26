<?php
header('Content-Type: text/plain; charset=utf-8');

$url = 'https://api.openai.com/v1/models';

$options = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer IDE_A_KULCSOD\r\n"
    ]
];

$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

if ($result === false) {
    echo "HIBA";
} else {
    echo "OK";
}