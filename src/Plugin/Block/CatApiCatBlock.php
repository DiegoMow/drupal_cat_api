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
    return [
      '#markup' => $this->t('The cat Image Should Appear Here!!!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }
}
