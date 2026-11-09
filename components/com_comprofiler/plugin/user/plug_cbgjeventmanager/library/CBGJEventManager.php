<?php
/**
 * CB GJ Event Manager Plugin
 * @version $Id: $
 * @package CBGJEventManager
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\GJEventManager;

use CB\Database\Table\UserTable;
use CB\Plugin\GroupJive\Helper\GroupHelper;
use CB\Plugin\GroupJive\Helper\RouteHelper;
use CB\Plugin\GroupJive\Table\GroupTable;
use CB\Plugin\GroupJiveEvents\Helper\EventHelper;
use CB\Plugin\GroupJiveEvents\Table\EventTable;
use CBLib\Application\Application;
use CBLib\Registry\Registry;

\defined( 'CBLIB' ) or die();

/**
 * Authorization + data helpers for the CB GJ Event Manager plugin.
 *
 * Membership is validated only at assignment time (not a maintained invariant); the plugin never
 * inspects or alters an event's existing ownership.
 *
 * Requires GroupJive 4. GroupJive 4.0.0 removed the CBGroupJive and CBGroupJiveEvents classes that
 * 3.x offered; their replacements are the Helper classes used here. On GroupJive 3.x those classes
 * are absent, so every check below answers "no" and the plugin stays inert instead of failing.
 */
class CBGJEventManager
{
	public const ELEMENT				=	'cbgjeventmanager';
	public const OWNER_FIELD			=	'cbgjeventmanager_owner_id';

