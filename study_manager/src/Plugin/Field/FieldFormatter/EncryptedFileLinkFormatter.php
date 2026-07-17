<?php

namespace Drupal\study_manager\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;

/**
 * Renders study file fields as links to the decrypting download route.
 *
 * The default 'file_default' formatter links straight at the private://
 * URI, which study_manager_file_download() now denies for encrypted fields
 * (Study::ENCRYPTED_FILE_FIELDS). This formatter links to
 * study_manager.file_download instead, which decrypts before streaming.
 *
 * @FieldFormatter(
 *   id = "study_manager_encrypted_file_link",
 *   label = @Translation("Encrypted file download link"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class EncryptedFileLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $study = $items->getEntity();
    $field_name = $items->getFieldDefinition()->getName();

    foreach ($items as $delta => $item) {
      $file = $item->entity;
      if (!$file) {
        continue;
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $file->getFilename(),
        '#url' => Url::fromRoute('study_manager.file_download', [
          'study' => $study->id(),
          'field_name' => $field_name,
          'file' => $file->id(),
        ]),
      ];
    }

    return $elements;
  }

}
