<?php

namespace Drupal\study_manager\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines the Study entity.
 *
 * @ContentEntityType(
 *   id = "study",
 *   label = @Translation("Study"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\study_manager\Entity\StudyListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\study_manager\Form\StudyForm",
 *       "add" = "Drupal\study_manager\Form\StudyForm",
 *       "edit" = "Drupal\study_manager\Form\StudyForm",
 *     },
 *     "access" = "Drupal\study_manager\StudyAccessControlHandler",
 *   },
 *   base_table = "study",
 *   admin_permission = "administer study manager",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "owner" = "user_id",
 *   },
 *   links = {
 *     "canonical" = "/study/{study}",
 *     "add-form" = "/study/add",
 *     "edit-form" = "/study/{study}/edit",
 *     "collection" = "/admin/content/studies",
 *   },
 * )
 */
class Study extends ContentEntityBase implements EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Names of the file fields whose uploads are encrypted at rest with the
   * study's own key. See study_manager.services.yml (study_manager.encryption).
   */
  const ENCRYPTED_FILE_FIELDS = ['files', 'private_comparison_files'];

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the study.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the study.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // --- File Uploads: files owned by and private to this study. ---
    $fields['files'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Study Data'))
      ->setDescription(t('Study data files (CSV, XLSX) for this study.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'studies/[study:id]')
      ->setSetting('file_extensions', 'csv xlsx')
      ->setSetting('uri_scheme', 'private')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'study_manager_encrypted_file_link',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['private_comparison_files'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Private Comparison Data'))
      ->setDescription(t('Comparison data files (CSV, XLSX) private to this study.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'studies/[study:id]/comparison_data')
      ->setSetting('file_extensions', 'csv xlsx')
      ->setSetting('uri_scheme', 'private')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'study_manager_encrypted_file_link',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['private_reference_files'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Private Reference Materials'))
      ->setDescription(t('Reference material files (PDF, PNG, JPG) private to this study.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'studies/[study:id]/reference_materials')
      ->setSetting('file_extensions', 'pdf png jpg')
      ->setSetting('uri_scheme', 'private')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // --- File Selection: existing files picked from the shared library. ---
    $fields['selected_comparison_files'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Comparison Data (Selected)'))
      ->setDescription(t('Comparison data files (CSV, XLSX) selected from the shared library.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'study_manager_shared_library')
      ->setSetting('handler_settings', ['category' => 'comparison_data'])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 8,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['selected_reference_files'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reference Materials (Selected)'))
      ->setDescription(t('Reference material files (PDF, PNG, JPG) selected from the shared library.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'study_manager_shared_library')
      ->setSetting('handler_settings', ['category' => 'reference_materials'])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => 9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the study.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
      ])
      ->setDefaultValue('active')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['encryption_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Encryption Key'))
      ->setDescription(t('Wrapped per-study key used to encrypt private study files. Never displayed.'))
      ->setSetting('max_length', 255)
      ->setSetting('text_processing', 0)
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the study was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the study was last edited.'));

    return $fields;
  }

  /**
   * Gets the study creation timestamp.
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Gets the study title.
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * Sets the study title.
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * Generates and sets a wrapped encryption key if this study doesn't have
   * one yet. Only mutates the in-memory entity; the caller is responsible
   * for saving it (preSave() below does this as part of the normal save
   * cycle).
   */
  protected function ensureEncryptionKey(): void {
    if (empty($this->get('encryption_key')->value)) {
      /** @var \Drupal\study_manager\Service\StudyEncryptionService $service */
      $service = \Drupal::service('study_manager.encryption');
      $raw = $service->generateStudyKey();
      $this->set('encryption_key', $service->wrapKey($raw));
    }
  }

  /**
   * Gets this study's raw (unwrapped) encryption key.
   *
   * The study must already have been saved at least once; call save() first
   * if $study->get('encryption_key')->value is empty.
   */
  public function getRawEncryptionKey(): string {
    $wrapped = $this->get('encryption_key')->value;
    if (empty($wrapped)) {
      throw new \LogicException('This study has no encryption key yet; save the study before requesting its key.');
    }
    return \Drupal::service('study_manager.encryption')->unwrapKey($wrapped);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->ensureEncryptionKey();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    /** @var \Drupal\study_manager\Service\StudyEncryptionService $service */
    $service = \Drupal::service('study_manager.encryption');
    $raw_key = $this->getRawEncryptionKey();

    foreach (self::ENCRYPTED_FILE_FIELDS as $field_name) {
      foreach ($this->get($field_name)->referencedEntities() as $file) {
        /** @var \Drupal\file\FileInterface $file */
        $uri = $file->getFileUri();
        $contents = file_get_contents($uri);
        if ($contents === FALSE || $service->isEncrypted($contents)) {
          continue;
        }

        $encrypted = $service->encryptFileContents($raw_key, $contents);
        file_put_contents($uri, $encrypted);
        $file->setSize(strlen($encrypted));
        $file->save();
      }
    }
  }

}
