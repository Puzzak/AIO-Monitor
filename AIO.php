<?php
	header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header("Access-Control-Allow-Headers: X-Requested-With");
    header('Content-Type: text/html; charset=utf-8');
    function getNetworkStatistics() {
        $netDevFile = '/proc/net/dev';
        $netDevContents = file_get_contents($netDevFile);
        $lines = explode("\n", $netDevContents);
        $networkData = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                $interface = rtrim($parts[0], ':');
                $receivedBytes = $parts[1];
                $transmittedBytes = $parts[9];

                $networkData[$interface] = [
                    'receivedBytes' => $receivedBytes,
                    'transmittedBytes' => $transmittedBytes
                ];
            }
        }

        return $networkData;
    }

    function getCurrentNetworkSpeed() {
        $interfaceName = 'eth0';
        $previousStatsFile = '/tmp/previous_stats.txt';
        $networkStatistics = getNetworkStatistics();

        if (isset($networkStatistics[$interfaceName])) {
            $currentStats = $networkStatistics[$interfaceName];
            $previousStats = [];
            if (file_exists($previousStatsFile)) {
                $previousStats = unserialize(file_get_contents($previousStatsFile));
            }
            $receivedDiff = $currentStats['receivedBytes'] - $previousStats['receivedBytes'];
            $transmittedDiff = $currentStats['transmittedBytes'] - $previousStats['transmittedBytes'];
            file_put_contents($previousStatsFile, serialize($currentStats));
            $requestInterval = 1;
            $receivedSpeed = $receivedDiff / $requestInterval;
            $transmittedSpeed = $transmittedDiff / $requestInterval;

            return [
                'receivedSpeed' => $receivedSpeed,
                'transmittedSpeed' => $transmittedSpeed
            ];
        } else {
            return false;
        }
    }
    function f($d, $n){
		$string = exec("grep '".$d."' /proc/meminfo");
		$numb = explode(":", $string);
		$outb = explode(" ", $numb[1]);
		return $outb[$n];
	}
    function getCpuUtilization() {
        $statFile = '/proc/stat';
        $cpuDataFile = '/tmp/cpu_data.txt';
        $statContents = file_get_contents($statFile);
        $lines = explode("\n", $statContents);
        $cpuLine = '';

        foreach ($lines as $line) {
            if (strpos($line, 'cpu ') === 0) {
                $cpuLine = $line;
                break;
            }
        }
        $cpuData = sscanf($cpuLine, 'cpu %d %d %d %d %d %d %d %d %d %d');
        $totalCpuTime = array_sum($cpuData);
        $previousCpuData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        if (file_exists($cpuDataFile)) {
            $previousCpuData = file($cpuDataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        $cpuTimeDiff = array_map(function ($current, $previous) {
            return $current - $previous;
        }, $cpuData, $previousCpuData);
        $totalCpuTimeDiff = array_sum($cpuTimeDiff);
        $idleCpuTimeDiff = $cpuTimeDiff[3];
        $cpuUtilization = 100 - (($idleCpuTimeDiff / $totalCpuTimeDiff) * 100);
        file_put_contents($cpuDataFile, implode(PHP_EOL, $cpuData));
        return $cpuUtilization;
    }

    $networkSpeeds = getCurrentNetworkSpeed();
    $stats["netspd"]["in"] = $networkSpeeds['receivedSpeed'];
    $stats["netspd"]["out"] = $networkSpeeds['transmittedSpeed'];
    $stats["time"] = microtime(true);
	$stats["temp"] = exec("cat /sys/class/thermal/thermal_zone*/temp") / 1000;
	// $stats["util"] = exec("mpstat 0,5 1 | awk -F \" \" '{print (100 - $12)}'");
    $stats["util"] = getCpuUtilization();
	$stats["memo"]["total"] = f("MemTotal", 8);
    $stats["memo"]["avail"] = f("MemAvailable", 4);
    $stats["uptime"] = strtotime(exec('uptime -s'));
	echo json_encode($stats);
?>