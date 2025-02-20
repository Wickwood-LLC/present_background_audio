<?php

namespace Drupal\present_background_audio;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\present\Entity\Presentation;

/**
 * Defines the access control handler for the presentation entity type.
 *
 * @see \Drupal\prsent\Entity\Presentation.
 */
class PresentationAccessCheck {

  /**
   * Checks access for the audio sync studio.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\present\Entity\Presentation $presentation
   *   The presentation entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function audioGuideAcess(AccountInterface $account, Presentation $presentation): AccessResultInterface {
    if ($account->hasPermission('administer presentations')) {
      $plugins_enabled = $presentation->getPlugins();
      if (in_array('background_audio', $plugins_enabled)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }
}