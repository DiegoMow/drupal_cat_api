<?php
/**
 * @file
 * Class implementation of The Cat API.
 */

namespace Drupal\cat_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

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
    //TODO: Support for Cat Category Lists.
    //TODO: Support for Sizes of images.

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

}