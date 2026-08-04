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

      $files = $file_storage->loadMultiple($ids);

      if ($files) {
        $form[$category]['existing_' . $category] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Filename'),
            $this->t('Title'),
            $this->t('Description'),
            $this->t('Delete'),
          ],
        ];

        foreach ($files as $file) {
          /** @var \Drupal\file\FileInterface $file */
          $fid = $file->id();
          $form[$category]['existing_' . $category][$fid]['filename'] = [
            '#type' => 'item',
            '#markup' => $file->getFilename(),
          ];
          $form[$category]['existing_' . $category][$fid]['title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#title_display' => 'invisible',
            '#default_value' => $file->get('study_manager_title')->value,
          ];
          $form[$category]['existing_' . $category][$fid]['description'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Description'),
            '#title_display' => 'invisible',
            '#default_value' => $file->get('study_manager_description')->value,
          ];
          $form[$category]['existing_' . $category][$fid]['delete'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Delete'),
            '#title_display' => 'invisible',
          ];
        }
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

      // Update title/description, and delete rows that were checked.
      $existing_rows = $form_state->getValue('existing_' . $category) ?: [];
      foreach ($existing_rows as $fid => $row) {
        $file = $file_storage->load($fid);
        if (!$file) {
          continue;
        }

        if (!empty($row['delete'])) {
          \Drupal::service('file.usage')->delete($file, 'study_manager', 'shared_library', $file->id());
          $file->delete();
          continue;
        }

        $file->set('study_manager_title', $row['title']);
        $file->set('study_manager_description', $row['description']);
        $file->save();
      }
    }

    $this->messenger()->addMessage($this->t('Shared library updated.'));
  }

}
