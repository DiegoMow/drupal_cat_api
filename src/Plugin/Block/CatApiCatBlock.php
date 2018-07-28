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
    $cat_api = \Drupal::service('cat_api.api');
    $image = $cat_api->getImage();
    $links = [
      $cat_api->getFavoriteLink($image['id'], isset($image['favourite'])),
      $cat_api->getVoteLink($image['id'], 10, isset($image['score']))
    ];
    $markup = '<div id="cat-api-message"></div><img src="' . $image['url'] . '" />' . implode('<br/>', $links);
    return [
      '#markup' => $markup,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access cat api content');
  }
}
