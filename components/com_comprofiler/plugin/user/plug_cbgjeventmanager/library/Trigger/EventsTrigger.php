<?php
/**
 * CB GJ Event Manager Plugin
 * @version $Id: $
 * @package CBGJEventManager
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\GJEventManager\Trigger;

use CB\Plugin\GJEventManager\CBGJEventManager;
use CB\Plugin\GroupJive\CBGroupJive;
use CBLib\Application\Application;
use CBLib\Language\CBTxt;

\defined( 'CBLIB' ) or die();

/**
 * GroupJive Events integration points. Every handler no-ops unless the plugin is enabled and the
 * acting user is an eligible group Moderator/Admin (never a CB global moderator, who has the native
 * inline Owner field).
 */
class EventsTrigger extends \cbPluginHandler
{
	/**
	 * Add a "Change Owner" entry to an existing event's menu, linking to the native event edit form.
	 *
	 * MUST always return an empty string: the GroupJive Events module also fires this trigger and
	 * renders the return value (not $menu) as card content, so any non-empty return would leak there.
	 *
	 * @param \CB\Plugin\GroupJiveEvents\Table\EventTable $row
	 * @param array                                       $menu
	 * @param \CB\Plugin\GroupJive\Table\GroupTable       $group
	 * @param \CB\Database\Table\UserTable                $user
	 * @return string  Always ''.
	 */
	public function onDisplayEvent( &$row, &$menu, $group, $user )
	{
		global $_CB_framework;

		if ( ! CBGJEventManager::shouldOfferOwnerControl( $user, $group, $row ) ) {
			return '';
		}

		$url		=	$_CB_framework->pluginClassUrl( 'cbgroupjiveevents', true, array(
			'action'	=>	'events',
			'func'		=>	'edit',
			'id'		=>	$row->getInt( 'id', 0 ),
			'return'	=>	CBGroupJive::getReturn(),
		) );

		$menu[]		=	'<a href="' . $url . '" class="dropdown-item"><span class="fa fa-user-o"></span> '
					.	CBTxt::T( 'CBGJEVENTMANAGER_CHANGE_OWNER', 'Change Owner' ) . '</a>';

		return '';
	}

	/**
	 * Insert the owner picker into the native event edit form (existing events only), before the
	 * closing </form> so the hidden field is submitted with the native save.
	 *
	 * @param string                                      $return  Rendered form HTML (by reference)
	 * @param \CB\Plugin\GroupJiveEvents\Table\EventTable $row
	 * @param array                                       $input
	 * @param \CB\Plugin\GroupJive\Table\GroupTable       $group
	 * @param \CB\Database\Table\UserTable                $user
	 * @return void
	 */
	public function onAfterDisplayEventEdit( &$return, $row, $input, $group, $user ): void
	{
		if ( ! CBGJEventManager::shouldOfferOwnerControl( $user, $group, $row ) ) {
			return;
		}

		// Insert the picker just before the native submit-button row so it sits with the other fields,
		// above "Update Event"/"Cancel". Anchor on the submit button (`gjButtonSubmit`) and back up to its
		// enclosing row wrapper; fall back to before the closing </form> if the markup changes.
		$pos		=	false;
		$submitPos	=	strpos( $return, 'gjButtonSubmit' );

		if ( $submitPos !== false ) {
			$pos	=	strrpos( substr( $return, 0, $submitPos ), '<div class="row no-gutters">' );
		}

		if ( $pos === false ) {
			$pos	=	strrpos( $return, '</form>' );
		}

		if ( $pos === false ) {
			return;
		}

		$return	=	substr( $return, 0, $pos ) . $this->getOwnerPickerHtml( $row ) . substr( $return, $pos );
	}

