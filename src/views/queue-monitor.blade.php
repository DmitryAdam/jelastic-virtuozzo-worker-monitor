<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-700 font-sans">
    <div class="min-h-screen p-4 max-w-2xl mx-auto">

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium text-gray-900">Queue Monitor</span>
                    <div class="text-xs text-gray-500">{{ $workerName }} • <span id="timestamp">{{ $timestamp }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="status-indicator" class="flex items-center space-x-1">
                        @if($isHealthy)
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-xs font-medium text-green-700">Online</span>
                        @else
                            <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                            <span class="text-xs font-medium text-red-700">Offline</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-lg border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">Workers</div>
                <div id="workers-count"
                    class="text-lg font-semibold {{ $workersCount > 0 ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $workersCount }}
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">Sessions</div>
                <div id="sessions-count"
                    class="text-lg font-semibold {{ $sessionsCount > 0 ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $sessionsCount }}
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-3">
                <div class="text-xs text-gray-500 mb-1">System</div>
                <div id="system-info" class="text-xs text-gray-600">
                    <div>CPU: <span class="font-medium">{{ $systemInfo['cpu_load'] }}</span></div>
                    <div>MEM: <span class="font-medium">{{ $systemInfo['memory_usage'] }}%</span></div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div id="controls-section">
            @if(!$isHealthy)
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                    <div
                        class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-3 sm:space-y-0">
                        <div>
                            <div class="text-sm font-medium text-gray-900 mb-1">Worker Offline</div>
                            <div class="text-xs text-gray-500">Auto-restart enabled via periodic check</div>
                        </div>
                        <a href="?restart=1" id="restart-btn"
                            class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors">
                            Manual Restart
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Restart Result -->
        @if($restartResult)
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4" id="restart-result">
                <div class="flex items-start space-x-3">
                    @if($restartResult['success'])
                        <div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <div class="w-2 h-2 bg-green-600 rounded-full"></div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-green-900">{{ $restartResult['message'] }}</div>
                        </div>
                    @else
                        <div class="w-5 h-5 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <div class="w-2 h-2 bg-yellow-600 rounded-full"></div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-yellow-900">{{ $restartResult['message'] }}</div>
                            @if(!empty($restartResult['output']))
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">View output</summary>
                                    <pre
                                        class="mt-2 text-xs bg-gray-50 p-2 rounded border overflow-x-auto">{{ $restartResult['output'] }}</pre>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Automation Info (Toggleable) -->
        <div class="bg-white rounded-lg border border-gray-200 mb-4">
            <button onclick="toggleAutomation()"
                class="w-full p-4 text-left flex items-center justify-between hover:bg-gray-50 transition-colors">
                <div class="text-sm font-medium text-gray-900">Automation Setup</div>
                <svg id="automation-arrow" class="w-4 h-4 text-gray-500 transform transition-transform" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="automation-content" class="hidden px-4 pb-4">
                <div class="space-y-2 text-xs text-gray-600">
                    <div>• Page auto-refreshes every 10 seconds via AJAX</div>
                    <div>• Setup cron job for automatic monitoring:</div>
                    <div class="bg-gray-50 p-2 rounded border font-mono text-xs overflow-x-auto">
                        * * * * * wget -q -O /dev/null {{ $appUrl }}/queue-monitor/periodic-check
                    </div>
                    <div class="text-xs text-gray-500">This endpoint automatically restarts workers when offline</div>
                </div>
            </div>
        </div>

        <!-- Worker Logs -->
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-medium text-gray-900">Worker Logs</div>
                <div class="text-xs text-gray-500">(newest first)</div>
            </div>

            <div class="bg-gray-50 rounded border overflow-hidden">
                <div class="max-h-64 overflow-y-auto">
                    <pre id="logs-content" class="text-xs text-gray-700 p-3 whitespace-pre-wrap">{{ $logs }}</pre>
                </div>
            </div>

            <div id="status-message" class="mt-3 p-2 rounded border">
                @if(!$isHealthy)
                    <div class="bg-yellow-50 border-yellow-200">
                        <div class="text-xs text-yellow-800">
                            <span class="font-medium">Status:</span> Worker offline - auto-restart will trigger if periodic
                            check is enabled
                        </div>
                    </div>
                @else
                    <div class="bg-green-50 border-green-200">
                        <div class="text-xs text-green-800">
                            <span class="font-medium">Status:</span> Worker healthy and running normally
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Debug Info (Collapsed) -->
        <div class="mt-4 bg-white rounded-lg border border-gray-200">
            <button onclick="toggleDebug()"
                class="w-full p-3 text-left flex items-center justify-between hover:bg-gray-50 transition-colors">
                <div class="text-xs font-medium text-gray-700">Debug Info</div>
                <svg id="debug-arrow" class="w-3 h-3 text-gray-500 transform transition-transform" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="debug-content" class="hidden px-3 pb-3">
                <div class="text-xs text-gray-600 space-y-1">
                    <div><span class="font-medium">App Name:</span> {{ $debugInfo['app_name_raw'] }}</div>
                    <div><span class="font-medium">Worker Name:</span> {{ $debugInfo['worker_name'] }}</div>
                    <div><span class="font-medium">ENV File:</span>
                        {{ $debugInfo['env_file_exists'] ? 'Found' : 'Missing' }}</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-xs text-gray-400">
            Refreshing in <span id="countdown">10</span>s
        </div>
    </div>

    <script>
        let countdownSeconds = 10;
        let countdownTimer;

        // AJAX refresh function
        async function refreshData() {
            try {
                const response = await fetch('/queue-monitor/status');
                const data = await response.json();

                // Update timestamp
                document.getElementById('timestamp').textContent = data.timestamp;

                // Update status indicator
                const statusIndicator = document.getElementById('status-indicator');
                if (data.isHealthy) {
                    statusIndicator.innerHTML = `
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span class="text-xs font-medium text-green-700">Online</span>
                    `;
                } else {
                    statusIndicator.innerHTML = `
                        <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-medium text-red-700">Offline</span>
                    `;
                }

                // Update counts
                const workersEl = document.getElementById('workers-count');
                workersEl.textContent = data.workersCount;
                workersEl.className = `text-lg font-semibold ${data.workersCount > 0 ? 'text-green-600' : 'text-gray-400'}`;

                const sessionsEl = document.getElementById('sessions-count');
                sessionsEl.textContent = data.sessionsCount;
                sessionsEl.className = `text-lg font-semibold ${data.sessionsCount > 0 ? 'text-green-600' : 'text-gray-400'}`;

                // Update system info
                document.getElementById('system-info').innerHTML = `
                    <div>CPU: <span class="font-medium">${data.systemInfo.cpu_load}</span></div>
                    <div>MEM: <span class="font-medium">${data.systemInfo.memory_usage}%</span></div>
                `;

                // Update logs
                document.getElementById('logs-content').textContent = data.logs;

                // Update status message
                const statusMessage = document.getElementById('status-message');
                if (data.isHealthy) {
                    statusMessage.innerHTML = `
                        <div class="bg-green-50 border-green-200">
                            <div class="text-xs text-green-800">
                                <span class="font-medium">Status:</span> Worker healthy and running normally
                            </div>
                        </div>
                    `;
                } else {
                    statusMessage.innerHTML = `
                        <div class="bg-yellow-50 border-yellow-200">
                            <div class="text-xs text-yellow-800">
                                <span class="font-medium">Status:</span> Worker offline - auto-restart will trigger if periodic check is enabled
                            </div>
                        </div>
                    `;
                }

                // Update controls section
                const controlsSection = document.getElementById('controls-section');
                if (!data.isHealthy) {
                    controlsSection.innerHTML = `
                        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-3 sm:space-y-0">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 mb-1">Worker Offline</div>
                                    <div class="text-xs text-gray-500">Auto-restart enabled via periodic check</div>
                                </div>
                                <a href="?restart=1" id="restart-btn"
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors">
                                    Manual Restart
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    controlsSection.innerHTML = '';
                }

            } catch (error) {
                console.log('Refresh failed:', error);
            }
        }

        // Countdown timer
        function startCountdown() {
            countdownSeconds = 10;
            countdownTimer = setInterval(() => {
                countdownSeconds--;
                const countdownEl = document.getElementById('countdown');
                if (countdownEl) countdownEl.textContent = countdownSeconds;

                if (countdownSeconds <= 0) {
                    clearInterval(countdownTimer);
                    refreshData();
                    startCountdown();
                }
            }, 1000);
        }

        // Toggle functions
        function toggleAutomation() {
            const content = document.getElementById('automation-content');
            const arrow = document.getElementById('automation-arrow');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        function toggleDebug() {
            const content = document.getElementById('debug-content');
            const arrow = document.getElementById('debug-arrow');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        // Start countdown on page load
        startCountdown();
    </script>
</body>

</html>