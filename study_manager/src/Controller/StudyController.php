<?php

namespace Drupal\study_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\study_manager\Entity\Study;

/**
 * Controller for Study entities.
 */
class StudyController extends ControllerBase {

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
    $view_builder = $this->entityTypeManager()->getViewBuilder('study');
    $build = $view_builder->view($study, 'full');

    return [
      '#prefix' => '<div style="padding-left: 20px; padding-right: 20px;">',
      '#suffix' => '</div>',
      'study' => $build,
    ];
  }

}
