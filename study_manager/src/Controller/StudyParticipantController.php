<?php

namespace Drupal\study_manager\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\study_manager\Entity\Study;

/**
 * Lists the participants linked to a study.
 */
class StudyParticipantController extends ControllerBase {

  /**
   * Displays the participant list for a study.
   */
  public function list(Study $study) {
    $rows = [];
    $mappings = \Drupal::database()->select('study_participants', 'sp')
      ->fields('sp', ['id', 'uid', 'participant_id', 'created'])
      ->condition('study_id', $study->id())
      ->orderBy('participant_id')
      ->execute();

    foreach ($mappings as $mapping) {
      $account = $this->entityTypeManager()->getStorage('user')->load($mapping->uid);
      $rows[] = [
        $mapping->participant_id,
        $account ? $account->getEmail() : $this->t('(account deleted)'),
        $account ? $account->getAccountName() : '-',
        Link::createFromRoute($this->t('Remove'), 'study_manager.participants_remove', [
          'study' => $study->id(),
          'mapping_id' => $mapping->id,
        ])->toString(),
      ];
    }

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Participants'),
      '#url' => Url::fromRoute('study_manager.participants_add', ['study' => $study->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Participant ID'),
        $this->t('Email'),
        $this->t('Username'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('This study has no participants yet.'),
    ];

    return $build;
  }

}
