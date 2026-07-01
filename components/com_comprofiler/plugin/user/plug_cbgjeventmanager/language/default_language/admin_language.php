<?php
/**
 * CB GJ Event Manager Default (English) language file - Backend
 * @version $Id:$
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

/**
 * WARNING:
 * Do not make changes to this file as it will be over-written when you upgrade CB.
 * To localize you need to create your own CB language plugin and make changes there.
 */

defined( 'CBLIB' ) or die();

return array(
	'CBGJEVENTMANAGER_TAB_GENERAL'						=>	'General',
	'CBGJEVENTMANAGER_OPTION_ENABLE'					=>	'Enable',
	'CBGJEVENTMANAGER_OPTION_DISABLE'					=>	'Disable',
	'CBGJEVENTMANAGER_PARAM_ENABLED_LABEL'				=>	'Enabled',
	'CBGJEVENTMANAGER_PARAM_ENABLED_DESC'				=>	'Enable or disable the "Change Owner" control for group Moderators/Admins.',
	'CBGJEVENTMANAGER_PARAM_OWNER_ASSIGN_LEVEL_LABEL'	=>	'Owner Assign Level',
	'CBGJEVENTMANAGER_PARAM_OWNER_ASSIGN_LEVEL_DESC'	=>	'Minimum group status allowed to reassign an event owner. CB global moderators always use the native inline Owner field and are unaffected.',
	'CBGJEVENTMANAGER_OPTION_LEVEL_MODERATOR'			=>	'Moderator (>= 2)',
	'CBGJEVENTMANAGER_OPTION_LEVEL_ADMIN'				=>	'Admin (>= 3)',
	'CBGJEVENTMANAGER_PARAM_PICKER_MIN_CHARS_LABEL'		=>	'Picker Minimum Characters',
	'CBGJEVENTMANAGER_PARAM_PICKER_MIN_CHARS_DESC'		=>	'Number of characters that must be typed before the member search runs (also enforced server-side).',
	'CBGJEVENTMANAGER_PARAM_PICKER_MAX_RESULTS_LABEL'	=>	'Picker Maximum Results',
	'CBGJEVENTMANAGER_PARAM_PICKER_MAX_RESULTS_DESC'	=>	'Maximum number of members returned per search query.',
);
