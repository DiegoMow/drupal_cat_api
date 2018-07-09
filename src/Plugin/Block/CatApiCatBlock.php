<?php
/**
 * @file
 * Contains Drupal\cat_api\Plugin\Block\CatApiCatBlock.
 */

namespace Drupal\cat_api\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

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
    $image = \Drupal::service('cat_api.api')->getImages(1);
    $markup = '<img src="' . $image[0]['url'] . '" />';
    return [
      '#markup' => $this->t($markup),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access cat api content');
  }
}
