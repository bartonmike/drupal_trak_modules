<?php

namespace Drupal\study_manager\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\study_manager\Entity\Study;

/**
 * Controller for Study entities.
 */
class StudyController extends ControllerBase {

  /**
   * Simple monoline icons used on the study page, keyed by icon name.
   */
  const ICONS = [
    'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/></svg>',
    'image' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
    'database' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
  ];

  /**
   * Provides the page title for a study.
   */
  public function title(Study $study) {
    return $study->getTitle();
  }

  /**
   * Displays a study.
   */
  public function view(Study $study) {
    $status_value = $study->get('status')->value;
    $status_options = $study->get('status')->getFieldDefinition()->getSetting('allowed_values');
    $status_label = $status_options[$status_value] ?? $status_value;

    $description_item = $study->get('description');
    $description = $description_item->isEmpty() ? NULL : [
      '#type' => 'processed_text',
      '#text' => $description_item->value,
      '#format' => $description_item->format,
    ];

    // "Study data files" combines both per-study upload fields (Study Data
    // and Private Comparison Data) since both are researcher-owned CSV/XLSX
    // uploads private to this study; "Study reference files" is the
    // separate, non-encrypted reference materials upload.
    $study_data_files = array_merge(
      $this->buildFileRows($study, 'files', 'study_manager.file_download', $this->t('Download')),
      $this->buildFileRows($study, 'private_comparison_files', 'study_manager.file_download', $this->t('Download'))
    );
    $reference_files = $this->buildFileRows($study, 'private_reference_files', NULL, $this->t('View'));

    // Selecting shared library files happens via the edit form's entity
    // reference widgets; there's no separate browse page yet, so the
    // "Browse" buttons link there.
    $edit_url = $study->access('update') ? Url::fromRoute('entity.study.edit_form', ['study' => $study->id()])->toString() : NULL;

    $comparison_data = [
      'items' => $this->buildSharedLibraryCards($study, 'selected_comparison_files', $this->t('View dataset'), 'database'),
      'browse_url' => $edit_url,
    ];
    $reference_resources = [
      'items' => $this->buildSharedLibraryCards($study, 'selected_reference_files', $this->t('View resource'), NULL),
      'browse_url' => $edit_url,
    ];

    $shared_studies = $this->buildSharedStudies($study);

    $participants = NULL;
    if ($study->access('update')) {
      $participants = [
        'add_url' => Url::fromRoute('study_manager.participants_add', ['study' => $study->id()])->toString(),
        'list_url' => Url::fromRoute('study_manager.participants_list', ['study' => $study->id()])->toString(),
      ];
    }

    // The reporting tool is a separate application, reached by an absolute
    // path rather than a Drupal route.
    $build_reports_url = Url::fromUserInput('/trak', ['query' => ['study_id' => $study->id()]])->toString();

    return [
      '#theme' => 'study_manager_study_page',
      '#attached' => ['library' => ['study_manager/study_page']],
      '#title' => $study->getTitle(),
      '#description' => $description,
      '#status_value' => $status_value,
      '#status_label' => $status_label,
      '#build_reports_url' => $build_reports_url,
      '#study_data_files' => $study_data_files,
      '#reference_files' => $reference_files,
      '#comparison_data' => $comparison_data,
      '#reference_resources' => $reference_resources,
      '#participants' => $participants,
      '#shared_studies' => $shared_studies,
      '#cache' => [
        // Access checks above vary per-owner, not just per-permission, so
        // this has to vary by user, not merely by permission set.
        'contexts' => ['user'],
        'tags' => $study->getCacheTags(),
        // Shared study names come from global.yaml, which is written outside
        // Drupal and can't invalidate a cached copy of this page.
        'max-age' => !empty($shared_studies['items']) ? 0 : Cache::PERMANENT,
      ],
    ];
  }

