<?php

$secret="mysecretkey";

$input=[
    'name' => 'John Doe',
    'email' => 'mail@foo',
    'startdate' => '2024-07-01'
];

echo sprintf("Input: %s\n", json_encode($input, JSON_PRETTY_PRINT));


$data = serialize($input);
echo sprintf("Data: %s\n", $data);
$signature = hash_hmac('sha256', $data, $secret);
$token = base64_encode($data . ':' . $signature);

echo $token;