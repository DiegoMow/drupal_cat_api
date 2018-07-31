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

  public function call(string $api_endpoint, array $params = []) {
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

    return json_decode(json_encode($results), true);
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
  public function getImage(string $id = '') {
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
  public function getImages(int $qtde) {
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
    foreach ($formats as $key=>$value) {
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
  public function getCategoryName(string $id) {
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
  public function getStats(string $key = '') {
    $params = [
      'api_key' => !empty($key) ? $key : $this->config->get('cat_api_key'),
    ];
    return $this->call(self::CAT_API_STATS, $params);
  }

  public function vote(string $id, int $score = 0) {
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

  public function getVoteLink(string $id = '', int $score = 10, bool $disabled = false) {
    if ($disabled) {
      return '<span>' . $this->t('You already voted for this cat!') . '</span>';
    }
    $options = [
      'attributes'=> [
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

  public function favorite(string $id, string $action = 'add') {
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

  public function getFavoriteLink(string $id, bool $remove = false) {
    $options = [
      'attributes'=> [
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

  public function report(string $id, string $reason = '') {
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

  public function getReportLink(string $id, string $reason = '') {
    $options = [
      'attributes'=> [
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