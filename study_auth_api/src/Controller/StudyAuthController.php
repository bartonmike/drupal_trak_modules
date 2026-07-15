<?php

namespace Drupal\study_auth_api\Controller;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;

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

    if (!$study_id) {
      return new JsonResponse([
        'valid_session' => FALSE,
        'message' => 'Missing study_id'
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
	      # 'session_id' => $hashed_sid,
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
    $study_storage = $this->entityTypeManager()->getStorage('study');
    $study = $study_storage->load($study_id);

    if (!$study) {
      return new JsonResponse([
        'valid_session' => TRUE,
        'uid' => $session['uid'],
        'study_id' => $study_id,
        'study_name' => 'Error: Study not found',
      ], 404);
    }

    $account = User::load($session['uid']);

    if (!$account || !$study->access('view', $account)) {
      return new JsonResponse([
        'valid_session' => TRUE,
        'uid' => $session['uid'],
        'study_id' => $study_id,
        'study_name' => 'Error: Access denied',
      ], 403);
    }

    $file_uris = function (string $field_name) use ($study) {
      $uris = [];
      foreach ($study->get($field_name)->referencedEntities() as $file) {
        $uris[] = $file->getFileUri();
      }
      return $uris;
    };

    return new JsonResponse([
      'valid_session' => TRUE,
      'uid' => $session['uid'],
      'study_id' => $study_id,
      'study_name' => $study->getTitle(),
      'status' => $study->get('status')->value,
      // File Uploads: private to this study.
      'study_files' => [
        'file_key' => '',
        'files' => $file_uris('files'),
      ],
      'private_comparison_data' => [
        'files' => $file_uris('private_comparison_files'),
      ],
      'private_reference_materials' => [
        'files' => $file_uris('private_reference_files'),
      ],
      // File Selection: picked from the shared library.
      'selected_comparison_data' => [
        'files' => $file_uris('selected_comparison_files'),
      ],
      'selected_reference_materials' => [
        'files' => $file_uris('selected_reference_files'),
      ],
    ]);
  }

}
