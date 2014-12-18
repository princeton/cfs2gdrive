<?php

// This script will upload all files in a local source directory to a target directory in
// Google Drive.
//
// Author:  Mark Ratliff, ratliff@princeton.edu

// Load the Google API libraries
require 'vendor/autoload.php';
require 'cronHelper/cron.helper.php';

require 'config.php';

// Obtain a lock to ensure that only on instance of this program runs simultaneously
if(($pid = cronHelper::lock()) !== FALSE)
{

// Set the timezone
date_default_timezone_set("America/New_York");

$logger = new Katzgrau\KLogger\Logger($logDir, Psr\Log\LogLevel::DEBUG);

$logger->debug('Executing upload2gdrive ...');

// Get only the files (not directories) from the directory listing
if (! file_exists($sourceDir))
{
   $error_msg = "Source directory does not exist: " . $sourceDir;
   $logger->error($error_msg);
   die($error_msg);
}
$filesToUpload = array();
$files = scandir($sourceDir);
foreach($files as $file)
{
    if( is_file($sourceDir . $file) )
    {
      array_push($filesToUpload, $file); 
    }
}
// List files
//var_dump($filesToUpload);

// If there are no files to upload, then exit
if (sizeof($filesToUpload) == 0)
{
    $logger->debug("No files to upload!");
    exit;
}


// Load Google tokens from .json file
$googleTokens = file_get_contents($tokensFilename);

// If there were problems reading the tokens file, then die
if ($googleTokens === false) {
    $error_msg = "Trouble reading Google autnentication tokens from " . $tokensFilename . "\nExiting!";
    $logger->error($error_msg);
    die($error_msg);
}

$client = new Google_Client();
$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$client->setScopes(array('https://www.googleapis.com/auth/drive'));
$client->setAccessType('offline');

$service = new Google_Service_Drive($client);

// If we don't have any tokens yet (i.e. this program being run for the first time)
//  then send the user to Google and ask them to enter the resulting auth code.
if (empty($googleTokens)) 
{

  $authUrl = $client->createAuthUrl();

  //Request authorization
  print "Please visit:\n$authUrl\n\n";
  print "Please enter the auth code:\n";
  $authCode = trim(fgets(STDIN));

  // Exchange authorization code for access token
  $accessToken = $client->authenticate($authCode);

  // Print the access token so that the refresh token can be copied and set above.
  print "The access token is:\n";
  print $accessToken;

  $client->setAccessToken($accessToken);
}

// If we already have a tokens then check to see if they need refreshed
else
{
  $client->setAccessToken($googleTokens);

  // If the access token has expired, then refresh
  if ($client->isAccessTokenExpired())
  {
    $decodedGoogleTokens = json_decode($googleTokens);
    $client->refreshToken($decodedGoogleTokens->refresh_token);
    $newToken=$client->getAccessToken();
    $client->setAccessToken($newToken);

    file_put_contents($tokensFilename, $newToken);

    $logger->info("Expired access token refreshed.");
  }
}

//Upload the files
foreach ($filesToUpload as $fileToUpload)
{
  $logger->info("Uploading file: " . $fileToUpload);

  try
  {
    $file = new Google_Service_Drive_DriveFile();
    $file->setTitle($fileToUpload);
    //$file->setDescription($fileDesc);
    $file->setMimeType($fileMimeType);

    // If a target Google folder was defined, then set it as the parent.
    if (! empty($googleFolderId))
    {
      $parent = new Google_Service_Drive_ParentReference();
      $parent->setId($googleFolderId);
      $file->setParents(array($parent));
    }

    // Read the source file
    $data = file_get_contents($sourceDir . $fileToUpload);

    // Create the target file
    $createdFile = $service->files->insert($file, array(
        'data' => $data,
        'uploadType' => 'media',
      ));
    // Move the source file to a "successfully_uploaded_todaysdate" folder
    $uploadedFolder = $sourceDir . "successfully_uploaded_" . date("Ymd");
    if (! file_exists($uploadedFolder))
    {
      mkdir($uploadedFolder);
    }

    rename($sourceDir . $fileToUpload, $uploadedFolder . "/" . $fileToUpload);
   }
   catch(Exception $e)
   { 
     $logger->error("Problems uploading file: " . $e->getMessage());
   }
}

  // Remove the lock file
  cronHelper::unlock();
}
else
{
  $logger->info("Previous job appears to be running.  Exiting ...");
}

?>
