<?php

/**
 * @file
 * Contains \Drupal\mymodule\Form\CollectPhoneSettings.
 */

namespace Drupal\mymodule\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class EmailFormSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'email_form_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [
      'mymodule.email_form.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('mymodule.email_form.settings');

    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Api Key from your hubspot site'),
      '#default_value' => $config->get('api_key'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('mymodule.email_form.settings')
      ->set('api_key', $values['api_key'])
      ->save();
  }
}
