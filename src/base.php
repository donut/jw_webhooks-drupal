<?php
declare(strict_types=1);

namespace Drupal\jw_webhooks;


use RightThisMinute\JWPlatform\Management\v2\Client;
use function Functional\first;
use function Functional\map;
use RTM\Drupal\helpers\log;
use function RightThisMinute\JWPlatform\Management\v2\endpoint\webhooks\authenticate_and_parse_publish_request;
use function RightThisMinute\JWPlatform\Management\v2\endpoint\webhooks\webhook_id_of_publish_request_body;


/**
 * Return a runtime cached instance of the JW Management v2 client. Calling
 * this multiple times will return the same instance.
 *
 * @return Client
 */
function jw_client () : Client
{
  static $jw;

  if (!isset($jw)) {
    $secret = variable_get('jw_webhooks_api_secret');
    $site_id = variable_get('jw_webhooks_default_site_id');
    $jw = new Client($secret, $site_id);
  }

  return $jw;
}


/**
 * Get the path that JW should publish webhook requests to.
 *
 * @return string
 */
function get_receive_path () : string
{
  return variable_get
    ('jw_webhooks_receive_path', 'jw_webhooks/receive');
}


/**
 * Get the absolute URL that JW should publish webhook requests to.
 * @return string
 */
function get_receive_url () : string
{
  $path = get_receive_path();
  # JW requires an HTTPS URL.
  return url($path, ['absolute' => true, 'https' => true]);
}


/**
 * Returns the list of hooks created by this module.
 *
 * @return HookRecord[]
 */
function get_local_hook_records () : array
{
  $rows = db_select('jw_webhooks', 'hooks')
    ->fields('hooks')
    ->execute()->fetchAllAssoc('id');

  return map($rows, function($r){
    return new HookRecord($r->id, $r->secret, $r->created);
  });
}


/**
 * Delete the local record of a webhook.
 *
 * @param string $id
 */
function delete_local_hook_record (string $id) : void
{
  db_delete('jw_webhooks')
    ->condition('id', $id)
    ->execute();
}


/**
 * Record the details of a webhook created at JW.
 *
 * @param string $id
 * @param string $secret
 *
 * @throws \Exception
 */
function save_local_hook_record (string $id, string $secret) : void
{
  db_insert('jw_webhooks')
    ->fields(['id' => $id, 'secret' => $secret, 'created' => time()])
    ->execute();
}


/**
 * Handles a webhook publish request from JW.
 *
 * @see https://developer.jwplayer.com/jwplayer/docs/learn-about-webhooks
 */
function handle_publish_request () : void
{
  $body = file_get_contents('php://input');

  try {
    $id = webhook_id_of_publish_request_body($body);
  }
  catch (\Exception $exn) {
    log\error
      ( 'jw_webhooks'
      , 'Failed getting webhook ID from JW publish request: %exn'
      , ['%exn' => $exn, 'request body' => $body] );
    return;
  }

  $secret = db_select('jw_webhooks', 'hooks')
    ->fields('hooks', ['secret'])
    ->condition('id', $id)
    ->range(0, 1)
    ->execute()
    ->fetchCol();

  if (count($secret) === 0) {
    log\error
      ( 'jw_webhooks'
      , 'Missing local record of JW webhook [%id]'
      , ['%id' => $id] );
    return;
  }

  $secret = first($secret);

  $headers = getallheaders();
  $headers = array_change_key_case($headers, CASE_LOWER);

  if (!isset($headers['authorization'])) {
    log\error
      ( 'jw_webhooks'
      , 'Authorization header missing from JW publish request.'
      , ['headers' => $headers, 'body' => $body] );
    return;
  }

  try {
    $event = authenticate_and_parse_publish_request
      ($headers['authorization'], $secret, $body);
  }
  catch (\Exception $exn) {
    log\error
      ( 'jw_webhooks'
      , 'Failed authenticating or parsing JW publish request: %exn'
      , [ '%exn' => $exn
        , 'id' => $id
        , 'auth_header' => $headers['authorization']
        , 'body' => $body ]);
    return;
  }

  log\info
    ( 'jw_webhooks'
    , 'JW sent %event notice for %media_id.'
    , [ '%event' => $event->event
      , '%media_id' => $event->media_id
      , 'webhook ID' => $event->webhook_id
      , 'site ID' => $event->site_id
      , 'event time' => $event->event_time ]);

  module_invoke_all('jw_webhooks_receive', $event);
}
