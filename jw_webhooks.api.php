<?php
declare(strict_types=1);

/**
 * Allow modules to register their need to be updated on certain JW events.
 * This should be paired with an implementation of hook_jw_webhooks_receive.
 *
 * @see https://developer.jwplayer.com/jwplayer/docs/learn-about-webhooks#section-create-a-webhook
 *
 * @returns string[]
 *   A list of webhook events to register a need for. Available events as of
 *   writing: ['media_available', 'conversions_complete', 'media_updated',
 *   'media_reuploaded', 'media_deleted'].
 *
 * @see https://developer.jwplayer.com/jwplayer/reference#post_v2-webhooks for
 *   available events.
 */
function hook_jw_webhooks_register () : array
{
  return ['media_update', 'media_deleted'];
}


/**
 * Receive an event notification form JW. Be sure to register which events you
 * need to be notified of with a hook_jw_webhooks_register() implementation.
 *
 * @param RightThisMinute\JWPlatform\Management\v2\request\WebhooksEventBody $event
 */
function hook_jw_webhooks_receive
  (RightThisMinute\JWPlatform\Management\v2\request\WebhooksEventBody $event)
  : void
{
  // Do stuff with $event
}
