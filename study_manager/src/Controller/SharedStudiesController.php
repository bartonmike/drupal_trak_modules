<?php

namespace Drupal\study_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\study_manager\Entity\Study;
use Drupal\study_manager\Service\SharedStudyIndex;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists shared studies from global.yaml and adds them to a researcher's study.
 */
class SharedStudiesController extends ControllerBase {

  /**
   * The shared study index.
   *
   * @var \Drupal\study_manager\Service\SharedStudyIndex
   */
  protected $index;

  /**
   * Constructs the controller.
   */
  public function __construct(SharedStudyIndex $index) {
    $this->index = $index;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('study_manager.shared_study_index'));
  }

  /**
   * Displays the shared studies page.
   *
   * The study being added to is picked with a "?study=" query parameter
   * (the study page's "Browse shared studies" button passes it), so the
   * per-card add/remove actions can be plain CSRF-protected links.
   */
  public function page(Request $request) {
    $projects = $this->index->getProjects();

    $study_options = $this->ownedEditableStudies();
    $selected = NULL;
    $selected_id = $request->query->get('study');
    if ($selected_id !== NULL && $selected_id !== '') {
      $study = $this->entityTypeManager()->getStorage('study')->load($selected_id);
      if ($study && $study->access('update')) {
        $selected = $study;
        $study_options[$study->id()] = $study->getTitle();
      }
    }
    elseif (count($study_options) === 1) {
      $selected = $this->entityTypeManager()->getStorage('study')->load(array_key_first($study_options));
    }

    $added = $selected ? array_column($selected->get('shared_studies')->getValue(), 'value') : [];
    $destination = Url::fromRoute('study_manager.shared_studies', [], [
      'query' => $selected ? ['study' => $selected->id()] : [],
    ])->toString();

    $rows = [];
    foreach ($projects as $id => $project) {
      $action = NULL;
      if ($selected) {
        $route_parameters = ['study' => $selected->id(), 'project_id' => $id];
        $options = ['query' => ['destination' => $destination]];
        if ((string) $id === (string) $selected->id()) {
          $action = ['state' => 'self'];
        }
        elseif (in_array($id, $added, TRUE)) {
          $action = [
            'state' => 'added',
            'url' => Url::fromRoute('study_manager.shared_studies_remove', $route_parameters, $options)->toString(),
          ];
        }
        else {
          $action = [
            'state' => 'add',
            'url' => Url::fromRoute('study_manager.shared_studies_add', $route_parameters, $options)->toString(),
          ];
        }
      }

      $rows[] = $project + [
        'dom_id' => 'shared-study-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $id),
        'date_range' => $this->formatDateRange($project['start'], $project['end']),
        'action' => $action,
      ];
    }

    return [
      '#theme' => 'study_manager_shared_studies',
      '#attached' => ['library' => ['study_manager/shared_studies']],
      '#projects' => $rows,
      '#study_options' => $study_options,
      '#selected_study' => $selected ? [
        'id' => $selected->id(),
        'title' => $selected->getTitle(),
        'url' => $selected->toUrl()->toString(),
      ] : NULL,
      '#index_missing' => !$projects,
      '#cache' => [
        // global.yaml is written outside Drupal, so there's nothing to
        // invalidate a cached copy of this page when it changes.
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Adds a shared study to a study.
   */
  public function add(Study $study, string $project_id) {
    $project = $this->index->getProject($project_id);
    if (!$project) {
      throw new NotFoundHttpException();
    }

    $added = array_column($study->get('shared_studies')->getValue(), 'value');
    if ((string) $study->id() === $project_id) {
      $this->messenger()->addWarning($this->t('A study cannot be added to itself.'));
    }
    elseif (!in_array($project_id, $added, TRUE)) {
      $study->get('shared_studies')->appendItem($project_id);
      $study->save();
      $this->messenger()->addStatus($this->t('Added %shared to %study.', [
        '%shared' => $project['name'],
        '%study' => $study->getTitle(),
      ]));
    }

    // Overridden by the "destination" query parameter when the link has one.
    return new RedirectResponse($study->toUrl()->toString());
  }

  /**
   * Removes a shared study from a study.
   *
   * Doesn't require the project to still be in global.yaml, so studies that
   * have since been dropped from the index can still be cleaned up.
   */
  public function remove(Study $study, string $project_id) {
    $field = $study->get('shared_studies');
    $remaining = array_values(array_filter(
      array_column($field->getValue(), 'value'),
      function ($value) use ($project_id) {
        return $value !== $project_id;
      }
    ));

    if (count($remaining) !== $field->count()) {
      $field->setValue($remaining);
      $study->save();
      $project = $this->index->getProject($project_id);
      $this->messenger()->addStatus($this->t('Removed %shared from %study.', [
        '%shared' => $project['name'] ?? $project_id,
        '%study' => $study->getTitle(),
      ]));
    }

    return new RedirectResponse($study->toUrl()->toString());
  }

  /**
   * Gets the current user's own studies that they're allowed to edit.
   *
   * @return array
   *   Study titles keyed by study ID, sorted by title.
   */
  protected function ownedEditableStudies(): array {
    $storage = $this->entityTypeManager()->getStorage('study');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('user_id', $this->currentUser()->id())
      ->sort('title')
      ->execute();

    $options = [];
    foreach ($storage->loadMultiple($ids) as $study) {
      if ($study->access('update')) {
        $options[$study->id()] = $study->getTitle();
      }
    }
    return $options;
  }

  /**
   * Formats a global.yaml date range for display, or '' if neither end is set.
   */
  protected function formatDateRange(string $start, string $end): string {
    if ($start !== '' && $end !== '') {
      return $start . ' – ' . $end;
    }
    if ($start !== '') {
      return (string) $this->t('From @date', ['@date' => $start]);
    }
    if ($end !== '') {
      return (string) $this->t('Until @date', ['@date' => $end]);
    }
    return '';
  }

}
