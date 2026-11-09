<?php
/**
 * CB GJ Event Manager Plugin
 * @version $Id: $
 * @package CBGJEventManager
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

use CB\Plugin\GJEventManager\CBGJEventManager;
use CBLib\Application\Application;

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

/**
 * Plugin-owned front-end route. Currently only the owner-picker JSON search endpoint
 * (action=owner&func=search). Unlike the GroupJive trigger hooks, this route is NOT downstream of
 * native event-access enforcement, so it re-checks the form token and full event access itself.
 */
class CBplug_cbgjeventmanager extends cbPluginHandler
{
	/**
	 * @param null|CB\Database\Table\TabTable  $tab
	 * @param null|CB\Database\Table\UserTable $user
	 * @param null|int                          $ui
	 * @param null|array                        $postdata
	 * @return void
	 */
	public function getCBpluginComponent( $tab, $user, $ui, $postdata ): void
	{
		$action		=	$this->getInput()->getCmd( 'action', '' );
		$function	=	$this->getInput()->getCmd( 'func', '' );

		if ( ( $action === 'owner' ) && ( $function === 'search' ) ) {
			$this->ownerSearch();
			return;
		}

		$this->outputJson( array( 'error' => 'unknown_action' ), 400 );
	}

	/**
	 * JSON typeahead: return effective, active members of the event's group matching the query.
	 *
	 * @return void
	 */
	private function ownerSearch(): void
	{
		if ( ! Application::Session()->checkFormToken( 'request', 0 ) ) {
			$this->outputJson( array( 'error' => 'invalid_token' ), 403 );
		}

		if ( ! CBGJEventManager::isEnabled() ) {
			$this->outputJson( array( 'error' => 'disabled' ), 403 );
		}

		$actor		=	CBGJEventManager::getActor();

		if ( $actor->getInt( 'id', 0 ) < 1 ) {
			$this->outputJson( array( 'error' => 'not_authorized' ), 403 );
		}

		// Fail closed (controlled JSON) rather than fatal if GroupJive Events is absent or disabled, or
		// GroupJive is older than 4, before touching any GroupJive Events class.
		if ( ! CBGJEventManager::isGroupJiveEventsAvailable() ) {
			$this->outputJson( array( 'error' => 'dependency_unavailable' ), 503 );
		}

		$event		=	CBGJEventManager::getEvent( $this->getInput()->getInt( 'id', 0 ) );

		if ( ! $event->getInt( 'id', 0 ) ) {
			$this->outputJson( array( 'error' => 'not_found' ), 404 );
		}

		if ( ! CBGJEventManager::canAccessEventOwnerSearch( $actor, $event ) ) {
			$this->outputJson( array( 'error' => 'not_authorized' ), 403 );
		}

		$search		=	trim( $this->getInput()->getString( 'q', '' ) );

		// Enforce the minimum length server-side, independent of the client.
		if ( function_exists( 'mb_strlen' ) ? ( mb_strlen( $search ) < CBGJEventManager::getPickerMinChars() ) : ( strlen( $search ) < CBGJEventManager::getPickerMinChars() ) ) {
			$this->outputJson( array( 'results' => array() ) );
		}

		$results	=	CBGJEventManager::searchGroupMembers( $event->group(), $search, CBGJEventManager::getPickerMaxResults() );

		$this->outputJson( array( 'results' => $results ) );
	}

	/**
	 * Emit a JSON body and terminate, bypassing the CB/Joomla page template entirely.
	 *
	 * @param array $data
	 * @param int   $status
	 * @return void
	 */
	private function outputJson( array $data, int $status = 200 ): void
	{
		// Discard any component/template output captured so far, then emit only JSON and terminate,
		// bypassing the CB/Joomla page template entirely (same idiom as CB Activity's BaseController::ajax()).
		while ( @ob_end_clean() ) {}

		if ( ! headers_sent() ) {
			http_response_code( $status );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		echo json_encode( $data );

		exit();
	}
}
