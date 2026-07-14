<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\study_manager\Plugin\EntityReferenceSelection\SharedLibrarySelection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages the shared library of files selectable by any study.
 */
class SharedLibraryForm extends FormBase {

  /**
   * The categories available in the shared library.
   */
  const CATEGORIES = [
    'comparison_data' => 'Comparison Data',
    'reference_materials' => 'Reference Materials',
  ];

  /**
   * The extensions allowed per category.
   */
  const CATEGORY_EXTENSIONS = [
    'comparison_data' => 'csv xlsx',
    'reference_materials' => 'pdf png jpg',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'study_manager_shared_library_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $file_storage = $this->entityTypeManager->getStorage('file');

    foreach (self::CATEGORIES as $category => $label) {
      $form[$category] = [
        '#type' => 'details',
        '#title' => $this->t('@label', ['@label' => $label]),
        '#open' => TRUE,
      ];

      $form[$category]['upload_' . $category] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Upload @label files', ['@label' => $label]),
        '#upload_location' => SharedLibrarySelection::getDirectory($category),
        '#multiple' => TRUE,
        '#upload_validators' => [
          'file_validate_extensions' => [self::CATEGORY_EXTENSIONS[$category]],
        ],
      ];

      $ids = $file_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('uri', SharedLibrarySelection::getDirectory($category) . '/', 'STARTS_WITH')
        ->execute();

      $rows = [];
      foreach ($file_storage->loadMultiple($ids) as $file) {
        /** @var \Drupal\file\FileInterface $file */
        $rows[$file->id()] = $file->getFilename();
      }

      if ($rows) {
        $form[$category]['existing_' . $category] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Existing @label files (check to delete)', ['@label' => $label]),
          '#options' => $rows,
        ];
      }
      else {
        $form[$category]['existing_' . $category] = [
          '#type' => 'item',
          '#markup' => $this->t('No files uploaded yet.'),
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save shared library'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file_storage = $this->entityTypeManager->getStorage('file');

    foreach (array_keys(self::CATEGORIES) as $category) {
      // Permanently save newly uploaded files.
      $fids = $form_state->getValue('upload_' . $category) ?: [];
      foreach ($fids as $fid) {
        $file = File::load($fid);
        if ($file && !$file->isPermanent()) {
          $file->setPermanent();
          $file->save();
          \Drupal::service('file.usage')->add($file, 'study_manager', 'shared_library', $file->id());
        }
      }

      // Delete files that were checked for removal.
      $to_delete = array_filter($form_state->getValue('existing_' . $category) ?: []);
      foreach ($to_delete as $fid) {
        $file = $file_storage->load($fid);
        if ($file) {
          \Drupal::service('file.usage')->delete($file, 'study_manager', 'shared_library', $file->id());
          $file->delete();
        }
      }
    }

    $this->messenger()->addMessage($this->t('Shared library updated.'));
  }

}
