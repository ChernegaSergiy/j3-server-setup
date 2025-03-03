<?php

/**
 * Battery monitoring script for j3-server-setup
 *
 * This script monitors the battery level and status on an Android device.
 * It performs actions based on the battery level and sends battery information to Telegram.
 * Uses non-blocking asynchronous approach for API calls and updates.
 *
 * Usage: php battery.php
 */

declare(strict_types=1);

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

function logMessage(string $message, bool $isError = false) : void
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

/**
 * Execute a function safely with retries
 *
 * @param  callable  $callback  Function to execute
 * @param  string  $errorMessage  Error message to log
 * @param  int  $maxRetries  Maximum number of retry attempts
 * @return mixed|null Result or null on failure
 */
function safeExecute(callable $callback, string $errorMessage, int $maxRetries = MAX_RETRY_ATTEMPTS)
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

/**
 * Read battery information from system file
 *
 * @param  string  $param  Battery parameter to read
 * @return string|null Battery parameter value or null on failure
 */
function readBatteryInfo(string $param) : ?string
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

/**
 * Get battery status information
 *
 * @return array|null Battery information or null on failure
 */
function getBatteryStatus() : ?array
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

/**
 * Format battery information into a message
 *
 * @param  array  $battery  Battery information
 * @return string Formatted message
 */
function formatBatteryMessage(array $battery) : string
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

/**
 * Handle critical battery level
 *
 * @param  array  $battery  Battery information
 * @return bool Success status
 */
function handleCriticalCharge(array $battery) : bool
{
    if (! is_numeric($battery['percentage']) || (int) $battery['percentage'] > CRITICAL_BATTERY_THRESHOLD) {
        return true;
    }

    $message = "⚠️ <b>Critical Battery Warning</b> ⚠️\n" .
               "Battery level is critically low at {$battery['percentage']}%.\n" .
               'Please connect the charger immediately!';

    $result = sendTelegramRequestAsync('sendMessage', [
        'chat_id' => CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
    ]);

    logMessage("Critical battery level reached: {$battery['percentage']}%");

    return $result;
}

/**
 * Send asynchronous request to Telegram API
 *
 * @param  string  $method  Telegram API method
 * @param  array  $data  Request data
 * @param  int  $retries  Maximum retry attempts
 * @return bool Success status
 */
function sendTelegramRequestAsync(string $method, array $data, int $retries = MAX_RETRY_ATTEMPTS) : bool
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_API_TOKEN . "/{$method}";
    $ch = curl_init($url);

    if (false === $ch) {
        logMessage("Failed to initialize cURL for {$method}", true);

        return false;
    }

    $jsonData = json_encode($data);
    if (false === $jsonData) {
        logMessage("Failed to encode JSON data for {$method}: " . json_last_error_msg(), true);
        curl_close($ch);

        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_NOSIGNAL => 1,
    ]);

    $mh = curl_multi_init();
    curl_multi_add_handle($mh, $ch);

    $active = null;
    $started = time();
    $timeout = 5;

    do {
        $status = curl_multi_exec($mh, $active);

        if (time() - $started > $timeout) {
            logMessage("Async request to {$method} timed out after {$timeout} seconds", true);
            break;
        }

        if ($active) {
            curl_multi_select($mh, 0.1);
        }

        usleep(5000);

    } while ($active && CURLM_OK == $status);

    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_multi_remove_handle($mh, $ch);
    curl_multi_close($mh);
    curl_close($ch);

    $success = false;
    if ($httpCode >= 200 && $httpCode < 300 && ! empty($response)) {
        $decoded = json_decode($response, true);
        if (JSON_ERROR_NONE === json_last_error() && isset($decoded['ok']) && true === $decoded['ok']) {
            $success = true;
        } else {
            $errorDesc = isset($decoded['description']) ? $decoded['description'] : 'Unknown error';
            logMessage("Telegram API error for {$method}: {$errorDesc}", true);
        }
    } else {
        logMessage("HTTP Error in {$method}: {$httpCode}, Error: {$error}", true);
    }

    return $success;
}

/**
 * Send message to Telegram
 *
 * @param  string  $message  Message text
 * @return bool Success status
 */
function sendToTelegram(string $message) : bool
{
    $data = [
        'chat_id' => CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [[['text' => '🔄 Refresh Data', 'callback_data' => 'refresh_battery']]],
        ],
    ];

    $success = sendTelegramRequestAsync('sendMessage', $data);

    if (! $success) {
        logMessage('Failed to send message to Telegram', true);

        return false;
    }

    return true;
}

/**
 * Get updates from Telegram
 *
 * @param  int  $offset  Update ID offset
 * @param  int  $timeout  Long polling timeout
 * @return array|null Updates or null on failure
 */
function getUpdatesAsync(int $offset, int $timeout = 1) : ?array
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_API_TOKEN . '/getUpdates';
    $ch = curl_init($url);

    if (false === $ch) {
        logMessage('Failed to initialize cURL for getUpdates', true);

        return null;
    }

    $data = [
        'offset' => $offset,
        'timeout' => $timeout,
        'allowed_updates' => ['callback_query'],
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => $timeout + 2,
        CURLOPT_NOSIGNAL => 1,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (false === $response || $httpCode >= 400) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (JSON_ERROR_NONE !== json_last_error() || ! isset($decoded['ok']) || true !== $decoded['ok']) {
        return null;
    }

    return $decoded;
}

/**
 * Answer callback query
 *
 * @param  string  $callbackQueryId  Callback query ID
 * @param  string  $text  Text to show
 * @return bool Success status
 */
function answerCallbackQuery(string $callbackQueryId, string $text) : bool
{
    return sendTelegramRequestAsync('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false,
    ]);
}

/**
 * Main battery monitoring function
 */
function runBatteryMonitor() : void
{
    logMessage('Battery monitoring script started');

    $lastHourlyUpdate = -1;
    $lastUpdateId = 0;
    $lastHourlyCheckTime = 0;
    $lastUpdateCheckTime = 0;

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
            $currentTime = time();

            if ($currentTime - $lastUpdateCheckTime >= 1) {
                $updates = getUpdatesAsync($lastUpdateId + 1, 1);
                $lastUpdateCheckTime = $currentTime;

                if (null !== $updates && isset($updates['result']) && is_array($updates['result'])) {
                    foreach ($updates['result'] as $update) {
                        $lastUpdateId = $update['update_id'];

                        if (! empty($update['callback_query']) && 'refresh_battery' === $update['callback_query']['data']) {
                            $battery = getBatteryStatus();
                            $callbackId = $update['callback_query']['id'];

                            if (null !== $battery) {
                                answerCallbackQuery($callbackId, 'Processing request...');

                                if (sendToTelegram(formatBatteryMessage($battery))) {
                                    logMessage('Battery status refreshed and sent to Telegram');
                                    handleCriticalCharge($battery);
                                } else {
                                    logMessage('Failed to send battery status for refresh', true);
                                }
                            } else {
                                answerCallbackQuery($callbackId, 'Error retrieving data!');
                                logMessage('Failed to retrieve battery status for refresh', true);
                            }
                        }
                    }
                }
            }

            $currentHour = (int) date('H');
            $currentMinute = (int) date('i');

            if (TELEGRAM_SEND_MINUTE === $currentMinute &&
                $lastHourlyUpdate !== $currentHour &&
                $currentTime - $lastHourlyCheckTime >= 55) {

                $lastHourlyCheckTime = $currentTime;

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
            }

            usleep(100000);

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
