<?php
function sendApiRequest($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return $err ? $err : $response;
}

if (isset($_POST['update_ip']) && !empty($_POST['esp_ip'])) {
    file_put_contents('latest_ip.txt', trim($_POST['esp_ip']));
    $esp_ip = trim($_POST['esp_ip']);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => true, 'esp_ip' => $esp_ip]);
        exit;
    }
} elseif (file_exists('latest_ip.txt')) {
    $esp_ip = trim(file_get_contents('latest_ip.txt'));
} else {
    $esp_ip = '';
}

$current_ssid = '';
$wifi_msg = '';
if (!empty($esp_ip)) {
    $wifi_json = sendApiRequest("http://{$esp_ip}/getwifi");
    if ($wifi_json) {
        $wifi_arr = json_decode($wifi_json, true);
        if (isset($wifi_arr['ssid'])) {
            $current_ssid = $wifi_arr['ssid'];
        } elseif (is_string($wifi_json) && $wifi_json !== '') {
            $current_ssid = trim($wifi_json);
        }
    }
}

if (isset($_POST['update_wifi']) && !empty($esp_ip)) {
    $ssid = trim($_POST['ssid']);
    $pass = trim($_POST['pass']);
    $wifi_url = "http://{$esp_ip}/setwifi?ssid=" . urlencode($ssid) . "&pass=" . urlencode($pass);
    $wifi_msg = sendApiRequest($wifi_url);
    $current_ssid = $ssid;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode([
            'success' => true,
            'ssid' => $ssid,
            'msg' => $wifi_msg,
        ]);
        exit;
    }
}

$utc_offset = '';
$utc_result = '';
if (!empty($esp_ip)) {
    $utc_offset_raw = sendApiRequest("http://{$esp_ip}/gettime");
    if (is_numeric(trim($utc_offset_raw))) {
        $utc_offset = trim($utc_offset_raw);
    }
}

if (isset($_POST['set_utc_offset']) && isset($_POST['utc_offset']) && $esp_ip) {
    $utc_offset = $_POST['utc_offset'];
    $url = "http://{$esp_ip}/settime?utc=" . urlencode($utc_offset);
    $utc_result = sendApiRequest($url);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode([
            'success' => true,
            'utc_offset' => $utc_offset,
            'msg' => $utc_result
        ]);
        exit;
    }
} elseif (isset($_POST['utc_offset'])) {
    $utc_offset = $_POST['utc_offset'];
}

// Only commands (theme and dht removed, handled in info panel)
$api_commands = [
    [
        'label' => 'Add a buzz time',
        'endpoint' => 'buzztime',
        'params' => [
            'add' => ['label' => 'Time (HH:MM)', 'type' => 'time'],
            'song' => [
                'label' => 'Song',
                'type' => 'select',
                'options' => [
                    'hedwig' => 'Hedwig',
                    'merry' => 'Merry',
                    'shapeofyou' => 'Shape of You',
                    'pirates' => 'Pirates',
                    'pinkpanther' => 'Pink Panther',
                    'nokia' => 'Nokia'
                ]
            ]
        ]
    ],
    [
        'label' => 'Change song for buzz time',
        'endpoint' => 'buzztime',
        'params' => [
            'set' => ['label' => 'Time (HH:MM)', 'type' => 'custom', 'options' => []],
            'song' => [
                'label' => 'Song',
                'type' => 'select',
                'options' => [
                    'hedwig' => 'Hedwig',
                    'merry' => 'Merry',
                    'shapeofyou' => 'Shape of You',
                    'pirates' => 'Pirates',
                    'pinkpanther' => 'Pink Panther',
                    'nokia' => 'Nokia'
                ]
            ]
        ]
    ],
    [
        'label' => 'Remove a buzz time',
        'endpoint' => 'buzztime',
        'params' => [
            'remove' => ['label' => 'Time (HH:MM)', 'type' => 'custom', 'options' => []]
        ]
    ],
    [
        'label' => 'Set bitmap animation window',
        'endpoint' => 'bitmapwindow',
        'params' => [
            'start' => ['label' => 'Start (min)', 'type' => 'number'],
            'end' => ['label' => 'End (min)', 'type' => 'number']
        ]
    ],
    [
        'label' => 'play a buzz now',
        'endpoint' => 'buzznow',
        'params' => [
            'song' => [
                'label' => 'Song (default: hedwig)',
                'type' => 'select',
                'options' => [
                    '' => '(Default: Hedwig)',
                    'hedwig' => 'Hedwig',
                    'merry' => 'Merry',
                    'shapeofyou' => 'Shape of You',
                    'pirates' => 'Pirates',
                    'pinkpanther' => 'Pink Panther',
                    'nokia' => 'Nokia'
                ]
            ]
        ]
    ]
];

