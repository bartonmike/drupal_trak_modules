<?php

namespace Drupal\study_manager\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Study entities.
 */
class StudyListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Study ID');
    $header['title'] = $this->t('Title');
    $header['status'] = $this->t('Status');
    $header['owner'] = $this->t('Owner');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\study_manager\Entity\Study $entity */
    $row['id'] = $entity->id();
    $row['title'] = Link::createFromRoute(
      $entity->label(),
      'entity.study.canonical',
      ['study' => $entity->id()]
    );
    $row['status'] = $entity->get('status')->value;
    $row['owner'] = $entity->getOwner()->getDisplayName();
    $row['created'] = \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'short');
    
    return $row + parent::buildRow($entity);
  }

}