	/**
	 * Assign the selected owner just before the native EventTable::store() persists the event.
	 * Fires from multiple store() callers (including the token-less publish path), so it re-verifies
	 * the form token itself and drops only the owner change on any failed check.
	 *
	 * @param \CB\Plugin\GroupJiveEvents\Table\EventTable $event  (by reference)
	 * @param \CB\Plugin\GroupJiveEvents\Table\EventTable $old
	 * @return void
	 */
	public function onBeforeUpdateEvent( &$event, $old ): void
	{
		if ( ! CBGJEventManager::isEnabled() ) {
			return;
		}

		$target	=	(int) Application::Input()->getInt( 'post/' . CBGJEventManager::OWNER_FIELD, 0 );

		if ( $target <= 0 ) {
			// No plugin field submitted -> leave existing owner untouched (FR5).
			return;
		}

		// The hook also fires from token-less callers (publish/unpublish); verify the form token here.
		if ( ! Application::Session()->checkFormToken( 'post', 0 ) ) {
			return;
		}

		$actor	=	CBGJEventManager::getActor();

		if ( $actor->getInt( 'id', 0 ) < 1 ) {
			return;
		}

		$group	=	$event->group();

		if ( ! $group->getInt( 'id', 0 ) ) {
			return;
		}

		if ( ! CBGJEventManager::canManageEventOwner( $actor, $group ) ) {
			return;
		}

		if ( ! CBGJEventManager::isAssignableOwner( $target, $group ) ) {
			return;
		}

		$event->set( 'user_id', $target );
	}

	/**
	 * Build the owner-picker form row injected into the native edit form.
	 *
	 * @param \CB\Plugin\GroupJiveEvents\Table\EventTable $row
	 * @return string
	 */
	private function getOwnerPickerHtml( $row ): string
	{
		global $_CB_framework, $_PLUGINS;

		$session		=	Application::Session();

		$searchUrl		=	$_CB_framework->pluginClassUrl( 'cbgjeventmanager', false, array(
			'action'	=>	'owner',
			'func'		=>	'search',
			'id'		=>	$row->getInt( 'id', 0 ),
		), 'raw' );

		$currentOwner	=	\CBuser::getInstance( $row->getInt( 'user_id', 0 ), false )->getField( 'formatname', null, 'text', 'none', 'list', 0, true );
		$currentOwner	=	trim( (string) $currentOwner );

		$plugin			=	$_PLUGINS->getLoadedPlugin( 'user', 'cbgjeventmanager' );
		$jsUrl			=	( $plugin ? $_PLUGINS->getPluginLivePath( $plugin ) . '/media/js/owner-picker.js' : '' );

		$help			=	( $currentOwner !== ''
							? CBTxt::T( 'CBGJEVENTMANAGER_CURRENT_OWNER', 'Current owner: [name]. Search and select a group member to reassign, or leave blank to keep.', array( '[name]' => $currentOwner ) )
							: CBTxt::T( 'CBGJEVENTMANAGER_PICK_OWNER', 'Search and select a group member to set as owner, or leave blank to keep the current owner.' ) );

		$html			=	'<div class="cbft_text cbtt_input form-group row no-gutters cb_form_line gjEventManagerOwnerRow">'
						.		'<label for="cbgjeventmanager_owner_search" class="col-form-label col-sm-3 pr-sm-2">' . CBTxt::T( 'CBGJEVENTMANAGER_CHANGE_OWNER', 'Change Owner' ) . '</label>'
						.		'<div class="cb_field col-sm-9">'
						.			'<div class="cbgjeventmanager-owner-picker" style="position: relative;"'
						.				' data-url="' . htmlspecialchars( $searchUrl ) . '"'
						.				' data-token-name="' . htmlspecialchars( $session->getFormTokenName() ) . '"'
						.				' data-token-value="' . htmlspecialchars( $session->getFormTokenValue() ) . '"'
						.				' data-min-chars="' . (int) CBGJEventManager::getPickerMinChars() . '">'
						.				'<input type="text" id="cbgjeventmanager_owner_search" class="form-control cbgjeventmanager-owner-search" autocomplete="off" placeholder="' . htmlspecialchars( CBTxt::T( 'CBGJEVENTMANAGER_SEARCH_PLACEHOLDER', 'Search members...' ) ) . '" />'
						.				'<input type="hidden" name="' . CBGJEventManager::OWNER_FIELD . '" id="' . CBGJEventManager::OWNER_FIELD . '" class="cbgjeventmanager-owner-id" value="" />'
						.				'<ul class="list-unstyled dropdown-menu w-100 cbgjeventmanager-owner-results" style="display: none;"></ul>'
						.				'<small class="form-text text-muted cbgjeventmanager-owner-selected"></small>'
						.			'</div>'
						.			'<small class="form-text text-muted">' . htmlspecialchars( $help ) . '</small>'
						.		'</div>'
						.	'</div>';

		if ( $jsUrl !== '' ) {
			$html		.=	'<script src="' . htmlspecialchars( $jsUrl ) . '"></script>';
		}

		return $html;
	}
}