$result = '';
$buzz_list = '';
$buzz_times = [];
$bitmap_window = '';
$current_theme = '';
// For theme switching
$themes = [
    '0' => [
        'name' => 'Default',
        'img' => 'theme_default.png',
    ],
    '1' => [
        'name' => 'Anime',
        'img' => 'theme_anime.png',
    ]
];

// Always fetch buzz times and bitmap window, theme, dht if IP is present
if (!empty($esp_ip)) {
    $buzz_url = "http://{$esp_ip}/buzztime";
    $buzz_list = sendApiRequest($buzz_url);

    if ($buzz_list) {
        if ($buzz_arr = json_decode($buzz_list, true)) {
            if (isset($buzz_arr['buzz_times'])) {
                $buzz_times = $buzz_arr['buzz_times'];
            } elseif (is_array($buzz_arr)) {
                $buzz_times = $buzz_arr;
            }
        } else {
            if (preg_match_all('/\b([01]?\d|2[0-3]):[0-5]\d\b/', $buzz_list, $matches)) {
                $buzz_times = $matches[0];
            }
        }
    }

    $bitmap_url = "http://{$esp_ip}/bitmapwindow";
    $bitmap_window = sendApiRequest($bitmap_url);

    // Fetch current theme (numeric)
    $theme_url = "http://{$esp_ip}/displaytheme";
    $theme_resp = sendApiRequest($theme_url);
    if (is_numeric(trim($theme_resp)) && isset($themes[trim($theme_resp)])) {
        $current_theme = trim($theme_resp);
    } else {
        $current_theme = '0';
    }
}

// On POST for API command or theme change
if (isset($_POST['endpoint']) && isset($_POST['command'])) {
    $endpoint = $_POST['endpoint'];
    $params = $_POST['params'] ?? [];

    if ($endpoint === 'buzztime' && isset($params['remove'])) {
        $params['remove'] = trim($params['remove']);
    }
    if ($endpoint === 'buzztime' && isset($params['set'])) {
        $params['set'] = trim($params['set']);
    }

    $url = "http://{$esp_ip}/{$endpoint}";
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    $result = sendApiRequest($url);

    $buzz_url = "http://{$esp_ip}/buzztime";
    $buzz_list = sendApiRequest($buzz_url);
    if ($buzz_list) {
        if ($buzz_arr = json_decode($buzz_list, true)) {
            if (isset($buzz_arr['buzz_times'])) {
                $buzz_times = $buzz_arr['buzz_times'];
            } elseif (is_array($buzz_arr)) {
                $buzz_times = $buzz_arr;
            }
        } else {
            if (preg_match_all('/\b([01]?\d|2[0-3]):[0-5]\d\b/', $buzz_list, $matches)) {
                $buzz_times = $matches[0];
            }
        }
    }
    $bitmap_url = "http://{$esp_ip}/bitmapwindow";
    $bitmap_window = sendApiRequest($bitmap_url);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <title>Clock Buzz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="3.4.16.js"></script> 
    <script src="sweetalert2.all.min.js"></script>
    <style>
        .theme-preview-img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #c7d2fe;
            margin-left: 0.5rem;
            box-shadow: 0 2px 8px 0 #0001;
        }
        .theme-eye-btn:focus {
            outline: none;
        }
        html.dark ::-webkit-scrollbar {
            background: #2d2e32;
        }
        html.dark ::-webkit-scrollbar-thumb {
            background: #3b3d44;
        }
    </style>
    <script>
      tailwind.config = {
        darkMode: 'class'
      };
    </script>
