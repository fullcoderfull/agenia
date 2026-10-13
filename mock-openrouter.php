<?php


header('Connection: keep-alive');
header('X-Accel-Buffering: no');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$text = "Bonjour je suis un mock OpenRouter en mode production.";

$len = strlen($text);
$i = 0;

while ($i < $len) {

    // simulate network chunking (pas caractère fiable)
    $chunkSize = rand(1, 3);
    $chunk = substr($text, $i, $chunkSize);

    $payload = [
        "id" => "mock-" . uniqid(),
        "object" => "chat.completion.chunk",
        "created" => time(),
        "model" => "mock/openrouter",
        "choices" => [
            [
                "index" => 0,
                "delta" => [
                    "content" => $chunk
                ],
                "finish_reason" => null
            ]
        ]
    ];

    echo "data: " . json_encode($payload) . "\n\n";
    flush();

    usleep(rand(20000, 120000)); // jitter réseau réel

    $i += $chunkSize;
}

// fin stream
echo "data: [DONE]\n\n";
flush();