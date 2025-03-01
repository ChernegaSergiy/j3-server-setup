<?php

const TELEGRAM_API_TOKEN = 'Ваш_Telegram_API_Token';
const CHAT_ID = 'Ваш_Chat_ID';

function readBatteryInfo($param)
{
    $path = "/sys/class/power_supply/battery/{$param}";

    return is_readable($path) ? trim(@file_get_contents($path)) : null;
}

function getBatteryStatus()
{
    $batteryInfo = [
        'percentage' => readBatteryInfo('capacity'),
        'status' => readBatteryInfo('status'),
        'temperature' => readBatteryInfo('temp'),
        'plugged' => readBatteryInfo('charge_type'),
        'health' => readBatteryInfo('health'),
        'current' => readBatteryInfo('current_now'),
    ];

    if (in_array(null, $batteryInfo, true)) {
        return null;
    }

    $batteryInfo['temperature'] = (float) $batteryInfo['temperature'] / 10;

    return $batteryInfo;
}

function formatBatteryMessage($battery)
{
    $pluggedMap = [
        'AC' => 'Підключено до зарядного пристрою',
        'USB' => 'Підключено до USB',
        'Wireless' => 'Бездротова зарядка',
        'Fast' => 'Швидка зарядка',
        'N/A' => 'Не підключено',
    ];
    $statusMap = [
        'Charging' => 'Заряджається',
        'Discharging' => 'Розряджається',
        'Full' => 'Заряд повний',
        'Not charging' => 'Не заряджається',
    ];
    $healthMap = [
        'Good' => 'Хороший стан',
        'Overheat' => 'Перегрів',
        'Dead' => 'Вмерла батарея',
        'Unspecified' => 'Не визначено',
    ];

    return "🔋 Статус батареї:\n" .
        "• Рівень заряду: {$battery['percentage']}%\n" .
        "• Стан зарядки: " . ($pluggedMap[$battery['plugged']] ?? "Невідомо") . "\n" .
        "• Статус: " . ($statusMap[$battery['status']] ?? "Невідомо") . "\n" .
        "• Температура: " . round($battery['temperature'], 1) . "°C\n" .
        "• Здоров'я: " . ($healthMap[$battery['health']] ?? "Невідомо") . "\n" .
        "• Струм: {$battery['current']} µA\n";
}

function sendTelegramRequest($method, $data)
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_API_TOKEN . "/$method";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
    ]);
    $response = curl_exec($ch);
    if (false === $response) {
        echo 'cURL Error: ' . curl_error($ch) . PHP_EOL;
    }
    curl_close($ch);

    return json_decode($response, true);
}

function sendToTelegram($message)
{
    $data = [
        'chat_id' => CHAT_ID,
        'text' => $message,
        'reply_markup' => json_encode([
            'inline_keyboard' => [[['text' => '🔄 Оновити дані', 'callback_data' => 'refresh_battery']]],
        ]),
    ];
    sendTelegramRequest('sendMessage', $data);
}

function getUpdates($offset)
{
    return sendTelegramRequest('getUpdates', ['offset' => $offset, 'timeout' => 10]);
}

function answerCallbackQuery($callbackQueryId, $text)
{
    sendTelegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
    ]);
}

// Надсилаємо стартове повідомлення
if ($battery = getBatteryStatus()) {
    sendToTelegram(formatBatteryMessage($battery));
}

$lastUpdateId = 0;
while (true) {
    $updates = getUpdates($lastUpdateId + 1);
    if ($updates && isset($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $lastUpdateId = $update['update_id'];
            if (! empty($update['callback_query']) && 'refresh_battery' === $update['callback_query']['data']) {
                if ($battery = getBatteryStatus()) {
                    sendToTelegram(formatBatteryMessage($battery));
                    answerCallbackQuery($update['callback_query']['id'], 'Дані оновлено!');
                }
            }
        }
    }

    // Щогодинне оновлення
    if ('00' === date('i')) {
        if ($battery = getBatteryStatus()) {
            sendToTelegram(formatBatteryMessage($battery));
        }
        sleep(60);
    }
    sleep(1);
}
