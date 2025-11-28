<?php
$missing = false;
foreach (['GH_TOKEN', 'GT_TOKEN', 'GH_USER', 'GT_USER', 'GT_HOST'] as $env_key) {
	if (array_key_exists($env_key, $_ENV)) {
		define($env_key, $_ENV[$env_key]);
	} else {
		printf("Missing environemnt variable: %s \n", $env_key);
		$missing = true;
	}
}

if ($missing) 
	exit(1);

define('USER_AGENT', sprintf('Gitea Pusher (User: %s)', $_ENV['GITHUB_USER']));

function gh_exists($name) {
	$ch = curl_init(sprintf('https://api.github.com/repos/%s/%s', GH_USER, $name));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Accept: application/vnd.github+json",
		'Authorization: Bearer ' . GH_TOKEN,
		'User-Agent: ' . USER_AGENT,
	]);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$data = json_decode(curl_exec($ch), true);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return isset($data['id']);
}

function create_repo($name) {
	$ch = curl_init('https://api.github.com/user/repos');
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Accept: application/vnd.github+json",
		'Authorization: Bearer ' . GH_TOKEN,
		'User-Agent: ' . USER_AGENT,
	]);

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
		'name' => $name
	]));

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	json_decode(curl_exec($ch), true);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);


	if ($status == 200) {
		return true;
	} else {
		printf("Returned %s \n", $status);
		return false;
	}
}

function has_github_mirror($name) {
	$ch = curl_init(sprintf('https://%s/api/v1/repos/%s/%s/push_mirrors?access_token=%s', GT_HOST, GT_USER, $name, GT_TOKEN));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'User-Agent: ' . USER_AGENT,
	]);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$data = json_decode(curl_exec($ch), true);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($status === 200 && $data) {
		foreach ($data as $mirror) {
			if (strpos($mirror['remote_address'], 'github.com') !== false) {
				return true;
			}
		}
	}

	return false;
}

function create_push_mirror($name) {
	$ch = curl_init(sprintf('https://%s/api/v1/repos/%s/%s/push_mirrors?access_token=%s', GT_HOST, GT_USER, $name, GT_TOKEN));
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'User-Agent: ' . USER_AGENT,
		'content-type: application/json',
	]);

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
		'interval' => '96h0m0s',
		'remote_address' => sprintf('https://github.com/%s/%s.git', GH_USER, $name),
		'remote_password' => GH_TOKEN,
		'remote_username' => GH_USER,
		'sync_on_commit' => true,
	]));

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$data = json_decode(curl_exec($ch), true);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);


	if ($status == 200) {
		$ch = curl_init(sprintf('https://%s/api/v1/repos/%s/%s/push_mirrors-sync?access_token=%s', GT_HOST, GT_USER, $name, GT_TOKEN));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'User-Agent: ' . USER_AGENT,
			'content-type: application/json',
		]);

		curl_setopt($ch, CURLOPT_POST, true);
		curl_exec($ch);
		curl_close($ch);

		return true;
	} else {
		printf("Returned %s \n", $status);
		return false;
	}
}

function set_description($name, $description) {
	$ch = curl_init('https://api.github.com/repos/' . GH_USER . '/' . $name);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Accept: application/vnd.github+json",
		'Authorization: Bearer ' . GH_TOKEN,
		'User-Agent: ' . USER_AGENT,
	]);

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
		'description' => $description
	]));

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	json_decode(curl_exec($ch), true);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);


	if ($status == 200) {
		return true;
	} else {
		printf("Returned %s \n", $status);
		return false;
	}
}

$page = 1;
$query = [
	'limit' => 5,
	'page' => $page,
	'access_token' => GT_TOKEN
];

$gitea = json_decode(file_get_contents('https://' . GT_HOST . '/api/v1/repos/search?' . http_build_query($query)), true);
while ($gitea['ok'] && count($gitea['data'])) {
	foreach ($gitea['data'] as $repo) {
		if (strtolower($repo['owner']['login']) === strtolower(GT_USER) && $repo['private'] === false) {
			if (gh_exists($repo['name'])) {
				printf("%s exists! \n", $repo['name']);
			} else {
				printf("%s doesn't exist, creating! \n", $repo['name']);
				create_repo( $repo['name']);
			}

			if (!has_github_mirror($repo['name'])) {
				printf("%s is missing push mirror! \n", $repo['name']);
				if (create_push_mirror($repo['name'])) {
					printf("Created push mirror \n");
				}
			}

			if (trim($repo['description'])) {
				printf("Setting %s description! \n", $repo['name']);
				set_description($repo['name'], $repo['description']);
			}

			sleep(2);
		}
	}

	$query['page']++;
	$gitea = json_decode(file_get_contents('https://' . GT_HOST . '/api/v1/repos/search?' . http_build_query($query)), true);
}