</head>
<body class="bg-gray-50 dark:bg-zinc-900 min-h-screen flex flex-col items-center py-8 transition-colors duration-300">
    <div class="w-full max-w-xl bg-white dark:bg-zinc-800 rounded-xl shadow-lg p-8 transition-colors duration-300">
        <div class="flex justify-between items-start mb-2">
            <h1 class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">ClockBuzz Client</h1>
            <div class="ml-4 flex flex-col items-end">
                <div class="flex items-center space-x-2">
                    <span id="wifiDisplay" class="text-base text-gray-700 dark:text-gray-200">
                        Wi-Fi: <span id="wifiSsid"><?= htmlspecialchars($current_ssid ?: 'Not set') ?></span>
                    </span>
                    <button type="button" id="editWifiBtn" class="text-gray-400 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none" title="Edit WiFi" <?= empty($esp_ip) ? 'disabled' : '' ?>>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </button>
                </div>
                <div class="flex items-center mt-1 space-x-1 text-xs text-gray-600 dark:text-gray-300" id="dhtDisplay" style="min-height: 22px;">
                    <?php if (!empty($esp_ip)): ?>
                        <span> </span>
                        <span id="dhtValue" class="font-mono"></span>
                        <span id="dhtLoading" class="animate-pulse text-gray-400">...</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- ESP IP Display/Editor -->
        <div class="flex flex-col items-center mb-4 mt-4">
            <div class="flex items-center space-x-2">
                <span id="espIpDisplay" class="text-lg font-semibold text-gray-700 dark:text-gray-200"><?= htmlspecialchars($esp_ip ?: 'No IP set') ?></span>
                <button type="button" id="editEspIpBtn" class="text-gray-400 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none" title="Edit IP">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </button>
            </div>
            <form id="espIpForm" class="mt-2 hidden flex-row items-center space-x-2" method="POST" autocomplete="off">
                <input type="hidden" name="update_ip" value="1">
                <input name="esp_ip" type="text" required placeholder="e.g. 192.168.1.100" class="px-3 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400" value="<?= htmlspecialchars($esp_ip) ?>">
                <button type="submit" class="bg-indigo-600 text-white rounded px-3 py-1 hover:bg-indigo-700 transition">Save</button>
                <button type="button" id="cancelEspIpBtn" class="text-gray-500 ml-1">Cancel</button>
            </form>
        </div>
        <!-- UTC Offset Section -->
        <div class="mb-6 flex flex-row items-center justify-between" id="utcOffsetWrapper">
            <!-- UTC Offset Selector (left) -->
            <div class="flex flex-row items-center space-x-3">
                <label class="block text-gray-700 dark:text-gray-200 font-semibold mb-1" for="utcOffsetSelect">UTC Offset</label>
                <select id="utcOffsetSelect" name="utc_offset" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-zinc-900 dark:border-zinc-700 dark:text-gray-100" <?= empty($esp_ip) ? 'disabled' : '' ?>>
                    <option value="" disabled>Select UTC Offset</option>
                    <?php for ($i = -12; $i <= 14; $i++): ?>
                        <option value="<?= $i ?>" <?= (string)$i === (string)$utc_offset ? 'selected' : '' ?>>UTC<?= $i >= 0 ? '+' : '' ?><?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <span id="utcOffsetMsg" class="text-sm text-gray-600 dark:text-gray-200"></span>
            </div>
            <!-- Toggle web theme (right) -->
            <div class="flex justify-center">
                <button id="darkToggleBtn" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition focus:outline-none">
                    <span id="darkModeLabel">Auto</span>
                </button>
            </div>
        </div>
        <?php if (!empty($utc_result)): ?>
            <div class="mb-4 p-2 bg-gray-100 dark:bg-zinc-700 border border-gray-200 dark:border-zinc-600 rounded text-sm text-gray-700 dark:text-gray-200">
                <div class="font-semibold mb-1">UTC Offset Response:</div>
                <pre><?= htmlspecialchars($utc_result) ?></pre>
            </div>
        <?php endif; ?>
        <?php if (!empty($esp_ip)): ?>
            <!-- Theme Section -->
            <div class="mb-6">
            <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-800 rounded-lg p-4 flex flex-col items-center">
                <div class="font-semibold text-green-700 dark:text-green-300 mb-3">Current Theme</div>
                <div class="flex items-center space-x-8">
                <?php foreach ($themes as $k => $t): ?>
                    <div class="flex flex-col items-center" style="min-width:80px;">
                    <form method="POST" class="themeSwitchForm flex flex-col items-center" <?= ($current_theme == $k ? 'data-current="1"' : '') ?>>
                        <input type="hidden" name="endpoint" value="displaytheme">
                        <input type="hidden" name="command" value="theme">
                        <input type="hidden" name="params[theme]" value="<?= $k ?>">
                        <button type="submit" class="focus:outline-none" <?= ($current_theme == $k ? 'disabled' : '') ?>>
                        <img src="<?= htmlspecialchars($t['img']) ?>" alt="<?= htmlspecialchars($t['name']) ?> Theme"
                             class="theme-preview-img <?= ($current_theme == $k ? 'ring-2 ring-green-400 dark:ring-green-300' : 'opacity-70 hover:opacity-100 hover:ring-2 hover:ring-green-300') ?>">
                        </button>
                    </form>
                    <span class="text-xs mt-2 <?= ($current_theme == $k ? 'text-green-700 dark:text-green-300 font-semibold' : 'text-gray-500 dark:text-gray-300') ?>"><?= htmlspecialchars($t['name']) ?></span>
                    <?php if ($current_theme == $k): ?>
                        <span class="text-green-500 dark:text-green-300 text-xs mt-1">selected</span>
                    <?php endif; ?>
                    <button
                        type="button"
                        class="theme-eye-btn mt-2 p-0 bg-transparent hover:bg-transparent focus:outline-none"
                        data-theme-img="<?= htmlspecialchars($t['img']) ?>"
                        data-theme-name="<?= htmlspecialchars($t['name']) ?>"
                        aria-label="Preview full theme"
                        title="Preview full theme"
                    >
                        <svg class="h-6 w-6 text-indigo-400 dark:text-indigo-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($esp_ip) && $bitmap_window): ?>
            <div class="mb-6">
                <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="font-semibold text-blue-700 dark:text-blue-300 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400 dark:text-blue-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        Current Bitmap Animation Window
                    </div>
                    <pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap"><?= htmlspecialchars($bitmap_window) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($esp_ip) && $buzz_list): ?>
            <div class="mb-6">
                <div class="bg-indigo-50 dark:bg-indigo-900 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                    <div class="font-semibold text-indigo-700 dark:text-indigo-300 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-400 dark:text-indigo-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Current Buzz Times
                    </div>
                    <pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap"><?= htmlspecialchars($buzz_list) ?></pre>
                </div>
            </div>
        <?php elseif (empty($esp_ip)): ?>
            <div class="mb-6">
                <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 text-yellow-700 dark:text-yellow-300 text-sm text-center">
                    Enter your ESP IP address to see current buzz times.
                </div>
            </div>
        <?php endif; ?>
        <hr class="my-8 border-t-2 border-gray-200 dark:border-zinc-700">
        <form method="POST" class="space-y-6" id="apiForm">
            <input type="hidden" name="esp_ip" value="<?= htmlspecialchars($esp_ip) ?>">
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-semibold mb-1">API Command</label>
                <select name="command" id="commandSelect" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-zinc-900 dark:border-zinc-700 dark:text-gray-100">
                    <?php foreach ($api_commands as $i => $cmd): ?>
                        <option value="<?= $i ?>" <?= (isset($_POST['command']) && $_POST['command'] == $i) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cmd['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="paramsContainer"></div>
            <input type="hidden" name="endpoint" id="endpointInput" value="">
            <button type="submit" class="w-full bg-indigo-600 dark:bg-indigo-500 text-white font-semibold py-2 rounded hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">Send API Request</button>
        </form>

        <?php if ($result): ?>
            <div class="mt-6 p-4 bg-gray-100 dark:bg-zinc-700 rounded border border-gray-200 dark:border-zinc-600">
                <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">API Response:</div>
                <pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap"><?= htmlspecialchars($result) ?></pre>
            </div>
        <?php endif; ?>
        <div class="mt-8 text-gray-500 dark:text-gray-300 text-xs text-center">
            Nana will always play 'hedwig' unless you pick another song~
        </div>
    </div>

    <script>
        const apiCommands = <?= json_encode($api_commands) ?>;
        const paramsContainer = document.getElementById('paramsContainer');
        const commandSelect = document.getElementById('commandSelect');
        const endpointInput = document.getElementById('endpointInput');
        const buzzTimes = <?= json_encode($buzz_times) ?>;

        function renderParams(idx) {
            paramsContainer.innerHTML = '';
            const cmd = apiCommands[idx];
            endpointInput.value = cmd.endpoint;
            if (!cmd.params || Object.keys(cmd.params).length === 0) return;
            for (const [key, param] of Object.entries(cmd.params)) {
                const label = document.createElement('label');
                label.className = 'block text-gray-700 dark:text-gray-200 font-semibold mb-1';
                label.textContent = param.label;

                if ((cmd.label === "Remove a buzz time" && key === "remove") ||
                    (cmd.label === "Change song for buzz time" && key === "set")) {
                    const select = document.createElement('select');
                    select.name = `params[${key}]`;
                    select.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-zinc-900 dark:border-zinc-700 dark:text-gray-100 mb-4';
                    if (buzzTimes.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.text = 'No available buzz times';
                        select.appendChild(opt);
                        select.disabled = true;
                    } else {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.text = '-- Select a buzz time --';
                        select.appendChild(opt);
                        buzzTimes.forEach(time => {
                            const option = document.createElement('option');
                            option.value = time;
                            option.text = time;
                            select.appendChild(option);
                        });
                    }
                    paramsContainer.appendChild(label);
                    paramsContainer.appendChild(select);
                } else if (param.type === 'select') {
                    const select = document.createElement('select');
                    select.name = `params[${key}]`;
                    select.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-zinc-900 dark:border-zinc-700 dark:text-gray-100 mb-4';
                    for (const [val, text] of Object.entries(param.options)) {
                        const option = document.createElement('option');
                        option.value = val;
                        option.text = text;
                        select.appendChild(option);
                    }
                    paramsContainer.appendChild(label);
                    paramsContainer.appendChild(select);
                } else {
                    const input = document.createElement('input');
                    input.name = `params[${key}]`;
                    input.type = param.type;
                    input.placeholder = param.placeholder || '';
                    input.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-zinc-900 dark:border-zinc-700 dark:text-gray-100 mb-4';
                    paramsContainer.appendChild(label);
                    paramsContainer.appendChild(input);
                }
            }
        }

        commandSelect.addEventListener('change', function () {
            renderParams(this.value);
        });

        document.addEventListener('DOMContentLoaded', function () {
            renderParams(commandSelect.value);
        });

        // ESP IP Edit Logic
        const espIpDisplay = document.getElementById('espIpDisplay');
        const editEspIpBtn = document.getElementById('editEspIpBtn');
        const espIpForm = document.getElementById('espIpForm');
        const cancelEspIpBtn = document.getElementById('cancelEspIpBtn');

        editEspIpBtn.addEventListener('click', function () {
            espIpDisplay.parentElement.style.display = 'none';
            espIpForm.classList.remove('hidden');
            espIpForm.querySelector('input[name="esp_ip"]').focus();
        });

        cancelEspIpBtn.addEventListener('click', function () {
            espIpDisplay.parentElement.style.display = '';
            espIpForm.classList.add('hidden');
        });

        espIpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(espIpForm);
            fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        });

        // WiFi SweetAlert2 Edit Logic
        const wifiSsid = document.getElementById('wifiSsid');
        const editWifiBtn = document.getElementById('editWifiBtn');
        editWifiBtn.addEventListener('click', function () {
            Swal.fire({
            title: '<span class="text-indigo-700 font-bold">Change Wi-Fi</span>',
            html: `
                <div class="flex flex-col gap-4 items-stretch">
                <input id="swal-ssid" class="swal2-input !px-4 !py-2 !rounded-lg !border !border-gray-300 !focus:ring-2 !focus:ring-indigo-400 !text-base" placeholder="SSID" value="${wifiSsid.textContent}">
                <div class="relative flex items-center">
                    <input id="swal-pass" type="password" class="swal2-input !px-4 !py-2 !rounded-lg !border !border-gray-300 !focus:ring-2 !focus:ring-indigo-400 !text-base w-full pr-12" placeholder="Password">
                    <button type="button" id="swal-toggle-pass" tabindex="-1"
                    class="absolute right-11 top-[60%] -translate-y-1/2 bg-transparent border-none p-0 m-0 cursor-pointer focus:outline-none"
                    aria-label="Toggle password visibility">
                    <svg id="swal-toggle-pass-icon" class="h-6 w-6 text-indigo-400 hover:text-indigo-600 transition" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path id="swal-toggle-eye-path" stroke-linecap="round" stroke-linejoin="round"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    </button>
                </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<span class="font-semibold">Save</span>',
            customClass: {
                popup: 'rounded-2xl p-0',
                title: 'pt-6 pb-2',
                htmlContainer: 'pb-6 px-6',
                actions: 'pb-4 px-6 flex justify-end gap-2',
                confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-6 py-2 font-semibold shadow transition',
                cancelButton: 'bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg px-6 py-2 font-semibold shadow transition',
            },
            didOpen: () => {
                const passInput = document.getElementById('swal-pass');
                const toggleBtn = document.getElementById('swal-toggle-pass');
                const eyeIcon = document.getElementById('swal-toggle-pass-icon');
                let visible = false;
                toggleBtn.addEventListener('click', function () {
                visible = !visible;
                passInput.type = visible ? 'text' : 'password';
                if (visible) {
                    eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    <circle cx="12" cy="12" r="3" />
                    `;
                } else {
                    eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    <circle cx="12" cy="12" r="3" />
                    <line x1="4" y1="20" x2="20" y2="4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    `;
                }
                });
            },
            preConfirm: () => {
                const ssid = document.getElementById('swal-ssid').value.trim();
                const pass = document.getElementById('swal-pass').value.trim();
                if (!ssid || !pass) {
                Swal.showValidationMessage('Both SSID and Password are required!');
                return false;
                }
                return { ssid, pass };
            }
            }).then((result) => {
            if (result.isConfirmed && result.value) {
                const formData = new FormData();
                formData.append('update_wifi', '1');
                formData.append('ssid', result.value.ssid);
                formData.append('pass', result.value.pass);
                fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                if (data.success) {
                    wifiSsid.textContent = data.ssid;
                    Swal.fire({
                    icon: 'success',
                    title: '<span class="text-green-700 font-bold">Wi-Fi settings changed!</span>',
                    html: 'The Wi-Fi will update <b>after reboot</b>.<br>Current Wi-Fi still: <b>' + data.ssid + '</b>',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'rounded-2xl p-0',
                        title: 'pt-6 pb-2',
                        htmlContainer: 'pb-6 px-6',
                        actions: 'pb-4 px-6 flex justify-end gap-2',
                        confirmButton: 'bg-green-600 hover:bg-green-700 text-white rounded-lg px-6 py-2 font-semibold shadow transition',
                    }
                    });
                } else {
                    Swal.fire({
                    icon: 'error',
                    title: '<span class="text-red-700 font-bold">Failed</span>',
                    text: data.msg || 'Could not update Wi-Fi',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'rounded-2xl p-0',
                        title: 'pt-6 pb-2',
                        htmlContainer: 'pb-6 px-6',
                        actions: 'pb-4 px-6 flex justify-end gap-2',
                        confirmButton: 'bg-red-600 hover:bg-red-700 text-white rounded-lg px-6 py-2 font-semibold shadow transition',
                    }
                    });
                }
                })
                .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: '<span class="text-red-700 font-bold">Failed</span>',
                    text: 'Could not update Wi-Fi',
                    confirmButtonText: 'OK',
                    customClass: {
                    popup: 'rounded-2xl p-0',
                    title: 'pt-6 pb-2',
                    htmlContainer: 'pb-6 px-6',
                    actions: 'pb-4 px-6 flex justify-end gap-2',
                    confirmButton: 'bg-red-600 hover:bg-red-700 text-white rounded-lg px-6 py-2 font-semibold shadow transition',
                    }
                });
                });
            }
            });
        });

        // UTC Offset Select: auto update on change
        const utcOffsetSelect = document.getElementById('utcOffsetSelect');
        const utcOffsetMsg = document.getElementById('utcOffsetMsg');
        if (utcOffsetSelect) {
            utcOffsetSelect.addEventListener('change', function () {
                if (!this.value) return;
                const formData = new FormData();
                formData.append('set_utc_offset', '1');
                formData.append('utc_offset', this.value);
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        utcOffsetMsg.textContent = "Set to UTC" + (data.utc_offset >= 0 ? "+" : "") + data.utc_offset;
                    } else {
                        utcOffsetMsg.textContent = "Failed to set UTC";
                    }
                });
            });
        }

        // Theme switch AJAX for preview, keep page in sync on change
        document.querySelectorAll('.themeSwitchForm').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(() => {
                    window.location.reload();
                });
            });
        });

        // DHT AJAX update every 10s
        <?php if (!empty($esp_ip)): ?>
        function updateDHT() {
            const dhtValue = document.getElementById('dhtValue');
            const dhtLoading = document.getElementById('dhtLoading');
            dhtLoading.style.display = "";
            fetch("/esp_proxy.php?esp_ip=<?= urlencode($esp_ip) ?>&path=getdht")
                .then(r => {
                    if (!r.ok) throw new Error("HTTP error: " + r.status);
                    return r.text();
                })
                .then(text => {
                    let pretty = "n/a";
                    try {
                        const data = JSON.parse(text);
                        if (typeof data.temperature !== "undefined" && typeof data.humidity !== "undefined") {
                            pretty = `🌡️ ${data.temperature}°C 💧 ${data.humidity}%`;
                        } else {
                            pretty = "Missing field";
                        }
                    } catch (e) {
                        pretty = "JSON error";
                    }
                    dhtValue.textContent = pretty;
                    dhtLoading.style.display = "none";
                })
                .catch((err) => {
                    dhtValue.textContent = "Error";
                    dhtLoading.style.display = "none";
                    console.error("DHT fetch error:", err);
                });
        }
        updateDHT();
        setInterval(updateDHT, 10000);
        <?php endif; ?>

        // Theme preview logic using SweetAlert2
        document.querySelectorAll('.theme-eye-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const img = this.getAttribute('data-theme-img');
                const name = this.getAttribute('data-theme-name');
                Swal.fire({
                    title: `<span class="text-indigo-700 font-bold">${name} Theme Preview</span>`,
                    html: `
                        <div class="flex flex-col items-center">
                            <img src="${img}" class="rounded-lg shadow-2xl border-4 border-indigo-300 max-w-full"
                                 style="max-width: 90vw; max-height: 60vh; object-fit: contain;"
                                 alt="${name} Full Preview">
                        </div>
                    `,
                    showCloseButton: true,
                    showConfirmButton: false,
                    background: '#f3f4f6',
                    customClass: {
                        popup: 'p-0 rounded-2xl',
                        title: 'pt-6 pb-2',
                        htmlContainer: 'pb-6',
                        closeButton: 'absolute top-3 right-3 text-gray-400 hover:text-indigo-700 transition'
                    },
                    width: 'auto'
                });
            });
        });

        // --- DARK MODE LOGIC ---
        function setDarkMode(mode) {
            if (mode === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('nana_darkmode', 'dark');
                document.getElementById('darkModeLabel').textContent = 'Dark';
            } else if (mode === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('nana_darkmode', 'light');
                document.getElementById('darkModeLabel').textContent = 'Light';
            } else {
                localStorage.setItem('nana_darkmode', 'auto');
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.getElementById('darkModeLabel').textContent = 'Auto';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.getElementById('darkModeLabel').textContent = 'Auto';
                }
            }
        }
        function detectDarkModeInit() {
            const saved = localStorage.getItem('nana_darkmode');
            if (saved === 'light') setDarkMode('light');
            else if (saved === 'dark') setDarkMode('dark');
            else setDarkMode('auto');
            if (!saved || saved === 'auto') {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    setDarkMode('auto');
                });
            }
        }
        document.getElementById('darkToggleBtn').addEventListener('click', function () {
            let saved = localStorage.getItem('nana_darkmode') || 'auto';
            if (saved === 'auto') setDarkMode('dark');
            else if (saved === 'dark') setDarkMode('light');
            else setDarkMode('auto');
        });
        detectDarkModeInit();
    </script>
</body>
</html>