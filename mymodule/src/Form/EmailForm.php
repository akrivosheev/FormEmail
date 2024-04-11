<?php

namespace Drupal\mymodule\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Form\FormBuilderInterface;
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

    public function validateForm(array &$form, FormStateInterface $form_state) {
        $email = $form_state->getValue('email');
        if (!\Drupal::service('email.validator')->isValid($email)) {
            $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
        }
    }

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
      $apiKey = 'pat-eu1-31eddd6f-924d-48ec-b529-13cdf173b232';
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
        $message = 'New contact was added';
        \Drupal::messenger()->addStatus($this->t($message));
        \Drupal::logger('mymodule')->notice($message);
      } else {
        $message = 'New contact wasn\'t added';
        \Drupal::logger('mymodule')->error(t($message));
        \Drupal::messenger()->addStatus($this->t($message));
      }
    }

  }

}
