<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Form controller for Study edit forms.
 */
class StudyForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    $entity = $this->entity;

    // Add custom validation and handling for file uploads.
    $config = \Drupal::config('study_manager.settings');
    $file_path = $config->get('file_path') ?: 'studies';

    // For new entities, use a temporary location that will be updated after save
    $entity_id = $entity->id() ?: 'new';

    // Each upload field keeps its own subdirectory and extension whitelist
    // so Study Data / Private Comparison Data / Private Reference Materials
    // can't be conflated.
    $upload_fields = [
      'files' => [
        'location' => "private://{$file_path}/study_data/{$entity_id}/study_data",
        'extensions' => 'csv xlsx',
      ],
      'private_comparison_files' => [
        'location' => "private://{$file_path}/study_data/{$entity_id}/comparison_data",
        'extensions' => 'csv xlsx',
      ],
      'private_reference_files' => [
        'location' => "private://{$file_path}/study_data/{$entity_id}/reference_materials",
        'extensions' => 'pdf png jpg',
      ],
    ];

    // Ensure every upload directory exists before the widget can try to use
    // it. AJAX uploads happen as soon as a file is chosen, well before the
    // entity is saved, so this can't wait for save() - and it has to run on
    // every form build (not just entity creation) in case the file_path
    // config changed after the study already existed, or a directory was
    // never created.
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    foreach ($upload_fields as $field_name => $settings) {
      if (isset($form[$field_name]['widget'])) {
        $file_system->prepareDirectory($settings['location'], $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);

        // These fields are unlimited-cardinality, so the widget is rendered
        // as a set of per-delta elements (0, 1, ..., 'add_more'); the
        // wrapper itself carries no #upload_location. It has to be set on
        // each delta element or the field definition's hardcoded default
        // directory silently wins instead of the admin-configured one.
        foreach (Element::children($form[$field_name]['widget']) as $delta) {
          if (is_numeric($delta)) {
            $form[$field_name]['widget'][$delta]['#upload_location'] = $settings['location'];
            $form[$field_name]['widget'][$delta]['#upload_validators']['file_validate_extensions'] = [$settings['extensions']];
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    // Custom validation can be added here
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Study.', [
          '%label' => $entity->label(),
        ]));
        
        // Create the upload directories for this study.
        $config = \Drupal::config('study_manager.settings');
        $file_path = $config->get('file_path') ?: 'studies';
        $base_directory = "private://{$file_path}/" . $entity->id();

        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        foreach ([$base_directory, "{$base_directory}/comparison_data", "{$base_directory}/reference_materials"] as $directory) {
          $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);
        }

        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Study.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.study.canonical', ['study' => $entity->id()]);
  }

}
