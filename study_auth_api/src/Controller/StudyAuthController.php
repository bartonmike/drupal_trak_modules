<?php

namespace Drupal\study_auth_api\Controller;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

class StudyAuthController extends ControllerBase {

  public function checkSession(Request $request) {

    $data = json_decode($request->getContent(), TRUE);

    $session_id = $data['session_id'] ?? NULL;
    $study_id = $data['study_id'] ?? NULL;

    $hashed_sid = Crypt::hashBase64($session_id);

    if (!$session_id) {
      return new JsonResponse([
        'valid_session' => FALSE,
        'message' => 'Missing session_id'
      ], 400);
    }

    $connection = Database::getConnection();

    $session = $connection->select('sessions', 's')
      ->fields('s', ['uid', 'timestamp'])
      ->condition('sid', $hashed_sid)
      ->execute()
      ->fetchAssoc();

    if (!$session) {
      return new JsonResponse([
	      'valid_session' => FALSE,
	      'reason' => 'invalid session',
#	      'session_id' => $hashed_sid,
      ]);
    }

    // Drupal session lifetime check
    $max_age = ini_get('session.gc_maxlifetime');

    if (time() - $session['timestamp'] > $max_age) {
      return new JsonResponse([
	      'valid_session' => FALSE,
	      'reason' => 'session_expired',
      ]);
    }
	if($study_id == '42'){
                return new JsonResponse([
                        'valid_session' => TRUE,
                        'uid' => $session['uid'],
                        'study_id' => $study_id,
                        'study_name' => 'Wristband Study',
                        'study_files' => [
                        'file_key' => '',
                                        #sha1(rand())
                                        'files' => [
                                                        'study_data/42/wristbands.csv'
                                        ],
                        ],
                        'uploaded_comp_data' => [
                                        'files' => [
                                                        'study_data/42/comparison_data/comparison_study.csv'
                                        ],
                        ],
                        'selected_shared_data' => [
                                        'files' => [
                                                        'shared_data/shared_wristbands1.csv',
                                                        'shared_data/shared_wristbands2.csv'
                                        ],
                        ],
                ]);
        }
	//If the study doesn't exist, throw an error
	else {
		return new JsonResponse([
			'valid_session' => TRUE,
			'uid' => $session['uid'],
			'study_id' => $study_id,
			'study_name' => 'Error: Study not found'
		]);

}
  }

}
