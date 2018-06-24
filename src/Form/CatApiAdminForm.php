<?php
/**
 * @file
 * Contains Drupal\welcome\Form\CatApiAdminForm.
 */
namespace Drupal\cat_api\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class CatApiAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cat_api_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cat_api.adminsettings',
    ];
  }

  /**
   * Function to return the documentation link.
   *
   * @return String
   *   HTML Markup with link.
   */
  protected function getDocumentationLink() {
    $link_text = $this->t('The Cat API documentation');
    $link_url = Url::fromUri('http://thecatapi.com/docs.html');
    return Link::fromTextAndUrl($link_text, $link_url)->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cat_api.adminsettings');

    $form['cat_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cat API URL'),
      '#description' => $this->t('The endpoint url for Cat Api Usage.'),
      '#required' => TRUE,
      '#default_value' => $config->get('cat_api_url'),
    ];
    $message = $this->t('You can make unlimited requests without an API key, but you\'ll only get access to the first 1000 Images and also you can\'t access other features from the API.<br>For more info, plese see ');
    
    $form['cat_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cat API Key'),
      '#description' => $message . $this->getDocumentationLink() . '.',
      '#default_value' => $config->get('cat_api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('cat_api.adminsettings')
      ->set('cat_api_url', $form_state->getValue('cat_api_url'))
      ->set('cat_api_key', $form_state->getValue('cat_api_key'))
      ->save();
  }
}  