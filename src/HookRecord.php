<?php


namespace Drupal\jw_webhooks;


class HookRecord
{
  /**
   * The hook's ID at JW.
   * @var string
   */
  public $id;

  /**
   * The secret needed to decode requests created from this hook.
   * @var string
   */
  public $secret;

  /**
   * Timestamp of when this record was created.
   * @var int
   */
  public $created;


  public function __construct (string $id, string $secret, string $created)
  {
    $this->id = $id;
    $this->secret = $secret;
    $this->created = $created;
  }
}
