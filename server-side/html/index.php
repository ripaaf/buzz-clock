<?php
function sendApiRequest($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return $err ? $err : $response;
}

// Handle ESP IP update via POST (AJAX or form submit)
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

// --- WiFi info
$current_ssid = '';
$wifi_msg = '';
if (!empty($esp_ip)) {
    // Fetch current SSID (endpoint /getwifi returns JSON: {"ssid": "your_ssid"})
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

// Handle WiFi update (SweetAlert2)
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

// UTC offset default and set logic
$utc_offset = '';
$utc_result = '';
if (!empty($esp_ip)) {
    // Fetch UTC offset from /gettime (plain text)
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

// Main API commands
$api_commands = [
    [
        'label' => 'Add a buzz time',
        'endpoint' => 'buzztime',
        'params' => [
            'add' => ['label' => 'Time (HH:MM)', 'type' => 'time'],
            'song' => ['label' => 'Song', 'type' => 'select', 'options' => ['hedwig' => 'Hedwig', 'merry' => 'Merry']]
        ]
    ],
    [
        'label' => 'Change song for buzz time',
        'endpoint' => 'buzztime',
        'params' => [
            'set' => ['label' => 'Time (HH:MM)', 'type' => 'custom', 'options' => []],
            'song' => ['label' => 'Song', 'type' => 'select', 'options' => ['hedwig' => 'Hedwig', 'merry' => 'Merry']]
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
    ]
];

$result = '';
$buzz_list = '';
$buzz_times = [];
$bitmap_window = '';

// Always fetch buzz times and bitmap window if IP is present
if (!empty($esp_ip)) {
    $buzz_url = "http://{$esp_ip}/buzztime";
    $buzz_list = sendApiRequest($buzz_url);

    // Try to parse buzz times as array
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

    // Fetch bitmap window for info display
    $bitmap_url = "http://{$esp_ip}/bitmapwindow";
    $bitmap_window = sendApiRequest($bitmap_url);
}

// On POST for API command
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

    // Refresh buzz list and buzz_times
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NanaClock API Client</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center py-8">
    <div class="w-full max-w-xl bg-white rounded-xl shadow-lg p-8">
        <div class="flex justify-between items-start mb-2">
            <h1 class="text-2xl font-bold text-indigo-700">NanaClock Client</h1>
            <!-- WiFi Display -->
            <div class="ml-4 flex flex-col items-end">
                <div class="flex items-center space-x-2">
                    <span id="wifiDisplay" class="text-base text-gray-700">
                        Wi-Fi: <span id="wifiSsid"><?= htmlspecialchars($current_ssid ?: 'Not set') ?></span>
                    </span>
                    <button type="button" id="editWifiBtn" class="text-gray-400 hover:text-indigo-600 focus:outline-none" title="Edit WiFi" <?= empty($esp_ip) ? 'disabled' : '' ?>>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- ESP IP Display/Editor -->
        <div class="flex flex-col items-center mb-4 mt-4">
            <div class="flex items-center space-x-2">
                <span id="espIpDisplay" class="text-lg font-semibold text-gray-700"><?= htmlspecialchars($esp_ip ?: 'No IP set') ?></span>
                <button type="button" id="editEspIpBtn" class="text-gray-400 hover:text-indigo-600 focus:outline-none" title="Edit IP">
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
        <div class="mb-6 flex flex-row items-center space-x-3" id="utcOffsetWrapper">
            <label class="block text-gray-700 font-semibold mb-1" for="utcOffsetSelect">UTC Offset</label>
            <select id="utcOffsetSelect" name="utc_offset" class="px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400" <?= empty($esp_ip) ? 'disabled' : '' ?>>
                <option value="" disabled>Select UTC Offset</option>
                <?php for ($i = -12; $i <= 14; $i++): ?>
                    <option value="<?= $i ?>" <?= (string)$i === (string)$utc_offset ? 'selected' : '' ?>>UTC<?= $i >= 0 ? '+' : '' ?><?= $i ?></option>
                <?php endfor; ?>
            </select>
            <span id="utcOffsetMsg" class="text-sm text-gray-600"></span>
        </div>

        <?php if (!empty($utc_result)): ?>
            <div class="mb-4 p-2 bg-gray-100 border border-gray-200 rounded text-sm text-gray-700">
                <div class="font-semibold mb-1">UTC Offset Response:</div>
                <pre><?= htmlspecialchars($utc_result) ?></pre>
            </div>
        <?php endif; ?>

        <?php if (!empty($esp_ip) && $bitmap_window): ?>
            <div class="mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="font-semibold text-blue-700 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        Current Bitmap Animation Window
                    </div>
                    <pre class="text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($bitmap_window) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($esp_ip) && $buzz_list): ?>
            <div class="mb-6">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <div class="font-semibold text-indigo-700 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Current Buzz Times
                    </div>
                    <pre class="text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($buzz_list) ?></pre>
                </div>
            </div>
        <?php elseif (empty($esp_ip)): ?>
            <div class="mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-700 text-sm text-center">
                    Enter your ESP IP address to see current buzz times.
                </div>
            </div>
        <?php endif; ?>

        <hr class="my-8 border-t-2 border-gray-200">

        <form method="POST" class="space-y-6" id="apiForm">
            <input type="hidden" name="esp_ip" value="<?= htmlspecialchars($esp_ip) ?>">
            <div>
                <label class="block text-gray-700 font-semibold mb-1">API Command</label>
                <select name="command" id="commandSelect" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <?php foreach ($api_commands as $i => $cmd): ?>
                        <option value="<?= $i ?>" <?= (isset($_POST['command']) && $_POST['command'] == $i) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cmd['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="paramsContainer"></div>
            <input type="hidden" name="endpoint" id="endpointInput" value="">
            <button type="submit" class="w-full bg-indigo-600 text-white font-semibold py-2 rounded hover:bg-indigo-700 transition">Send API Request</button>
        </form>

        <?php if ($result): ?>
            <div class="mt-6 p-4 bg-gray-100 rounded border border-gray-200">
                <div class="font-semibold text-gray-700 mb-1">API Response:</div>
                <pre class="text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($result) ?></pre>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-gray-500 text-xs text-center">
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
                label.className = 'block text-gray-700 font-semibold mb-1';
                label.textContent = param.label;

                if ((cmd.label === "Remove a buzz time" && key === "remove") ||
                    (cmd.label === "Change song for buzz time" && key === "set")) {
                    const select = document.createElement('select');
                    select.name = `params[${key}]`;
                    select.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 mb-4';
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
                    select.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 mb-4';
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
                    input.className = 'w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-indigo-400 mb-4';
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
            const ip = espIpForm.querySelector('input[name="esp_ip"]').value;
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

        // WiFi SweetAlert2 Edit Logic with password toggle and reboot message
        const wifiSsid = document.getElementById('wifiSsid');
        const editWifiBtn = document.getElementById('editWifiBtn');
        editWifiBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Change Wi-Fi',
                html:
                    '<input id="swal-ssid" class="swal2-input" placeholder="SSID" value="' + wifiSsid.textContent + '">' +
                    '<div style="position:relative;display:flex;align-items:center;">' +
                      '<input id="swal-pass" type="password" class="swal2-input" placeholder="Password" style="width:100%;">' +
                      '<button type="button" id="swal-toggle-pass" tabindex="-1" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;">' +
                        '<svg id="swal-toggle-pass-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path id="swal-toggle-eye-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>' +
                      '</button>' +
                    '</div>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Save',
                didOpen: () => {
                    const passInput = document.getElementById('swal-pass');
                    const toggleBtn = document.getElementById('swal-toggle-pass');
                    const eyeIcon = document.getElementById('swal-toggle-pass-icon');
                    let visible = false;
                    toggleBtn.addEventListener('click', function () {
                        visible = !visible;
                        passInput.type = visible ? 'text' : 'password';
                        // Switch icon between open/closed eye
                        if (visible) {
                            eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z M2 2l20 20"/>';
                        } else {
                            eyeIcon.innerHTML = '<path id="swal-toggle-eye-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" />';
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
                                title: 'Wi-Fi settings changed!',
                                html: 'The Wi-Fi will update <b>after reboot</b>.<br>Current Wi-Fi still: <b>' + data.ssid + '</b>',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire('Failed', data.msg || 'Could not update Wi-Fi', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Failed', 'Could not update Wi-Fi', 'error');
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
    </script>
</body>
</html>