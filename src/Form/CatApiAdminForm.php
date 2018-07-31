<?php
/**
 * @file
 * Contains Drupal\cat_api\Form\CatApiAdminForm.
 */

namespace Drupal\cat_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Utility;

/**
 * Class for the Admin form of Cat API.
 */
class CatApiAdminForm extends ConfigFormBase {

  /**
   * Const with name of setting.
   */
  const CAT_API_SETTINGS = 'cat_api.settings';

  /**
   * Const to use in String Translations.
   */
  const CAT_API_T_CONTEXT = ['context' => 'CAT_API_MODULE'];

  /**
   * Function to return the the permission page link.
   *
   * @param string $text
   *   A text to be used with the link.
   *
   * @return string
   *   HTML Markup with link.
   */
  protected function getPermissionLink($text) {
    $link_text = $this->t($text, [], self::CAT_API_T_CONTEXT);
    $link_url = Url::fromRoute('user.admin_permissions', [], ['fragment' => 'module-cat_api']);
    return Link::fromTextAndUrl($link_text, $link_url)->toString();
  }

  /**
   * Function to return the documentation link.
   *
   * @return string
   *   HTML Markup with link.
   */
  protected function getDocumentationLink() {
    $link_text = $this->t('The Cat API documentation', [], self::CAT_API_T_CONTEXT);
    $link_url = Url::fromUri('http://thecatapi.com/docs.html');
    return Link::fromTextAndUrl($link_text, $link_url)->toString();
  }

  /**
   * Check how many requests the inputed API did.
   *
   * @param string $key
   *   The API Key to consult.
   *
   * @return string
   *   An string markup to use as Markup Element.
   */
  protected function getStatsList($key) {
    $stats = \Drupal::service('cat_api.api')->getStats($key);
    $stats = $stats['data']['stats']['statsoverview'];
    return $this->t('The inputed API Key already did: ', [], self::CAT_API_T_CONTEXT) .
      '<ul><li>' . $this->t('Get Requests: <b>@qtd</b>', ['@qtd' => $stats['total_get_requests']], self::CAT_API_T_CONTEXT) . '</li>' .
      '<li>' . $this->t('Votes: <b>@qtd</b>', ['@qtd' => $stats['total_votes']], self::CAT_API_T_CONTEXT) . '</li>' .
      '<li>' . $this->t('Favourites: <b>@qtd</b>', ['@qtd' => $stats['total_favourites']], self::CAT_API_T_CONTEXT) . '</li></ul>';
  }

