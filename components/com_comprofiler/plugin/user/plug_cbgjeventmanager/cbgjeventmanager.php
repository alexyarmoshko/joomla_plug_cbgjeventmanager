<?php
/**
 * CB GJ Event Manager Plugin
 * @version $Id: $
 * @package CBGJEventManager
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

use CB\Plugin\GJEventManager\Trigger\EventsTrigger;
use CBLib\Core\AutoLoader;

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

global $_PLUGINS;

AutoLoader::registerExactMap( '%^CB/Plugin/GJEventManager/(.+)%i', __DIR__ . '/library/$1.php' );

// GroupJive fires its gj_* triggers through the CB plugin handler; register our handlers for the
// three integration points. Handlers no-op unless the plugin is enabled and GroupJive 4 is present.
$_PLUGINS->registerFunction( 'gj_onDisplayEvent', 'onDisplayEvent', EventsTrigger::class );
$_PLUGINS->registerFunction( 'gj_onAfterDisplayEventEdit', 'onAfterDisplayEventEdit', EventsTrigger::class );
$_PLUGINS->registerFunction( 'gj_onBeforeUpdateEvent', 'onBeforeUpdateEvent', EventsTrigger::class );
