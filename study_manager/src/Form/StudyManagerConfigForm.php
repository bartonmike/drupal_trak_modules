<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Study Manager.
 */
class StudyManagerConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['study_manager.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'study_manager_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('study_manager.settings');

    $form['current_file_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Private File System Path'),
      '#markup' => '<code>' . htmlspecialchars($config->get('file_path')) . '</code>',
    ];
    
    $form['file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private File System Path'),
      '#description' => $this->t('Enter the path within the private file system where study files will be stored. Example: studies'),
      '#default_value' => $config->get('file_path'),
      '#required' => TRUE,
    ];

    $form['max_file_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum File Size'),
      '#description' => $this->t('Maximum file size allowed for uploads (e.g., 10MB, 100MB)'),
      '#default_value' => $config->get('max_file_size') ?: '50MB',
    ];

    $form['allowed_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed File Extensions'),
      '#description' => $this->t('Space-separated list of allowed file extensions (e.g., pdf doc docx txt jpg png)'),
      '#default_value' => $config->get('allowed_extensions') ?: 'pdf doc docx txt jpg png gif',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $file_path = $form_state->getValue('file_path');
    
    // Validate that the path doesn't start with a slash and doesn't contain dangerous characters
    if (strpos($file_path, '/') === 0) {
      $form_state->setErrorByName('file_path', $this->t('Path should not start with a slash.'));
    }
    
    if (strpos($file_path, '..') !== FALSE) {
      $form_state->setErrorByName('file_path', $this->t('Path cannot contain ".." for security reasons.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('study_manager.settings')
      ->set('file_path', $form_state->getValue('file_path'))
      ->set('max_file_size', $form_state->getValue('max_file_size'))
      ->set('allowed_extensions', $form_state->getValue('allowed_extensions'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
