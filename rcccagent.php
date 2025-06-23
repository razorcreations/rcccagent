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

	try {
		$config = json_decode(file_get_contents('/opt/rcccagent/config.json'));
	} catch (\Exception $e) {
		file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not capture config - ".$e->getMessage()."\n", FILE_APPEND);
		$proceed = false;
	}

	if ($proceed && !is_object($config) && !isset($config->hostname)) {
		file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not capture config\n", FILE_APPEND);
                $proceed = false;
	}

	if ($proceed) {
		$config = (array) $config;
	
		if (!isset($config['hostname']) || trim($config['hostname']) === '') {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Hostname is empty or missing\n", FILE_APPEND);
       		         $proceed = false;
		}
	
		if ($proceed && !isset($config['token']) || trim($config['token']) === '') {
	                file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Token is invalid or missing\n", FILE_APPEND);
	                $proceed = false;
		}

		try {
			if ($proceed && $hostname = parse_url($config['hostname'], PHP_URL_HOST)) {
				if ($hostname !== 'hosting.razorcreations.com') {
					file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Invalid API hostname: ".$hostname."\n", FILE_APPEND);
	                        	$proceed = false;
				}
			} else {
				file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not parse hostname URL: ".$config['hostname']."\n", FILE_APPEND);
	                	$proceed = false;
			}
		} catch (\Exception $e) {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Failed to check API hostname - ".$e->getMessage()."\n", FILE_APPEND);
	                $proceed = false;
		}

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
		} elseif (!isset($output) || trim($output) === '') {
			file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not parse certbot output\n", FILE_APPEND);
	                $proceed = false;
		}

		if ($proceed && isset($certs)) {
			try {
				file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] Sending ".count($certs)." certificate(s)...\n", FILE_APPEND);
	
				$ch = curl_init($config['hostname']);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($certs));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-Type: application/json',
					'x-rcccagent-hostname: '.gethostname(),
					'x-rcccagent-token: '.$config['token']
				]);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
				$response = curl_exec($ch);
				curl_close($ch);

				file_put_contents("/var/log/rcccagent.log", "API Response: ".$response."\n", FILE_APPEND);
			} catch (\Exception $e) {
				file_put_contents("/var/log/rcccagent.log", "[".date('Y-m-d H:i:s')."] ERROR: Could not submit certificate data - ".$e->getMessage()."\n", FILE_APPEND);
				$proceed = false;
			}
		}
	}

	sleep(60 * 60 * 12); // Every 12 hours
}
