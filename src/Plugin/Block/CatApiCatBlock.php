<?php
/**
 * @file
 * Contains Drupal\cat_api\Plugin\Block\CatApiCatBlock.
 */

namespace Drupal\cat_api\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides the Cat Api Block.
 *
 * @Block(
 *   id = "cat_api_block",
 *   admin_label = @Translation("Cat API Block"),
 * )
 */
class CatApiCatBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    // TODO: Render Favorites, and other stuff from api.
    $image = \Drupal::service('cat_api.api')->getImage();
    $link = $this->voteLink('Vote', $image['id'], 10, isset($image['score']));
    $markup = '<div id="cat-api-message"></div><img src="' . $image['url'] . '" />' . $link;
    return [
      '#markup' => $markup,
    ];
  }

  public function voteLink(string $text, string $id, int $score = 10, bool $disabled = false) {
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
    if ($disabled) {
      $text = 'You already voted for this cat!';
      $route = '<none>';
      $options['attributes']['class'][] = 'disabled';
    }
    $params = ['id' => $id, 'score' => $score];
    $url = Url::fromRoute($route, $params, $options);
    return Link::fromTextAndUrl($this->t($text), $url)->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access cat api content');
  }
}
