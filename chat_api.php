<?php
// chat_api.php — ServiceConnect AI backend (FINAL)

header('Content-Type: application/json; charset=utf-8');

// Allow fetch from same domain (or adjust if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(["ok" => true]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$userMessage = trim($data['message'] ?? '');
$history     = $data['history'] ?? []; // optional array of past {role,content}

if ($userMessage === '') {
    echo json_encode(["reply" => "Please type a message."]);
    exit;
}

// -----------------------------
// 1) Local fallback AI (same as frontend, but server-safe)
// -----------------------------
function localAI($text) {
    $t = strtolower($text);

    if (strpos($t, "book") !== false || strpos($t, "schedule") !== false) {
        return "To book a service:\n"
            . "1) Go to Find Service\n"
            . "2) Search a service\n"
            . "3) Choose date (from tomorrow)\n"
            . "4) Click View Schedule / Book and select a slot.";
    }

    if (strpos($t, "cancel") !== false || strpos($t, "decline") !== false) {
        return "You can cancel before technician accepts:\n"
            . "1) Go to My Orders\n"
            . "2) Open the order\n"
            . "3) Press Cancel (if available).";
    }

    if (strpos($t, "wallet") !== false || strpos($t, "payment") !== false || strpos($t, "recharge") !== false) {
        return "For wallet/payment:\n"
            . "• Check your balance on dashboard\n"
            . "• Add money from Wallet page\n"
            . "• After service, payment is deducted automatically.";
    }

    if (strpos($t, "rating") !== false || strpos($t, "review") !== false) {
        return "After a job is completed:\n"
            . "1) Open My Orders\n"
            . "2) Click completed order\n"
            . "3) Submit rating + review.";
    }

    if (strpos($t, "technician") !== false || strpos($t, "provider") !== false) {
        return "Technicians are matched based on rating, experience & availability.\n"
            . "You can filter by budget and sort in Find Service.";
    }

    return "I can help with booking, orders, ratings, and wallet.\n"
        . "Try asking a short question like “How to book?”";
}

// -----------------------------
// 2) If no API key => fallback
// -----------------------------
$apiKey = getenv("OPENAI_API_KEY");
if (!$apiKey) {
    echo json_encode(["reply" => localAI($userMessage)]);
    exit;
}

// -----------------------------
// 3) Build messages for OpenAI
// -----------------------------
$messages = [];

$messages[] = [
    "role" => "system",
    "content" =>
        "You are ServiceConnect AI assistant. Help customers with:\n"
        . "- booking services\n"
        . "- choosing technicians\n"
        . "- wallet/payment issues\n"
        . "- order status/cancel rules\n"
        . "- ratings/reviews\n"
        . "Be short, friendly, and specific to ServiceConnect."
];

// Include history if passed from frontend (safe filter)
if (is_array($history)) {
    foreach ($history as $h) {
        if (!isset($h["role"], $h["content"])) continue;
        $role = $h["role"];
        if (!in_array($role, ["user", "assistant", "system"], true)) continue;
        $messages[] = [
            "role" => $role,
            "content" => (string)$h["content"]
        ];
    }
}

$messages[] = [
    "role" => "user",
    "content" => $userMessage
];

// -----------------------------
// 4) Call OpenAI Chat Completions
// -----------------------------
$model = getenv("OPENAI_MODEL") ?: "gpt-4o-mini"; // change anytime

$payload = [
    "model" => $model,
    "messages" => $messages,
    "temperature" => 0.6,
    "max_completion_tokens" => 300
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code >= 400) {
    // If OpenAI fails, fallback safely
    echo json_encode([
        "reply" => localAI($userMessage),
        "debug" => $err ? $err : "OpenAI error HTTP $code"
    ]);
    exit;
}

$out = json_decode($response, true);

$reply = $out["choices"][0]["message"]["content"] ?? null;
if (!$reply) {
    echo json_encode(["reply" => localAI($userMessage)]);
    exit;
}

// Final
echo json_encode(["reply" => trim($reply)]);
