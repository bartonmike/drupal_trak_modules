<?php

namespace Drupal\study_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\study_manager\Entity\Study;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bulk-creates/links participant user accounts for a study from a CSV.
 *
 * The CSV has two columns: email, participant_id. participant_id is the
 * researcher-assigned identifier that correlates this participant to rows
 * in the study's own data files - it's scoped per-study, not globally
 * unique, so it's validated for uniqueness within this study only.
 */
class ParticipantUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'study_manager_participant_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Study $study = NULL) {
    $form['study_id'] = [
      '#type' => 'value',
      '#value' => $study->id(),
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Participant mapping file'),
      '#description' => $this->t('A CSV file with two columns: email, participant_id. A new account (with a set-password email) is created for any email not already registered.'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
    $files = $this->getRequest()->files->get('files', []);
    $uploaded = $files['csv_file'] ?? NULL;

    if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
      $form_state->setErrorByName('csv_file', $this->t('No valid file was uploaded.'));
      return;
    }

    if (strtolower($uploaded->getClientOriginalExtension()) !== 'csv') {
      $form_state->setErrorByName('csv_file', $this->t('The file must be a CSV.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $study_id = (int) $form_state->getValue('study_id');

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploaded */
    $uploaded = $this->getRequest()->files->get('files')['csv_file'];

    $handle = fopen($uploaded->getRealPath(), 'r');
    if ($handle === FALSE) {
      $this->messenger()->addError($this->t('Could not read the uploaded file.'));
      return;
    }

    $header = fgetcsv($handle);
    $header = array_map(fn($col) => strtolower(trim((string) $col)), $header ?: []);
    $email_col = array_search('email', $header, TRUE);
    $participant_id_col = array_search('participant_id', $header, TRUE);

    if ($email_col === FALSE || $participant_id_col === FALSE) {
      fclose($handle);
      $this->messenger()->addError($this->t('The CSV must have "email" and "participant_id" columns.'));
      return;
    }

    $created_accounts = 0;
    $linked_existing = 0;
    $skipped = [];
    $email_validator = \Drupal::service('email.validator');
    $database = \Drupal::database();
    $row_num = 1;

    while (($row = fgetcsv($handle)) !== FALSE) {
      $row_num++;
      $email = trim((string) ($row[$email_col] ?? ''));
      $participant_id = trim((string) ($row[$participant_id_col] ?? ''));

      if ($email === '' || $participant_id === '') {
        $skipped[] = $this->t('Row @row: missing email or participant_id.', ['@row' => $row_num]);
        continue;
      }

      if (!$email_validator->isValid($email)) {
        $skipped[] = $this->t('Row @row: "@email" is not a valid email address.', ['@row' => $row_num, '@email' => $email]);
        continue;
      }

      // A participant_id must be unique within this study; a user account
      // should also only appear once per study, under a single
      // participant_id, so the correlation to study data stays unambiguous.
      $existing_mapping = $database->select('study_participants', 'sp')
        ->fields('sp', ['id', 'uid'])
        ->condition('study_id', $study_id)
        ->condition('participant_id', $participant_id)
        ->execute()
        ->fetchAssoc();
      if ($existing_mapping) {
        $skipped[] = $this->t('Row @row: participant_id "@pid" is already in use for this study.', ['@row' => $row_num, '@pid' => $participant_id]);
        continue;
      }

      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
      $account = $users ? reset($users) : NULL;

      $uid_already_mapped = $account ? (bool) $database->select('study_participants', 'sp')
        ->fields('sp', ['id'])
        ->condition('study_id', $study_id)
        ->condition('uid', $account->id())
        ->execute()
        ->fetchField() : FALSE;
      if ($uid_already_mapped) {
        $skipped[] = $this->t('Row @row: "@email" is already a participant in this study under a different participant_id.', ['@row' => $row_num, '@email' => $email]);
        continue;
      }

      try {
        if (!$account) {
          $account = User::create([
            'name' => $email,
            'mail' => $email,
            // Active but with no password ever set: the account can't be
            // logged into except via the one-time login link in the
            // notification email below. Blocked accounts can't complete
            // that reset flow, so "active + no password" is the closest
            // equivalent to "pending until they set a password".
            'status' => 1,
          ]);
          $account->addRole('study_participant');
          $account->enforceIsNew();
          $account->save();
          _user_mail_notify('register_admin_created', $account);
          $created_accounts++;
        }
        else {
          if (!$account->hasRole('study_participant')) {
            $account->addRole('study_participant');
            $account->save();
          }
          $linked_existing++;
        }

        $database->insert('study_participants')
          ->fields([
            'study_id' => $study_id,
            'uid' => $account->id(),
            'participant_id' => $participant_id,
            'created' => \Drupal::time()->getRequestTime(),
          ])
          ->execute();
      }
      catch (\Exception $e) {
        $skipped[] = $this->t('Row @row: failed to process "@email" (@message).', [
          '@row' => $row_num,
          '@email' => $email,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    fclose($handle);

    $this->messenger()->addStatus($this->t('@created new account(s) created, @linked existing account(s) linked.', [
      '@created' => $created_accounts,
      '@linked' => $linked_existing,
    ]));
    foreach ($skipped as $message) {
      $this->messenger()->addWarning($message);
    }

    $form_state->setRedirect('study_manager.participants_list', ['study' => $study_id]);
  }

}
