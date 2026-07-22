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

    // Study-owned private uploads are physically stored at
    // "private://{config file_path}/{study_id}/...". The API exposes them
    // as relative paths rooted at a fixed "study_data" prefix regardless of
    // that config, e.g. "study_data/42/comparison_data/beyond_toxics.csv".
    $config_file_path = $this->config('study_manager.settings')->get('file_path') ?: 'studies';
    $private_prefix = 'private://' . $config_file_path . '/';

    $private_paths = function (string $field_name) use ($study, $private_prefix) {
      $paths = [];
      foreach ($study->get($field_name)->referencedEntities() as $file) {
        $uri = $file->getFileUri();
        if (strpos($uri, $private_prefix) === 0) {
          $paths[] = '' . substr($uri, strlen($private_prefix));
        }
        else {
          // Fallback for a URI that doesn't match the expected prefix
          // (e.g. legacy content not yet repaired by update_8005).
          $paths[] = 'study_data/' . $study->id() . '/' . $file->getFilename();
        }
      }
      return $paths;
    };

    // Shared-library selections are physically stored at
    // "private://study_manager/shared_library/{category}/...". The API
    // exposes them flattened under a fixed "shared_data" prefix.
    $shared_paths = function (string $field_name) use ($study) {
      $paths = [];
      foreach ($study->get($field_name)->referencedEntities() as $file) {
        $paths[] = 'shared_data/' . $file->getFilename();
      }
      return $paths;
    };

    // Study Data and Private Comparison Data are encrypted at rest with a
    // per-study key (see Study::postSave()). Ensure the key is persisted,
    // then hand it to the caller so it can decrypt the files itself.
    if (empty($study->get('encryption_key')->value)) {
      $study->save();
    }
    $file_key = base64_encode($study->getRawEncryptionKey());

    return new JsonResponse([
      'valid_session' => TRUE,
      'uid' => $session['uid'],
      'study_id' => $study_id,
      'study_name' => $study->getTitle(),
      'status' => $study->get('status')->value,
      // File Uploads: private to this study.
      'study_files' => [
        'file_key' => $file_key,
        'files' => $private_paths('files'),
      ],
      'uploaded_comp_data' => [
        'file_key' => $file_key,
        'files' => $private_paths('private_comparison_files'),
      ],
      'private_reference_materials' => [
        'files' => $private_paths('private_reference_files'),
      ],
      // File Selection: picked from the shared library.
      'selected_comparison_data' => [
        'files' => $shared_paths('selected_comparison_files'),
      ],
      'selected_reference_materials' => [
        'files' => $shared_paths('selected_reference_files'),
      ],
    ]);
  }

}
