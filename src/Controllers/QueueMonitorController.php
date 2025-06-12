<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QueueMonitorController extends Controller
{
    private function getWorkerName()
    {
        // Read directly from .env file like bash script does
        $envFile = base_path('.env');

        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);

            // Match the bash script logic: grep "^APP_NAME=" .env
            if (preg_match('/^APP_NAME=(.*)$/m', $envContent, $matches)) {
                $appName = trim($matches[1], '"\'');

                if (!empty($appName)) {
                    // Match bash transformation exactly:
                    // tr '[:upper:]' '[:lower:]' | tr -d ' ' | sed 's/[^a-z0-9-]//g' | sed 's/--*/-/g' | sed 's/^-\|-$//g'
                    $workerName = strtolower($appName);           // lowercase
                    $workerName = str_replace(' ', '', $workerName); // remove spaces
                    $workerName = preg_replace('/[^a-z0-9-]/', '', $workerName); // keep only alphanumeric and dash
                    $workerName = preg_replace('/--+/', '-', $workerName);       // compress multiple dashes
                    $workerName = trim($workerName, '-');                        // remove leading/trailing dashes

                    return $workerName;
                }
            }
        }

        // Fallback
        return 'laravel-worker';
    }

    private function getQueueWorkers()
    {
        $output = shell_exec('ps aux | grep "artisan.*queue:work" | grep -v grep');
        return array_filter(explode("\n", $output ?: ''));
    }

    private function getScreenSessions()
    {
        $workerName = $this->getWorkerName();

        // Get all screen sessions
        $output = shell_exec("screen -ls 2>/dev/null");

        if (empty($output)) {
            return [];
        }

        // Parse screen sessions - look for worker name pattern
        $lines = explode("\n", $output);
        $sessions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, $workerName) !== false && strpos($line, '.') !== false) {
                $sessions[] = $line;
            }
        }

        return $sessions;
    }

    private function getWorkerLogs()
    {
        $workerName = $this->getWorkerName();
        $homeDir = getenv('HOME') ?: '/var/lib/nginx';
        $logFile = "{$homeDir}/{$workerName}-worker.log";

        if (file_exists($logFile)) {
            // Get logs and reverse order (newest first)
            $output = shell_exec("tac '$logFile' 2>/dev/null | head -50");
            if (empty($output)) {
                // Fallback if tac not available
                $output = shell_exec("tail -50 '$logFile' 2>/dev/null");
                $lines = array_reverse(explode("\n", $output ?: ''));
                $output = implode("\n", $lines);
            }
            // Basic sanitization
            return preg_replace('/\/[\/\w\-\.]+\//', '/***/', $output ?: '');
        }

        return "Log file not found: {$logFile}";
    }

    private function getSystemInfo()
    {
        // Get CPU usage
        $cpuUsage = 0;
        $loadAvg = shell_exec('cat /proc/loadavg 2>/dev/null');
        if ($loadAvg) {
            $load = explode(' ', trim($loadAvg));
            $cpuUsage = round($load[0] ?? 0, 1);
        }

        // Get memory usage
        $memUsage = 0;
        $memInfo = shell_exec('cat /proc/meminfo 2>/dev/null');
        if ($memInfo) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);

            if (!empty($total[1]) && !empty($available[1])) {
                $totalMem = $total[1];
                $availableMem = $available[1];
                $usedMem = $totalMem - $availableMem;
                $memUsage = round(($usedMem / $totalMem) * 100, 1);
            }
        }

        return [
            'cpu_load' => $cpuUsage,
            'memory_usage' => $memUsage
        ];
    }

    private function getDebugInfo()
    {
        $workerName = $this->getWorkerName();

        // Test APP_NAME extraction
        $envFile = base_path('.env');
        $appNameRaw = '';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (preg_match('/^APP_NAME=(.*)$/m', $envContent, $matches)) {
                $appNameRaw = trim($matches[1], '"\'');
            }
        }

        return [
            'app_name_raw' => $appNameRaw,
            'worker_name' => $workerName,
            'env_file_exists' => file_exists($envFile)
        ];
    }

    private function triggerRestart()
    {
        $projectRoot = base_path();
        $scriptPath = $projectRoot . '/jelastic-worker-start.sh';

        if (!file_exists($scriptPath)) {
            return ['success' => false, 'message' => 'Script not found'];
        }

        // Make executable
        chmod($scriptPath, 0755);

        // Execute script
        $outputFile = '/tmp/worker-restart-' . time() . '.log';
        $command = "cd {$projectRoot} && ./jelastic-worker-start.sh > {$outputFile} 2>&1";
        shell_exec($command);

        // Wait and read output
        sleep(3);
        $output = file_exists($outputFile) ? file_get_contents($outputFile) : '';
        if (file_exists($outputFile))
            unlink($outputFile);

        // Check if actually successful
        $success = !empty($this->getQueueWorkers());

        return [
            'success' => $success,
            'message' => $success ? 'Worker restarted successfully' : 'Restart attempted - check status',
            'output' => $output
        ];
    }

    public function index(Request $request)
    {
        $workerName = $this->getWorkerName();
        $workers = $this->getQueueWorkers();
        $sessions = $this->getScreenSessions();
        $logs = $this->getWorkerLogs();
        $systemInfo = $this->getSystemInfo();
        $debugInfo = $this->getDebugInfo();
        $isHealthy = !empty($workers) && !empty($sessions);

        $restartResult = null;

        // Manual restart
        if ($request->has('restart')) {
            $restartResult = $this->triggerRestart();
            // Refresh data after restart
            $workers = $this->getQueueWorkers();
            $sessions = $this->getScreenSessions();
            $logs = $this->getWorkerLogs();
            $systemInfo = $this->getSystemInfo();
            $isHealthy = !empty($workers) && !empty($sessions);
        }

        $data = [
            'workerName' => $workerName,
            'workersCount' => count($workers),
            'sessionsCount' => count($sessions),
            'logs' => $logs,
            'systemInfo' => $systemInfo,
            'debugInfo' => $debugInfo,
            'isHealthy' => $isHealthy,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'restartResult' => $restartResult,
            'appUrl' => config('app.url', request()->getSchemeAndHttpHost())
        ];

        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($data);
        }

        return response()->view('queue-monitor', $data);
    }

    public function status()
    {
        // Quick status API for AJAX refresh
        $workers = $this->getQueueWorkers();
        $sessions = $this->getScreenSessions();
        $systemInfo = $this->getSystemInfo();

        return response()->json([
            'workersCount' => count($workers),
            'sessionsCount' => count($sessions),
            'systemInfo' => $systemInfo,
            'isHealthy' => !empty($workers) && !empty($sessions),
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'logs' => $this->getWorkerLogs()
        ]);
    }

    public function periodicCheck()
    {
        $workerName = $this->getWorkerName();
        $workers = $this->getQueueWorkers();
        $sessions = $this->getScreenSessions();
        $isHealthy = !empty($workers) && !empty($sessions);

        $result = [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'worker_name' => $workerName,
            'is_healthy' => $isHealthy,
            'workers_count' => count($workers),
            'sessions_count' => count($sessions),
            'action_taken' => 'none'
        ];

        if (!$isHealthy) {
            // Attempt restart
            $restartResult = $this->triggerRestart();
            $result['action_taken'] = 'restart_attempted';
            $result['restart_success'] = $restartResult['success'];

            // Re-check after restart
            sleep(2);
            $workers = $this->getQueueWorkers();
            $sessions = $this->getScreenSessions();
            $result['post_restart_healthy'] = !empty($workers) && !empty($sessions);
        }

        // Simple logging
        $logEntry = json_encode($result) . "\n";
        file_put_contents(storage_path('logs/worker-monitor.log'), $logEntry, FILE_APPEND | LOCK_EX);

        return response()->json($result);
    }
}