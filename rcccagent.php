#!/usr/bin/php
<?php
declare(ticks=1);

pcntl_signal(SIGTERM, function() {
    file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] Shutting down...\n", FILE_APPEND);
    exit;
});

while (true) {
	$proceed = true;

	file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] Checking certificates...\n", FILE_APPEND);

	if ($proceed) {
		try {
			$output = shell_exec('sudo certbot certificates');
		} catch (\Exception $e) {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not retrieve certbot output - ".$e->getMessage()."\n", FILE_APPEND);
			$proceed = false;
		}
	}

	if ($proceed && isset($output)) {
		try {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] Parsing certificates...\n", FILE_APPEND);

			// Match each certificate block starting from "Certificate Name:"
			preg_match_all('/Certificate Name: (.*?)\n(.*?)(?=Certificate Name:|\z)/s', $output, $matches, PREG_SET_ORDER);

			$certs = [];

			foreach ($matches as $match) {
    				$name = trim($match[1]);
    				$block = $match[2];

    				// Match Domains line
    				preg_match('/Domains:\s+(.*?)\n/', $block, $domainsMatch);
    				$domains = isset($domainsMatch[1]) ? preg_split('/\s+/', trim($domainsMatch[1])) : [];

    				// Match Expiry Date line
    				preg_match('/Expiry Date:\s+(.*?)\s+\(/', $block, $expiryMatch);
    				$expiry = isset($expiryMatch[1]) ? new DateTime(trim($expiryMatch[1])) : null;

    				$certs[] = [
        				'name' => $name,
        				'domains' => $domains,
        				'expiry' => $expiry,
    				];
			}

		} catch (\Exception $e) {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not parse certbot output - ".$e->getMessage()."\n", FILE_APPEND);
                        $proceed = false;
		}
	}

	if ($proceed && isset($certs)) {
		try {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] Sending certificates data:\n", FILE_APPEND);

			$data = ['hostname' => gethostname(), 'certs' => $certs];

			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ".json_encode($data)."\n", FILE_APPEND);

			file_put_contents("/var/log/rcccagent.log", json_encode($data), FILE_APPEND);

			$ch = curl_init('https://hosting.razorcreations.com/agent');
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);
		} catch (\Exception $e) {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not submit certificate data - ".$e->getMessage()."\n", FILE_APPEND);
			$proceed = false;
		}
	}

	sleep(60 * 60 * 12); // Every 12 hours
}
