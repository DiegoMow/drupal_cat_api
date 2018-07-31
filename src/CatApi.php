<?php
/**
 * @file
 * Class implementation of The Cat API.
 */

namespace Drupal\cat_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;

use Psr\Log\LoggerInterface;

/**
 * Default class for Cat Api usage.
 */
class CatApi {
  use StringTranslationTrait;

  /**
   * Constants with possibles API Calls.
   */
  const CAT_API_GET_IMAGES = 'images/get';
  const CAT_API_VOTE = 'images/vote';
  const CAT_API_FAVORITE = 'images/favourite';
  const CAT_API_GET_FAVORITES = 'images/getfavourites';
  const CAT_API_REPORT = 'images/report';
  const CAT_API_CATEGORIES = 'categories/list';
  const CAT_API_STATS = 'stats/getoverview';

  /**
   * The settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ConfigFactoryInterface $config, LoggerInterface $logger) {
    return new static(
      $config,
      $logger
    );
  }

  /**
   * Cat Api constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   CloudFlare config object.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config, LoggerInterface $logger) {
    $this->config = $config->get('cat_api.settings');
    $this->logger = $logger;
  }

  /**
   * Function to execute the API Calls.
   *
   * @param string $api_endpoint
   *   Endpoint to call.
   * @param array $params
   *   Parameters to use on call.
   *
   * @return array
   *   An Array with data from the call.
   */
  public function call($api_endpoint, array $params = []) {
    // TODO: Implement CACHE.
    $api_key = $this->config->get('cat_api_key');
    if (!empty($api_key) && !isset($params['api_key'])) {
      $params['api_key'] = $api_key;
    }
    $endpoint = $this->config->get('cat_api_url') . $api_endpoint;
    $url = Url::fromUri($endpoint, ['query' => $params])->toString();
    // TODO: Create error Handling for requests.
    $this->logger->debug($this->t('Request made at endpoint @url', ['@url' => $url]));
    $client = \Drupal::httpClient();
    $request = $client->request('GET', $url);
    $results = new \SimpleXMLElement($request->getBody()->getContents());

    return json_decode(json_encode($results), TRUE);
  }

  /**
   * Return only one image or a specific image.
   *
   * @param string $id
   *   Id of the image to load.
   *
   * @return array
   *   An array with image data.
   */
  public function getImage($id = '') {
    $params = [
      'results_per_page' => 1,
      'format' => 'xml',
      'type' => $this->getImageTypes(),
      'size' => $this->config->get('cat_api_size'),
    ];
    if (!empty($id)) {
      $params['image_id'] = $id;
    }
    // Here I add the category param, but if we have an Id, it's ignored.
    $category = $this->config->get('cat_api_category');
    if ($category !== 'all') {
      $params['category'] = $this->getCategoryName($category);
    }
    if (\Drupal::currentUser()->isAuthenticated()) {
      $params['sub_id'] = \Drupal::currentUser()->id();
    }
    $result = $this->call(self::CAT_API_GET_IMAGES, $params);
    return $result['data']['images']['image'];
  }

  /**
   * Return a quantity of desired images.
   *
   * @param int $qtde
   *   An int value between 0 and 100.
   *
   * @return array
   *   A multiple array with images data.
   */
  public function getImages($qtde) {
    $params = [
      'results_per_page' => $qtde,
      'format' => 'xml',
      'type' => $this->getImageTypes(),
      'size' => $this->config->get('cat_api_size'),
    ];
    if ($qtde <= 0) {
      $this->logger->warning($this->t('[LOW NUMBER] Wrong value used in function call! Will use "1" instead. Value: @qtde', ['@qtde' => $qtde]));
      $params['results_per_page'] = 1;
    }
    if ($qtde > 100) {
      $this->logger->warning($this->t('[BIG NUMBER] Wrong value used in function call! Will use "100" instead. Value: @qtde', ['@qtde' => $qtde]));
      $params['results_per_page'] = 100;
    }
    $category = $this->config->get('cat_api_category');
    if ($category !== 'all') {
      $params['category'] = $this->getCategoryName($category);
    }
    if (\Drupal::currentUser()->isAuthenticated()) {
      $params['sub_id'] = \Drupal::currentUser()->id();
    }
    $results = $this->call(self::CAT_API_GET_IMAGES, $params);
    $images = $results['data']['images']['image'];
    // Normalize return for only one result.
    if (isset($images['url'])) {
      $images = [0 => $images];
    }
    return $images;
  }

