<?php

namespace Drupal\study_manager\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines the Study entity.
 *
 * @ContentEntityType(
 *   id = "study",
 *   label = @Translation("Study"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\study_manager\Entity\StudyListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\study_manager\Form\StudyForm",
 *       "add" = "Drupal\study_manager\Form\StudyForm",
 *       "edit" = "Drupal\study_manager\Form\StudyForm",
 *     },
 *     "access" = "Drupal\study_manager\StudyAccessControlHandler",
 *   },
 *   base_table = "study",
 *   admin_permission = "administer study manager",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "owner" = "user_id",
 *   },
 *   links = {
 *     "canonical" = "/study/{study}",
 *     "add-form" = "/study/add",
 *     "edit-form" = "/study/{study}/edit",
 *     "collection" = "/admin/content/studies",
 *   },
 * )
 */
class Study extends ContentEntityBase implements EntityOwnerInterface {
  
  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the study.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the study.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['files'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Files'))
      ->setDescription(t('Files attached to this study.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'studies/[study:id]')
      ->setSetting('file_extensions', 'pdf doc docx txt jpg png gif')
      ->setSetting('uri_scheme', 'private')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the study.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
      ])
      ->setDefaultValue('active')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the study was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the study was last edited.'));

    return $fields;
  }

  /**
   * Gets the study creation timestamp.
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Gets the study title.
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * Sets the study title.
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

}