	/**
	 * This plugin's own parameters.
	 *
	 * @return Registry
	 */
	public static function getParams(): Registry
	{
		global $_PLUGINS;

		static $params	=	null;

		if ( $params === null ) {
			$params		=	new Registry();
			$plugin		=	$_PLUGINS->getLoadedPlugin( 'user', self::ELEMENT );

			if ( $plugin ) {
				$params->load( $plugin->params );
			}
		}

		return $params;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled(): bool
	{
		return self::getParams()->getInt( 'enabled', 1 ) === 1;
	}

	/**
	 * Minimum effective group status permitted to assign owners (2 = Moderator, 3 = Admin).
	 *
	 * @return int
	 */
	public static function getOwnerAssignLevel(): int
	{
		$level	=	self::getParams()->getInt( 'owner_assign_level', 2 );

		return ( $level === 3 ? 3 : 2 );
	}

	/**
	 * @return int
	 */
	public static function getPickerMinChars(): int
	{
		return max( 1, self::getParams()->getInt( 'picker_min_chars', 2 ) );
	}

	/**
	 * @return int
	 */
	public static function getPickerMaxResults(): int
	{
		return min( 100, max( 1, self::getParams()->getInt( 'picker_max_results', 20 ) ) );
	}

	/**
	 * Resolve the acting user from the CB session (the gj_onBeforeUpdateEvent hook receives no user).
	 *
	 * @return UserTable
	 */
	public static function getActor(): UserTable
	{
		return \CBuser::getMyUserDataInstance();
	}

	/**
	 * The user's name as CB is configured to format it (the formatname field's value), as plain text.
	 *
	 * The formatname field's getField() supports only output formats such as html, which may wrap the
	 * name in a profile link; any other format logs a warning and returns this same value.
	 *
	 * UserTable::getFormattedName() returns the name already passed through htmlspecialchars() with
	 * PHP's default flags, so "O'Shea" arrives as "O&#039;Shea". This undoes that one layer. Callers
	 * escape the result for their own output: htmlspecialchars() in HTML, textContent in the picker.
	 *
	 * @param int $userId
	 * @return string  '' when the user does not exist.
	 */
	public static function getDisplayName( int $userId ): string
	{
		if ( $userId <= 0 ) {
			return '';
		}

		$user	=	\CBuser::getUserDataInstance( $userId );

		if ( ! $user->getInt( 'id', 0 ) ) {
			return '';
		}

		return trim( htmlspecialchars_decode( (string) $user->getFormattedName(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) );
	}

	/**
	 * Whether the GroupJive 4 helpers this plugin calls can be loaded. False on GroupJive 3.x, which
	 * has none of them, and when GroupJive is not installed.
	 *
	 * @return bool
	 */
	public static function isGroupJiveSupported(): bool
	{
		static $supported	=	null;

		if ( $supported === null ) {
			$supported		=	class_exists( GroupHelper::class ) && class_exists( RouteHelper::class );
		}

		return $supported;
	}

	/**
	 * Whether the user is a CB global moderator. GroupJive 3.x wrapped this same call as
	 * CBGroupJive::isModerator().
	 *
	 * @param int $userId
	 * @return bool
	 */
	public static function isGlobalModerator( int $userId ): bool
	{
		return Application::User( $userId )->isGlobalModerator();
	}

	/**
	 * The one check the plugin adds on top of native enforcement: actor is a CB global moderator
	 * or has sufficient effective group status.
	 *
	 * @param UserTable  $actor
	 * @param GroupTable $group
	 * @return bool
	 */
	public static function canManageEventOwner( $actor, $group ): bool
	{
		if ( ( ! self::isGroupJiveSupported() ) || ( ! ( $actor instanceof UserTable ) ) || ( ! ( $group instanceof GroupTable ) ) ) {
			return false;
		}

		if ( ! $actor->getInt( 'id', 0 ) ) {
			return false;
		}

		if ( self::isGlobalModerator( $actor->getInt( 'id', 0 ) ) ) {
			return true;
		}

		return (int) GroupHelper::getGroupStatus( $actor, $group ) >= self::getOwnerAssignLevel();
	}

	/**
	 * Whether to offer the "Change Owner" control (existing events only; excludes global moderators,
	 * who already have CB's native inline Owner field).
	 *
	 * @param UserTable  $actor
	 * @param GroupTable $group
	 * @param EventTable $event
	 * @return bool
	 */
	public static function shouldOfferOwnerControl( $actor, $group, $event ): bool
	{
		if ( ( ! self::isEnabled() ) || ( ! self::isGroupJiveSupported() ) ) {
			return false;
		}

		if ( ( ! ( $actor instanceof UserTable ) ) || ( ! ( $group instanceof GroupTable ) ) || ( ! ( $event instanceof EventTable ) ) ) {
			return false;
		}

		if ( ( $event->getInt( 'id', 0 ) <= 0 ) || ( ! $actor->getInt( 'id', 0 ) ) ) {
			return false;
		}

		if ( self::isGlobalModerator( $actor->getInt( 'id', 0 ) ) ) {
			return false;
		}

		return (int) GroupHelper::getGroupStatus( $actor, $group ) >= self::getOwnerAssignLevel();
	}

	/**
	 * Parameters of the GroupJive Events integration plugin (for the search route's native-equivalent
	 * access check). Mirrors GroupJive's own integration-plugin lookup.
	 *
	 * @return null|Registry
	 */
	public static function getEventsPluginParams(): ?Registry
	{
		global $_PLUGINS;

		$_PLUGINS->loadPluginGroup( 'user/plug_cbgroupjive/plugins' );

		$eventsPlugin	=	$_PLUGINS->getLoadedPlugin( 'user/plug_cbgroupjive/plugins', 'cbgroupjiveevents' );

		if ( ! $eventsPlugin ) {
			return null;
		}

		$params			=	new Registry();
		$params->load( $eventsPlugin->params );

		return $params;
	}

	/**
	 * Whether the GroupJive + GroupJive Events dependency is present and autoloadable. Used by the
	 * plugin-owned search route to fail closed (controlled JSON) rather than fataling if the plugin is
	 * enabled while GroupJive Events is absent/disabled. The trigger hooks don't need this — they only
	 * fire when GroupJive itself is running.
	 *
	 * @return bool
	 */
	public static function isGroupJiveEventsAvailable(): bool
	{
		global $_PLUGINS;

		$_PLUGINS->loadPluginGroup( 'user/plug_cbgroupjive/plugins' );

		if ( ! $_PLUGINS->getLoadedPlugin( 'user/plug_cbgroupjive/plugins', 'cbgroupjiveevents' ) ) {
			return false;
		}

		return self::isGroupJiveSupported() && class_exists( EventHelper::class );
	}

	/**
	 * Load an event by id. Call only after isGroupJiveEventsAvailable() has returned true.
	 *
	 * @param int $id
	 * @return EventTable  An empty table when the event does not exist.
	 */
	public static function getEvent( int $id ): EventTable
	{
		return EventHelper::getEvent( $id );
	}

	/**
	 * The current page as a base64 return URL, the form GroupJive's own links carry.
	 *
	 * @return null|string
	 */
	public static function getReturn(): ?string
	{
		return RouteHelper::getReturn();
	}

	/**
	 * Route guard for the plugin-owned owner/search endpoint. Unlike the trigger hooks, this route is
	 * NOT downstream of native GroupJive access enforcement, so it mirrors native showEventEdit access.
	 *
	 * @param UserTable  $actor
	 * @param EventTable $event
	 * @return bool
	 */
	public static function canAccessEventOwnerSearch( $actor, $event ): bool
	{
		if ( ( ! self::isEnabled() ) || ( ! self::isGroupJiveSupported() ) ) {
			return false;
		}

		if ( ( ! ( $actor instanceof UserTable ) ) || ( ! ( $event instanceof EventTable ) ) ) {
			return false;
		}

		if ( ( $event->getInt( 'id', 0 ) <= 0 ) || ( ! $actor->getInt( 'id', 0 ) ) ) {
			return false;
		}

		$group		=	$event->group();

		if ( ! $group->getInt( 'id', 0 ) ) {
			return false;
		}

		if ( ! GroupHelper::canAccessGroup( $group, $actor ) ) {
			return false;
		}

		if ( ! self::isGlobalModerator( $actor->getInt( 'id', 0 ) ) ) {
			$eventsParams	=	self::getEventsPluginParams();

			if ( $eventsParams === null ) {
				return false;
			}

			$viewLevels		=	Application::User( $actor->getInt( 'id', 0 ) )->getAuthorisedViewLevels();

			if ( ! \in_array( $eventsParams->getInt( 'groups_events_access', 1 ), $viewLevels, true ) ) {
				return false;
			}

			if ( ! $group->category()->params()->getBool( 'events', true ) ) {
				return false;
			}
		}

		return self::canManageEventOwner( $actor, $group );
	}

	/**
	 * Assignment-time target validation: effective member (membership row status >= 1, or the group
	 * owner) AND an active, usable CB/Joomla account.
	 *
	 * @param int        $targetUserId
	 * @param GroupTable $group
	 * @return bool
	 */
	public static function isAssignableOwner( $targetUserId, $group ): bool
	{
		global $_CB_database;

		$targetUserId	=	(int) $targetUserId;
		$groupId		=	$group->getInt( 'id', 0 );

		if ( ( $targetUserId <= 0 ) || ( ! $groupId ) ) {
			return false;
		}

		$query	=	'SELECT j.' . $_CB_database->NameQuote( 'id' )
				.	"\n FROM " . $_CB_database->NameQuote( '#__users' ) . ' AS j'
				.	"\n INNER JOIN " . $_CB_database->NameQuote( '#__comprofiler' ) . ' AS cb'
				.		' ON cb.' . $_CB_database->NameQuote( 'id' ) . ' = j.' . $_CB_database->NameQuote( 'id' )
				.	"\n LEFT JOIN " . $_CB_database->NameQuote( '#__groupjive_users' ) . ' AS gu'
				.		' ON gu.' . $_CB_database->NameQuote( 'user_id' ) . ' = j.' . $_CB_database->NameQuote( 'id' )
				.		' AND gu.' . $_CB_database->NameQuote( 'group' ) . ' = ' . (int) $groupId
				.		' AND gu.' . $_CB_database->NameQuote( 'status' ) . ' >= 1'
				.	"\n WHERE j." . $_CB_database->NameQuote( 'id' ) . ' = ' . (int) $targetUserId
				.	"\n AND j." . $_CB_database->NameQuote( 'block' ) . ' = 0'
				.	"\n AND cb." . $_CB_database->NameQuote( 'approved' ) . ' = 1'
				.	"\n AND cb." . $_CB_database->NameQuote( 'confirmed' ) . ' = 1'
				.	"\n AND cb." . $_CB_database->NameQuote( 'banned' ) . ' = 0'
				.	"\n AND ( gu." . $_CB_database->NameQuote( 'user_id' ) . ' IS NOT NULL'
				.		' OR j.' . $_CB_database->NameQuote( 'id' ) . ' = ' . (int) $group->getInt( 'user_id', 0 ) . ' )';
		$_CB_database->setQuery( $query );

		return (bool) $_CB_database->loadResult();
	}

	/**
	 * Search effective, active members of a group by name/username for the owner picker.
	 *
	 * @param GroupTable $group
	 * @param string     $search
	 * @param int        $limit
	 * @return array[] each: [ 'id' => int, 'name' => string ]
	 */
	public static function searchGroupMembers( $group, $search, $limit ): array
	{
		global $_CB_database;

		$groupId	=	$group->getInt( 'id', 0 );

		if ( ! $groupId ) {
			return array();
		}

		// getEscaped( $search, true ) escapes both SQL quotes and LIKE metacharacters (% and _) via
		// addcslashes; see MysqlDatabaseDriver::getEscaped() / Joomla escape($text, true).
		$like		=	$_CB_database->Quote( '%' . $_CB_database->getEscaped( $search, true ) . '%', false );

		$query		=	'SELECT j.' . $_CB_database->NameQuote( 'id' ) . ' AS ' . $_CB_database->NameQuote( 'id' )
					.		', j.' . $_CB_database->NameQuote( 'name' ) . ' AS ' . $_CB_database->NameQuote( 'name' )
					.	"\n FROM " . $_CB_database->NameQuote( '#__users' ) . ' AS j'
					.	"\n INNER JOIN " . $_CB_database->NameQuote( '#__comprofiler' ) . ' AS cb'
					.		' ON cb.' . $_CB_database->NameQuote( 'id' ) . ' = j.' . $_CB_database->NameQuote( 'id' )
					.	"\n LEFT JOIN " . $_CB_database->NameQuote( '#__groupjive_users' ) . ' AS gu'
					.		' ON gu.' . $_CB_database->NameQuote( 'user_id' ) . ' = j.' . $_CB_database->NameQuote( 'id' )
					.		' AND gu.' . $_CB_database->NameQuote( 'group' ) . ' = ' . (int) $groupId
					.		' AND gu.' . $_CB_database->NameQuote( 'status' ) . ' >= 1'
					.	"\n WHERE j." . $_CB_database->NameQuote( 'block' ) . ' = 0'
					.	"\n AND cb." . $_CB_database->NameQuote( 'approved' ) . ' = 1'
					.	"\n AND cb." . $_CB_database->NameQuote( 'confirmed' ) . ' = 1'
					.	"\n AND cb." . $_CB_database->NameQuote( 'banned' ) . ' = 0'
					.	"\n AND ( gu." . $_CB_database->NameQuote( 'user_id' ) . ' IS NOT NULL'
					.		' OR j.' . $_CB_database->NameQuote( 'id' ) . ' = ' . (int) $group->getInt( 'user_id', 0 ) . ' )'
					.	"\n AND ( j." . $_CB_database->NameQuote( 'name' ) . ' LIKE ' . $like
					.		' OR j.' . $_CB_database->NameQuote( 'username' ) . ' LIKE ' . $like . ' )'
					.	"\n ORDER BY j." . $_CB_database->NameQuote( 'name' ) . ' ASC';
		$_CB_database->setQuery( $query, 0, (int) $limit );

		$rows		=	$_CB_database->loadObjectList();

		if ( ! $rows ) {
			return array();
		}

		$results	=	array();

		foreach ( $rows as $row ) {
			$id			=	(int) $row->id;
			$name		=	self::getDisplayName( $id );

			$results[]	=	array(
				'id'	=>	$id,
				'name'	=>	( $name !== '' ? $name : (string) $row->name ),
			);
		}

		return $results;
	}
}
