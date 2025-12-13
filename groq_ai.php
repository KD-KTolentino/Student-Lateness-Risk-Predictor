<?php
// groq_ai.php
// Helper function to call Groq AI API for lateness advice
function get_groq_advice($apiKey, $prompt) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $data = [
        'model' => 'llama-3.3-70b-versatile', 
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert student punctuality advisor. Give concise, actionable, and positive advice based on the user\'s routine.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 512, 
        'temperature' => 0.7
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    file_put_contents(__DIR__.'/groq_debug.log', $result);
    if (curl_errno($ch)) {
        curl_close($ch);
        return 'AI service error: ' . curl_error($ch);
    }
    curl_close($ch);
    $json = json_decode($result, true);
    if (isset($json['choices'][0]['message']['content'])) {
        return trim($json['choices'][0]['message']['content']);
    }
    return 'No AI advice available.';
}