  /**
   * Builds the "Shared studies" section: studies from global.yaml that have
   * been added to this one (see SharedStudiesController).
   *
   * @return array|null
   *   {items, browse_url}, or NULL if there's nothing to show and the viewer
   *   can't add anything.
   */
  private function buildSharedStudies(Study $study): ?array {
    $can_manage = $study->access('update');
    $can_browse = $can_manage && $this->currentUser()->hasPermission('browse shared studies');

    $project_ids = array_column($study->get('shared_studies')->getValue(), 'value');
    if (!$project_ids && !$can_browse) {
      return NULL;
    }

    /** @var \Drupal\study_manager\Service\SharedStudyIndex $index */
    $index = \Drupal::service('study_manager.shared_study_index');
    $destination = $study->toUrl()->toString();

    $items = [];
    foreach ($project_ids as $project_id) {
      $project = $index->getProject($project_id);
      $items[] = [
        'title' => $project['name'] ?? $project_id,
        'meta' => $project
          ? ($project['description'] ?: $this->t('Project @id', ['@id' => $project_id]))
          : $this->t('No longer available in the shared index.'),
        'url' => $can_manage ? Url::fromRoute('study_manager.shared_studies_remove', [
          'study' => $study->id(),
          'project_id' => $project_id,
        ], ['query' => ['destination' => $destination]])->toString() : NULL,
        'action_label' => $this->t('Remove'),
        'icon' => $this->icon('database'),
      ];
    }

    return [
      'items' => $items,
      'browse_url' => $can_browse ? Url::fromRoute('study_manager.shared_studies', [], ['query' => ['study' => $study->id()]])->toString() : NULL,
    ];
  }

  /**
   * Builds file-row data for a per-study upload field.
   *
   * @param string $route_name
   *   The route to link to, or NULL to link directly at the file's URI
   *   (used for fields that aren't encrypted at rest).
   */
  private function buildFileRows(Study $study, string $field_name, ?string $route_name, $action_label): array {
    $rows = [];
    $date_formatter = \Drupal::service('date.formatter');

    foreach ($study->get($field_name) as $item) {
      $file = $item->entity;
      if (!$file) {
        continue;
      }

      $meta_parts = [];
      if (!empty($item->description)) {
        $meta_parts[] = $item->description;
      }
      $meta_parts[] = $this->t('Updated @date', [
        '@date' => $date_formatter->format($file->getChangedTime(), 'custom', 'F j, Y'),
      ]);

      $url = $route_name
        ? Url::fromRoute($route_name, ['study' => $study->id(), 'field_name' => $field_name, 'file' => $file->id()])->toString()
        : \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());

      $rows[] = [
        'name' => $file->getFilename(),
        'meta' => implode(' · ', $meta_parts),
        'size' => format_size($file->getSize()),
        'url' => $url,
        'action_label' => $action_label,
        'icon' => $this->iconForExtension(pathinfo($file->getFilename(), PATHINFO_EXTENSION)),
      ];
    }

    return $rows;
  }

  /**
   * Builds card data for a shared-library selection field.
   *
   * @param string|null $icon
   *   Force a specific icon (e.g. 'database' for datasets), or NULL to pick
   *   one based on the file's extension.
   */
  private function buildSharedLibraryCards(Study $study, string $field_name, $action_label, ?string $icon): array {
    $items = [];

    foreach ($study->get($field_name)->referencedEntities() as $file) {
      /** @var \Drupal\file\FileInterface $file */
      $title = $file->get('study_manager_title')->value ?: $file->getFilename();
      $description = $file->get('study_manager_description')->value;

      $items[] = [
        'title' => $title,
        'meta' => $description,
        'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
        'action_label' => $action_label,
        'icon' => $icon ? $this->icon($icon) : $this->iconForExtension(pathinfo($file->getFilename(), PATHINFO_EXTENSION)),
      ];
    }

    return $items;
  }

  /**
   * Renders one of the fixed icons in self::ICONS as safe markup.
   */
  private function icon(string $name): array {
    return ['#markup' => Markup::create(self::ICONS[$name] ?? self::ICONS['document'])];
  }

  /**
   * Picks an icon based on a file extension.
   */
  private function iconForExtension(string $extension): array {
    if (in_array(strtolower($extension), ['png', 'jpg', 'jpeg', 'gif'], TRUE)) {
      return $this->icon('image');
    }
    return $this->icon('document');
  }

}
