<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\study_manager\Entity\Study;

/**
 * Confirms removing a participant from a study.
 *
 * This only deletes the study_participants mapping row - the underlying
 * user account is left alone, since it may be a participant in other
 * studies too.
 */
class ParticipantRemoveForm extends ConfirmFormBase {

  /**
   * The study the participant is being removed from.
   */
  protected Study $study;

  /**
   * The study_participants.id of the mapping row to delete.
   */
  protected int $mappingId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'study_manager_participant_remove_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Study $study = NULL, $mapping_id = NULL) {
    $this->study = $study;
    $this->mappingId = (int) $mapping_id;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Remove this participant from %study?', ['%study' => $this->study->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This removes the participant from this study only. Their user account is not deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('study_manager.participants_list', ['study' => $this->study->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::database()->delete('study_participants')
      ->condition('id', $this->mappingId)
      ->condition('study_id', $this->study->id())
      ->execute();

    $this->messenger()->addStatus($this->t('The participant has been removed from this study.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
