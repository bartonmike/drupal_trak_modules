<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

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

    // Add custom validation and handling for file uploads
    if (isset($form['files'])) {
      $config = \Drupal::config('study_manager.settings');
      $file_path = $config->get('file_path') ?: 'studies';
      $allowed_extensions = $config->get('allowed_extensions') ?: 'pdf doc docx txt jpg png gif';
      
      // For new entities, use a temporary location that will be updated after save
      $entity_id = $entity->id() ?: 'new';
      
      // Update file field settings with current configuration  
      $form['files']['widget']['#upload_location'] = "private://{$file_path}/{$entity_id}";
      
      $form['files']['widget']['#upload_validators'] = [
        'file_validate_extensions' => [$allowed_extensions],
      ];
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
        
        // Create the directory for this study
        $config = \Drupal::config('study_manager.settings');
        $file_path = ($config->get('file_path') ?: 'trak_data') . 'study_data';
        $directory = "private://{$file_path}/" . $entity->id();
        
        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);
        
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Study.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.study.canonical', ['study' => $entity->id()]);
  }

}