  /**
   * Getter for Allowed Image Types.
   */
  public function getImageTypes() {
    $formats = $this->config->get('cat_api_formats');
    if (empty($formats)) {
      return 'jpg,gif,png';
    }
    foreach ($formats as $key => $value) {
      if ($value === 0) {
        unset($formats[$key]);
      }
    }
    return implode(',', $formats);
  }

  /**
   * Get a complete list of Categories.
   *
   * @return array
   *   An array with existing categories.
   */
  public function getCategories() {
    $categories = $this->call(self::CAT_API_CATEGORIES);
    return $categories['data']['categories']['category'];
  }

  /**
   * Return the category name according to the category ID provided.
   *
   * @param string $id
   *   A CATegory id.
   *
   * @return string
   *   The name of the Category.
   */
  public function getCategoryName($id) {
    $name = '';
    $categories = $this->getCategories();
    foreach ($categories as $category) {
      if ($category['id'] === $id) {
        $name = $category['name'];
        break;
      }
    }
    return $name;
  }

  /**
   * Gets the Stats from an API Usage.
   *
   * @param string $key
   *   The API Key to check. Use default if not set.
   */
  public function getStats($key = '') {
    $params = [
      'api_key' => !empty($key) ? $key : $this->config->get('cat_api_key'),
    ];
    return $this->call(self::CAT_API_STATS, $params);
  }

  /**
   * Vote on a cat.
   *
   * @param string $id
   *   Id of the cat.
   * @param int $score
   *   Score of the cat.
   *
   * @return AjaxResponse
   *   An Ajax response with the new vote link.
   */
  public function vote($id, $score = 0) {
    if (!$this->config->get('cat_api_enable_vote')) {
      return new AjaxResponse();
    }
    $params = [
      'image_id' => $id,
      'score' => $score,
    ];
    if (\Drupal::currentUser()->isAuthenticated()) {
      $params['sub_id'] = \Drupal::currentUser()->id();
    }
    // TODO: error handling.
    $result = $this->call(self::CAT_API_VOTE, $params);
    $span = $this->getVoteLink($disabled = TRUE);
    $output = '<div id="cat-api-message">' . $this->t('Thank You For the Vote!') . '</div>';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#cat-api-message', $output))
      ->addCommand(new ReplaceCommand('#cat-api-block-vote-link', $span));
    return $response;
  }

  /**
   * Helper function to generate the Link for vote.
   *
   * @param string $id
   *   The image ID.
   * @param int $score
   *   The score you want to apply for the cat.
   * @param bool $disabled
   *   If true, generate a span to avoid voting.
   *
   * @return string
   *   A Link/Span Markup.
   */
  public function getVoteLink($id = '', $score = 10, $disabled = FALSE) {
    if (!$this->config->get('cat_api_enable_vote')) {
      return '';
    }
    if ($disabled) {
      return '<span>' . $this->t('You already voted for this cat!') . '</span>';
    }
    $options = [
      'attributes' => [
        'class' => [
          'use-ajax',
          'vote-link'
        ],
        'id' => 'cat-api-block-vote-link'
      ]
    ];
    $route = 'cat_api.vote_callback';
    $params = ['id' => $id, 'score' => $score];
    $url = Url::fromRoute($route, $params, $options);
    return Link::fromTextAndUrl($this->t('Vote'), $url)->toString();
  }

