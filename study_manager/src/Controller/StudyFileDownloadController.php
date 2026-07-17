<?php

namespace Drupal\study_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Drupal\study_manager\Entity\Study;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams decrypted study files to authorized users.
 *
 * Study Data and Private Comparison Data are encrypted at rest (see
 * Study::postSave()); the default private:// download route is denied for
 * them (study_manager_file_download()) so this controller is the only way
 * to retrieve their plaintext contents.
 */
class StudyFileDownloadController extends ControllerBase {

  /**
   * Decrypts and streams $file, which must belong to $field_name on $study.
   */
  public function download(Study $study, string $field_name, FileInterface $file): Response {
    if (!in_array($field_name, Study::ENCRYPTED_FILE_FIELDS, TRUE)) {
      throw new NotFoundHttpException();
    }

    if (!$study->access('view', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }

    $referenced_ids = array_column($study->get($field_name)->getValue(), 'target_id');
    if (!in_array($file->id(), $referenced_ids, TRUE)) {
      throw new NotFoundHttpException();
    }

    $contents = file_get_contents($file->getFileUri());
    if ($contents === FALSE) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\study_manager\Service\StudyEncryptionService $service */
    $service = \Drupal::service('study_manager.encryption');
    if ($service->isEncrypted($contents)) {
      $contents = $service->decryptFileContents($study->getRawEncryptionKey(), $contents);
    }

    $response = new Response($contents);
    $response->headers->set('Content-Type', $file->getMimeType());
    $response->headers->set('Content-Length', (string) strlen($contents));
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $file->getFilename() . '"');
    return $response;
  }

}
