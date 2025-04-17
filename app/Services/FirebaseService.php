<?php
namespace App\Services;

use GuzzleHttp\Client;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseService
{
  const FCM_URL = 'https://fcm.googleapis.com/v1/projects/wisesocial-29487/messages:send';
  private $client;
  private $firebaseToken;
  private $lastError;

  /**
   * Initialize FirebaseService with Guzzle client and Firebase token.
   */
  public function __construct()
  {
    // Init Guzzle client for HTTP requests
    $this->client = new Client();

    // Define scope for Firebase token
    $scope = [
      'https://www.googleapis.com/auth/firebase.messaging'
    ];

    // Load service account credentials from file
    $pathToServiceAccount = storage_path('firebase_credential.json');
    $credentials = new ServiceAccountCredentials($scope, $pathToServiceAccount);

    // Fetch and store Firebase token
    $credentials->fetchAuthToken();
    $this->firebaseToken = $credentials->getLastReceivedToken()["access_token"];
  }

  /**
   * Function  service send nofitication to device
   *
   * @param string $content message
   * @param string $deviceToken of device
   * @return void | mixed
   */
  public function sendFCM($content, $deviceToken)
  {
    try {
      // Send POST request to FCM with notification data
      $response = $this->client->request('POST', self::FCM_URL, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->firebaseToken,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'message' => [
            'token' => $deviceToken,
            'notification' => [
              'title' => 'Wise Social',
              'body' => $content,
            ],
          ],
        ],
      ]);
      return true;
    } catch (\Exception $e) {
      $this->lastError = $e->getMessage();
      return false;
    }
  }

  public function getLastError()
  {
    return $this->lastError;
  }
}
