<?php

namespace Drupal\mymodule\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;

/**
 * Class EmailForm
 * @package Drupal\mymodule\Form
 */
class EmailForm extends FormBase
{

  protected $formBuilder;
  protected $logger;
  private $mailManager;
  private $languageManager;

  public function __construct(
    $form_builder,
    LoggerInterface $logger,
    MailManagerInterface $mailManager,
    LanguageManagerInterface $languageManager
  ) {
    $this->formBuilder = $form_builder;
    $this->logger = $logger;
    $this->mailManager = $mailManager;
    $this->languageManager = $languageManager;
  }

  /**
   * @param ContainerInterface $container
   * @return EmailForm|static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('logger.factory')->get('custom_module'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

    public function getFormId() {
        return 'email_form';
    }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
    public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Name'),
      '#required' => TRUE,
    ];
    $form['lastname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Last Name'),
      '#required' => TRUE,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
            '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    if (!\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $params['message'] = $values['message'];
    $params['subject'] = $values['subject'];
    $params['name'] = $values['name'];
    $params['lastname'] = $values['lastname'];
    $params['email'] = $values['email'];

      $to = \Drupal::config('system.site')->get('mail');
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
      $send = TRUE;
      $params = [
          'email' => $values['email'],
          'subject' => 'Тема письма',
          'message' => 'Текст сообщения',
      ];
      $result = $this->mailManager->mail('mymodule', 'email_form', $to, $langcode, $params, NULL, $send);

    if ($result['result'] !== true) {
      $message = 'There was an error while sending the email.';
      \Drupal::logger('mymodule')->error(t($message));
      \Drupal::messenger()->addError($this->t($message));
    }
    else {
      $message = 'Form submitted successfully!';
      \Drupal::messenger()->addStatus($this->t($message));
      \Drupal::logger('mymodule')->notice($message);

      // Create new contact in hubspot
      $client = new Client();

      $config = $this->config('mymodule.email_form.settings');
      $apiKey = $config->get('api_key');
      $hubspotEndpoint = 'https://api.hubapi.com/contacts/v1/contact/';

      $response = $client->post($hubspotEndpoint, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $apiKey,
        ],
        'json' => [
          'properties' => [
            [
              'property' => 'email',
              'value' => $values['email'],
            ],
            [
              'property' => 'firstname',
              'value' => $values['name'],
            ],
            [
              'property' => 'lastname',
              'value' => $values['lastname'],
            ],
          ],
        ],
      ]);

      if ($response->getStatusCode() == 200) {
        $message = 'New contact with email: ' . $values['email'] . ' was added';
        \Drupal::messenger()->addStatus($this->t($message));
        \Drupal::logger('mymodule')->notice($message);
      }
      else {
        $message = 'New contact wasn\'t added';
        \Drupal::logger('mymodule')->error(t($message));
        \Drupal::messenger()->addStatus($this->t($message));
      }
    }

  }

}