  /**
   * Gets a category list to use on radios element.
   *
   * @return array
   *   An array with all categories options formated.
   */
  protected function getCategories() {
    $categories = \Drupal::service('cat_api.api')->getCategories();
    $options = ['all' => $this->t('All <b>CAT</b>egories', [], self::CAT_API_T_CONTEXT)];
    foreach ($categories as $category) {
      $options[$category['id']] = $this->t(ucfirst($category['name']), [], self::CAT_API_T_CONTEXT);
    }
    return $options;
  }

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
      self::CAT_API_SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CAT_API_SETTINGS);

    $form['cat_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cat API URL', [], self::CAT_API_T_CONTEXT),
      '#description' => $this->t('The endpoint url for Cat Api Usage.', [], self::CAT_API_T_CONTEXT),
      '#required' => TRUE,
      '#default_value' => $config->get('cat_api_url'),
    ];
    $message = $this->t('You can make unlimited requests without an API key, but you\'ll only get access to the first 1000 Images and also you can\'t access other features from the API.<br>For more info, plese see ', [], self::CAT_API_T_CONTEXT);

    $key = $config->get('cat_api_key');
    $form['cat_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cat API Key', [], self::CAT_API_T_CONTEXT),
      '#description' => $message . $this->getDocumentationLink() . '.',
      '#default_value' => $key,
    ];

    if (!empty($key)) {
      $form['markup_1'] = ['#markup' => $this->getStatsList($key)];
    }

    $form['details_1'] = [
      '#type' => 'details',
      '#title' => $this->t('Image configurations', [], self::CAT_API_T_CONTEXT),
      '#open' => FALSE,
    ];
    $form['details_1']['cat_api_size'] = [
      '#type' => 'radios',
      '#title' => $this->t('Size'),
      '#options' => [
        'full' => $this->t('Full - Original size', [], self::CAT_API_T_CONTEXT),
        'med' => $this->t('Medium - 500px as max width and/or height', [], self::CAT_API_T_CONTEXT),
        'small' => $this->t('Small - 250px as max width and/or height', [], self::CAT_API_T_CONTEXT),
      ],
      '#default_value' => $config->get('cat_api_size'),
      '#required' => TRUE,
    ];
    $image_formats = $config->get('cat_api_formats');
    $image_formats_options = [
      'jpg' => 'jpg',
      'gif' => 'gif',
      'png' => 'png',
    ];
    $form['details_1']['cat_api_formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Formats', [], self::CAT_API_T_CONTEXT),
      '#options' => $image_formats_options,
      '#default_value' => !empty($image_formats) ? $image_formats : $image_formats_options,
      '#required' => TRUE,
    ];
    $form['details_2'] = [
      '#type' => 'details',
      '#title' => $this->t('Cat Categories', [], self::CAT_API_T_CONTEXT),
      '#open' => FALSE,
    ];
    $form['details_2']['markup_1'] = [
      '#markup' => $this->t('The <b>CAT</b>egories is a way to choose which kind of cat do you want to show. Unfortunatelly, the API permits only one category. For more information, please see the @docs.', ['@docs' => $this->getDocumentationLink()], self::CAT_API_T_CONTEXT),
    ];
    $form['details_2']['cat_api_category'] = [
      '#type' => 'radios',
      '#title' => $this->t('Available options', [], self::CAT_API_T_CONTEXT),
      '#options' => $this->getCategories(),
      '#default_value' => $config->get('cat_api_category'),
      '#required' => TRUE,
    ];
    $form['details_3'] = [
      '#type' => 'details',
      '#title' => $this->t('User Actions', [], self::CAT_API_T_CONTEXT),
      '#open' => FALSE,
    ];
    $form['details_3']['markup_1'] = [
      '#markup' => $this->t('Enable a variety of possible interactions that users on your site can execute. For more information, pleasce check the @docs.', ['@docs' => $this->getDocumentationLink()], self::CAT_API_T_CONTEXT),
    ];
    $form['details_3']['cat_api_enable_vote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable vote', [], self::CAT_API_T_CONTEXT),
      '#description' => $this->t('Note: Users need <b>@permission</b> to use this feature.', ['@permission' => $this->getPermissionLink('Vote on a Cat permission')], self::CAT_API_T_CONTEXT),
      '#default_value' => $config->get('cat_api_enable_vote'),
    ];
    $form['details_3']['cat_api_enable_report'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable report', [], self::CAT_API_T_CONTEXT),
      '#description' => $this->t('Note: Users need <b>@permission</b> to use this feature.', ['@permission' => $this->getPermissionLink('Report a Cat permission')], self::CAT_API_T_CONTEXT),
      '#default_value' => $config->get('cat_api_enable_report'),
    ];
    $form['details_3']['cat_api_enable_favorite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable favorite', [], self::CAT_API_T_CONTEXT),
      '#description' => $this->t('Note: Users need <b>@permission</b> to use this feature.', ['@permission' => $this->getPermissionLink('Favorite a Cat permission')], self::CAT_API_T_CONTEXT),
      '#default_value' => $config->get('cat_api_enable_favorite'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->configFactory->getEditable(self::CAT_API_SETTINGS)
      ->set('cat_api_url', $form_state->getValue('cat_api_url'))
      ->set('cat_api_key', $form_state->getValue('cat_api_key'))
      ->set('cat_api_size', $form_state->getValue('cat_api_size'))
      ->set('cat_api_formats', $form_state->getValue('cat_api_formats'))
      ->set('cat_api_category', $form_state->getValue('cat_api_category'))
      ->set('cat_api_enable_vote', $form_state->getValue('cat_api_enable_vote'))
      ->set('cat_api_enable_report', $form_state->getValue('cat_api_enable_report'))
      ->set('cat_api_enable_favorite', $form_state->getValue('cat_api_enable_favorite'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
