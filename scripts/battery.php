<?php

/**
 * Battery monitoring script for j3-server-setup
 *
 * This script monitors the battery level and status on an Android device.
 * It performs actions based on the battery level and sends battery information to Telegram.
 *
 * Usage: php battery.php
 */
const TELEGRAM_API_TOKEN = 'YOUR_TELEGRAM_API_TOKEN';
const CHAT_ID = 'YOUR_CHAT_ID';
const LOG_FILE = __DIR__ . '/path/to/logfile.log';
const TELEGRAM_SEND_MINUTE = 0;
const HOURLY_CHECK_INTERVAL = 60;
const MAX_RETRY_ATTEMPTS = 3;
const RETRY_DELAY = 5;
const ERROR_SLEEP_TIME = 30;
const CRITICAL_BATTERY_THRESHOLD = 15;

set_error_handler(function ($severity, $message, $file, $line) {
    if (! (error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function logMessage($message, $isError = false)
{
    $timestamp = date('Y-m-d H:i:s');
    $prefix = $isError ? 'ERROR' : 'INFO';
    $logEntry = "[{$timestamp}] [{$prefix}] {$message}" . PHP_EOL;

    try {
        file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    } catch (Exception $e) {
        fwrite(STDERR, "Cannot write to log file: {$e->getMessage()}" . PHP_EOL);
        fwrite(STDERR, $logEntry);
    }
}

function safeExecute($callback, $errorMessage, $maxRetries = MAX_RETRY_ATTEMPTS)
{
    $attempts = 0;
    $lastException = null;

    while ($attempts < $maxRetries) {
        try {
            return $callback();
        } catch (Throwable $e) {
            $lastException = $e;
            $attempts++;
            logMessage("{$errorMessage}: {$e->getMessage()}", true);

            if ($attempts < $maxRetries) {
                logMessage("Retry attempt {$attempts}/{$maxRetries} after error", true);
                sleep(RETRY_DELAY);
            }
        }
    }

    logMessage("Failed after {$maxRetries} attempts: {$lastException->getMessage()}", true);

    return null;
}

function readBatteryInfo($param)
{
    return safeExecute(
        function () use ($param) {
            $path = "/sys/class/power_supply/battery/{$param}";
            if (! file_exists($path) || ! is_readable($path)) {
                throw new Exception("Unable to read {$param} from {$path}");
            }

            $content = @file_get_contents($path);
            if (false === $content) {
                throw new Exception("Failed to read content from {$path}");
            }

            return trim($content);
        },
        "Error reading battery parameter {$param}"
    );
}

function getBatteryStatus()
{
    $batteryParams = [
        'percentage' => 'capacity',
        'status' => 'status',
        'temperature' => 'temp',
        'plugged' => 'charge_type',
        'health' => 'health',
        'current' => 'current_now',
    ];

    $batteryInfo = [];
    $anyMissing = false;

    foreach ($batteryParams as $key => $param) {
        $value = readBatteryInfo($param);
        if (null === $value) {
            $anyMissing = true;
            switch ($key) {
                case 'percentage':
                    $batteryInfo[$key] = 'Unknown';
                    break;
                case 'temperature':
                    $batteryInfo[$key] = 0;
                    break;
                default:
                    $batteryInfo[$key] = 'N/A';
            }
        } else {
            $batteryInfo[$key] = $value;
        }
    }

    if (is_numeric($batteryInfo['temperature'])) {
        $batteryInfo['temperature'] = (float) $batteryInfo['temperature'] / 10;
    }

    if ($anyMissing) {
        logMessage('Warning: Some battery information is missing, using defaults', true);
    }

    return $batteryInfo;
}

function formatBatteryMessage($battery)
{
    $pluggedMap = [
        'AC' => 'Connected to charger',
        'USB' => 'Connected via USB',
        'Wireless' => 'Wireless charging',
        'Fast' => 'Fast charging',
        'N/A' => 'Not connected',
    ];
    $statusMap = [
        'Charging' => 'Charging',
        'Discharging' => 'Discharging',
        'Full' => 'Full',
        'Not charging' => 'Not charging',
    ];
    $healthMap = [
        'Good' => 'Good condition',
        'Overheat' => 'Overheating',
        'Dead' => 'Battery dead',
        'Unspecified' => 'Unspecified',
    ];

    return "🔋 Battery Status:\n" .
        "• Charge Level: {$battery['percentage']}%\n" .
        '• Charging State: ' . ($pluggedMap[$battery['plugged']] ?? 'Unknown') . "\n" .
        '• Status: ' . ($statusMap[$battery['status']] ?? 'Unknown') . "\n" .
        '• Temperature: ' . round($battery['temperature'], 1) . "°C\n" .
        '• Health: ' . ($healthMap[$battery['health']] ?? 'Unknown') . "\n" .
        "• Current: {$battery['current']} µA\n";
}

function handleCriticalCharge($battery)
{
    $startTime = microtime(true);
    $maxExecutionTime = 5;

    if (is_numeric($battery['percentage']) && (int) $battery['percentage'] <= CRITICAL_BATTERY_THRESHOLD) {
        $message = "⚠️ <b>Critical Battery Warning</b> ⚠️\n" .
                   "Battery level is critically low at {$battery['percentage']}%.\n" .
                   'Please connect the charger immediately!';

        sendTelegramRequest('sendMessage', [
            'chat_id' => CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        logMessage("Critical battery level reached: {$battery['percentage']}%");
    }

    if ((microtime(true) - $startTime) > $maxExecutionTime) {
        logMessage('Warning: handleCriticalCharge took longer than expected', true);
    }
}

function sendTelegramRequest($method, $data, $retries = MAX_RETRY_ATTEMPTS)
{
    return safeExecute(
        function () use ($method, $data) {
            $url = 'https://api.telegram.org/bot' . TELEGRAM_API_TOKEN . "/{$method}";
            $ch = curl_init($url);

            if (false === $ch) {
                throw new Exception('Failed to initialize cURL');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);

            if (false === $response) {
                $error = curl_error($ch);
                $errNo = curl_errno($ch);
                curl_close($ch);
                throw new Exception("cURL Error #{$errNo}: {$error}");
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                logMessage("HTTP Error: {$httpCode}, Response: {$response}", true);
                throw new Exception("HTTP Error: {$httpCode}");
            }

            $decoded = json_decode($response, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (! isset($decoded['ok']) || true !== $decoded['ok']) {
                $errorDesc = isset($decoded['description']) ? $decoded['description'] : 'Unknown error';
                throw new Exception("Telegram API error: {$errorDesc}");
            }

            return $decoded;
        },
        "Error sending Telegram {$method} request",
        $retries
    );
}

function sendToTelegram($message)
{
    $data = [
        'chat_id' => CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'inline_keyboard' => [[['text' => '🔄 Refresh Data', 'callback_data' => 'refresh_battery']]],
        ]),
    ];

    $response = sendTelegramRequest('sendMessage', $data);

    if (null === $response) {
        logMessage('Failed to send message to Telegram', true);

        return false;
    }

    return true;
}

function getUpdates($offset)
{
    return sendTelegramRequest('getUpdates', [
        'offset' => $offset,
        'timeout' => 10,
        'allowed_updates' => json_encode(['callback_query']),
    ]);
}

function answerCallbackQuery($callbackQueryId, $text)
{
    return sendTelegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false,
    ]);
}

function runBatteryMonitor()
{
    logMessage('Battery monitoring script started');

    $lastHourlyUpdate = -1;
    $lastUpdateId = 0;

    $battery = getBatteryStatus();
    if (null !== $battery) {
        if (sendToTelegram(formatBatteryMessage($battery))) {
            logMessage('Initial battery status sent to Telegram');
        }
        handleCriticalCharge($battery);
    } else {
        logMessage('Could not retrieve initial battery status', true);
    }

    while (true) {
        try {
            $updates = getUpdates($lastUpdateId + 1);

            if (null !== $updates && isset($updates['result']) && is_array($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $lastUpdateId = $update['update_id'];

                    if (! empty($update['callback_query']) && 'refresh_battery' === $update['callback_query']['data']) {
                        $battery = getBatteryStatus();
                        $callbackId = $update['callback_query']['id'];

                        if (null !== $battery) {
                            if (sendToTelegram(formatBatteryMessage($battery))) {
                                answerCallbackQuery($callbackId, 'Data refreshed!');
                                logMessage('Battery status refreshed and sent to Telegram');
                                handleCriticalCharge($battery);
                            } else {
                                answerCallbackQuery($callbackId, 'Error sending data!');
                                logMessage('Failed to send battery status for refresh', true);
                            }
                        } else {
                            answerCallbackQuery($callbackId, 'Error retrieving data!');
                            logMessage('Failed to retrieve battery status for refresh', true);
                        }
                    }
                }
            }

            $currentHour = intval(date('H'));
            $currentMinute = intval(date('i'));

            if (TELEGRAM_SEND_MINUTE === $currentMinute && $lastHourlyUpdate !== $currentHour) {
                $battery = getBatteryStatus();
                if (null !== $battery) {
                    if (sendToTelegram(formatBatteryMessage($battery))) {
                        logMessage("Scheduled battery status for hour {$currentHour} sent successfully");
                        handleCriticalCharge($battery);
                    } else {
                        logMessage("Failed to send scheduled battery status for hour {$currentHour}", true);
                    }
                } else {
                    logMessage('Failed to retrieve battery status for hourly update', true);
                }

                $lastHourlyUpdate = $currentHour;

                sleep(min(HOURLY_CHECK_INTERVAL, 55));
            }

            usleep(200000);
        } catch (Exception $e) {
            logMessage('Error in main loop: ' . $e->getMessage(), true);
            sleep(ERROR_SLEEP_TIME);
        }
    }
}

try {
    runBatteryMonitor();
} catch (Exception $e) {
    logMessage('Critical error: ' . $e->getMessage(), true);
    exit(1);
}
