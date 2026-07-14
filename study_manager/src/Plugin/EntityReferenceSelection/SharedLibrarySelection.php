<?php

namespace Drupal\study_manager\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Restricts entity reference selection to the study manager shared library.
 *
 * Only exposes File entities that were uploaded through the shared library
 * management form (study_manager.shared_library), scoped to the category
 * ("comparison_data" or "reference_materials") set in the field's
 * handler_settings. This keeps per-study private uploads out of the
 * selectable pool.
 *
 * @EntityReferenceSelection(
 *   id = "study_manager_shared_library",
 *   label = @Translation("Study Manager Shared Library"),
 *   entity_types = {"file"},
 *   group = "study_manager_shared_library",
 *   weight = 0
 * )
 */
class SharedLibrarySelection extends DefaultSelection {

  /**
   * Gets the private:// directory prefix for the configured category.
   */
  public static function getDirectory($category) {
    return 'private://study_manager/shared_library/' . $category;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = parent::buildEntityQuery($match, $match_operator);

    // Field-level handler_settings are merged flat into $this->configuration
    // by the selection plugin manager (see SelectionPluginManager::getSelectionHandler()).
    $category = $this->configuration['category'] ?? NULL;
    if ($category) {
      $prefix = static::getDirectory($category) . '/';
      $query->condition('uri', $prefix, 'STARTS_WITH');
    }

    return $query;
  }

}
