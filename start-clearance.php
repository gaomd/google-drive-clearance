<?php

/**
 * Copyright (c) 2016 Mengdi Gao
 * Based on the code at https://developers.google.com/drive/v3/web/quickstart/php
 * Licensed under the Apache License 2.0, http://www.apache.org/licenses/LICENSE-2.0
 *
 * 1. Follow the Step 1: Turn on the Drive API on https://developers.google.com/drive/v3/web/quickstart/php#prerequisites
 * 2. Place the downloaded `client_secret.json` to the credentials directory.
 * 3. php start-clearance.php
 * 4. Manually empty the trash on the Google Drive Web
 */
require __DIR__ . '/vendor/autoload.php';

define('APPLICATION_NAME', 'Google Drive Clearance');
define('CREDENTIALS_PATH', __DIR__ . '/credentials/delete-me-after-clearance.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/credentials/client_secret.json');

// If modifying these scopes, delete your previously saved credentials
// at CREDENTIALS_PATH
define('SCOPES', implode(' ', [
        Google_Service_Drive::DRIVE,
        Google_Service_Drive::DRIVE_FILE,
        Google_Service_Drive::DRIVE_APPDATA,
    ]
));

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = CREDENTIALS_PATH;
    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->authenticate($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, $accessToken);
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->refreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, $client->getAccessToken());
    }

    return $client;
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

$optParams = [
    'q'      => "'me' in owners and trashed = false",
    'fields' => "nextPageToken, nextLink, items(id, title)",
];

while (true) {
    $results = $service->files->listFiles($optParams);

    if (count($results->getItems()) === 0) {
        echo "No files found.\n";
        break;
    }

    echo "Delete files in a batch:\n";
    foreach ($results->getItems() as $file) {
        /** @var $file Google_Service_Drive_DriveFile */
        printf("Deleting %s (%s)...", $file->getTitle(), $file->getId());

        try {
            $response = $service->files->delete($file->getId());
            if ($response === null) {
                printf("...Deleted.\n");
            }
        } catch (Google_Service_Exception $e) {
            printf("...Failed: {$e->getMessage()}\n");
        }
    }
}