  /**
   * Add removes Cat from favorites.
   *
   * @param string $id
   *   Id of the cat.
   * @param string $action
   *   Use "add" to Add the cat, and "remove" to remove the cat.
   *
   * @return AjaxResponse
   *   An Ajax response with the new favorite link.
   */
  public function favorite($id, $action = 'add') {
    if (!$this->config->get('cat_api_enable_favorite')) {
      return new AjaxResponse();
    }
    $params = [
      'image_id' => $id,
      'action' => $action,
    ];
    if (\Drupal::currentUser()->isAuthenticated()) {
      $params['sub_id'] = \Drupal::currentUser()->id();
    }
    // TODO: error handling.
    $result = $this->call(self::CAT_API_FAVORITE, $params);
    $message = $this->t('Cat added to your favourites CATalog.');
    if ($action === 'remove') {
      $message = $this->t('This Cat is not a favourite anymore.');
    }
    $link = $this->getFavoriteLink($id, ($action !== 'remove'));
    $output = '<div id="cat-api-message">' . $message . '</div>';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#cat-api-message', $output))
      ->addCommand(new ReplaceCommand('#cat-api-block-favorite-link', $link));
    return $response;
  }

  /**
   * Helper function to generate the Link for Favorite.
   *
   * @param string $id
   *   The image ID.
   * @param bool $remove
   *   If true, generate a link to remove from favorites.
   *
   * @return string
   *   A Link/Span Markup.
   */
  public function getFavoriteLink($id, $remove = FALSE) {
    if (!$this->config->get('cat_api_enable_favorite')) {
      return '';
    }
    $options = [
      'attributes' => [
        'class' => [
          'use-ajax',
          'favorite-link'
        ],
        'id' => 'cat-api-block-favorite-link'
      ]
    ];
    $params = ['id' => $id, 'action' => 'add'];
    $text = 'Add to favorites';
    if ($remove) {
      $text = 'Remove from favorites';
      $params['action'] = 'remove';
    }
    $url = Url::fromRoute('cat_api.favorite_callback', $params, $options);
    return Link::fromTextAndUrl($this->t($text), $url)->toString();
  }

  /**
   * Report a Cat so your API Key doesn't show it anymore.
   *
   * @param string $id
   *   Id of the cat.
   * @param string $reason
   *   The reason for reporting this cat.
   *
   * @return AjaxResponse
   *   An Ajax response to remove the report link.
   */
  public function report($id, $reason = '') {
    if (!$this->config->get('cat_api_enable_report')) {
      return new AjaxResponse();
    }
    $params = ['image_id' => $id];
    // TODO: Add reason system from config.
    if (!empty($reason)) {
      $params['reason'] = $reason;
    }
    if (\Drupal::currentUser()->isAuthenticated()) {
      $params['sub_id'] = \Drupal::currentUser()->id();
    }
    // TODO: error handling.
    $result = $this->call(self::CAT_API_REPORT, $params);
    $message = $this->t('Cat taken to the Cat Watcher.');
    $output = '<div id="cat-api-message">' . $message . '</div>';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#cat-api-message', $output))
      ->addCommand(new ReplaceCommand('#cat-api-block-report-link', ''));
    return $response;
  }

  /**
   * Helper function to generate the Link for vote.
   *
   * @param string $id
   *   The image ID.
   * @param string $reason
   *   The string with the reason.
   *
   * @return string
   *   A Link/Span Markup.
   */
  public function getReportLink($id, $reason = '') {
    if (!$this->config->get('cat_api_enable_report')) {
      return '';
    }
    $options = [
      'attributes' => [
        'class' => [
          'use-ajax',
          'report-link'
        ],
        'id' => 'cat-api-block-report-link'
      ]
    ];
    $params = ['id' => $id];
    if (!empty($reason)) {
      $params['reason'] = $reason;
    }
    $text = 'Report this cat';
    $url = Url::fromRoute('cat_api.report_callback', $params, $options);
    return Link::fromTextAndUrl($this->t($text), $url)->toString();
  }

}
