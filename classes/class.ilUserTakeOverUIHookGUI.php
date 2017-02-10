<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once('./Services/UIComponent/classes/class.ilUIHookPluginGUI.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/UserTakeOver/classes/class.usrtoHelper.php');
require_once("./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/UserTakeOver/classes/class.ilUserTakeOverConfig.php");

/**
 * Class ilUserTakeOverUIHookGUI
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @author  Martin Studer <ms@studer-raimann.ch>
 * @version $Id$
 * @ingroup ServicesUIComponent
 */
class ilUserTakeOverUIHookGUI extends ilUIHookPluginGUI {

	/**
	 * @var array
	 */
	protected static $loaded = array();


	/**
	 * @param $key
	 *
	 * @return bool
	 */
	protected static function isLoaded($key) {
		return self::$loaded[$key] == 1;
	}


	/**
	 * @param $key
	 */
	protected static function setLoaded($key) {
		self::$loaded[$key] = 1;
	}


	/**
	 * @var int
	 */
	protected static $num = 0;


	/**
	 * @param       $a_comp
	 * @param       $a_part
	 * @param array $a_par
	 *
	 * @return array
	 */
	public function getHTML($a_comp, $a_part, $a_par = array()) {
		/**
		 * @var $ilCtrl     ilCtrl
		 * @var $tpl        ilTemplate
		 * @var $ilToolbar  ilToolbarGUI
		 * @var $rbacreview ilRbacReview
		 * @var $ilUser     ilObjUser
		 */
		if ($a_comp == 'Services/MainMenu') {
			if (!self::isLoaded('user_take_back')) {

				/////////// For the Demo Group //////////////////
				global $ilUser;
				/** @var ilUserTakeOverConfig $config */
				$config = ilUserTakeOverConfig::first();
				if(in_array($ilUser->getId(), $config->getDemoGroup()) && in_array($_SESSION[usrtoHelper::USR_ID_BACKUP], $config->getDemoGroup())) {
					$inner_html = "";
					foreach ($config->getDemoGroup() as $userId) {
						$user = new ilObjUser($userId);
						$b = "";
						if($userId == $ilUser->getId())
							$b = " style='font-weight: bold;'";
						$inner_html .= "<li>
								<a href=\"goto.php?target=usr_takeover_$userId\"$b>{$user->getPresentationTitle()}</a>
							</li>";
					}
					$html = "<a href='#' class='dropdown-toggle' data-toggle='dropdown'>
								<b>{$ilUser->getPresentationTitle()}</b>
							</a>
							<ul class=\"dropdown-menu pull-right\" role=\"menu\">
							$inner_html
						</ul>";

					// add list in ILIAS 5 and newer
					if (ilComponent::isVersionGreaterString(ILIAS_VERSION_NUMERIC, "4.9.999")) {
						$html = '<li>' . $html . '</li>';
					}

					self::setLoaded('user_take_back');

					return array( "mode" => ilUIHookPluginGUI::PREPEND, "html" => $html );
				}

				/////////////////// FOR EXITING THE VIEW ///////////////////////
				if ($_SESSION[usrtoHelper::USR_ID_BACKUP]) {
				$ilToolbar = new ilToolbarGUI();

					global $ilPluginAdmin;
					/**
					 * @var $ilPluginAdmin ilPluginAdmin
					 */
					if ($ilToolbar instanceof ilToolbarGUI) {
						if ($ilPluginAdmin->isActive(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'CtrlMainMenu')) {
							self::setLoaded('user_take_back');
							return array( "mode" => ilUIHookPluginGUI::KEEP, "html" => '' );
						}

						$ilUserTakeOverPlugin = ilUserTakeOverPlugin::getInstance();
						$link = 'goto.php?target=usr_takeback';

						$html = '<a class="MMInactive" id="leave_user_view" target="" href="' . $link . '">' . $ilUserTakeOverPlugin->txt("leave_user_view")
						        . '</a>';

						// add list in ILIAS 5 and newer
						if (ilComponent::isVersionGreaterString(ILIAS_VERSION_NUMERIC, "4.9.999")) {
							$html = '<li>' . $html . '</li>';
						}

						self::setLoaded('user_take_back');

						return array( "mode" => ilUIHookPluginGUI::PREPEND, "html" => $html );
					}
				}
			}
		}


		if (!self::isLoaded('user_take_over')) {
			global $rbacreview, $ilUser;
			// Only Administrators
			if (!in_array(2, $rbacreview->assignedGlobalRoles($ilUser->getId()))) {
				self::setLoaded('user_take_over');

				return false;
			}

			///////////////// IN THE USER ADMINISTRATION /////////////////
			if ($_GET['cmdClass'] == 'ilobjusergui' AND ($_GET['cmd'] == 'view' OR $_GET['cmd'] == 'edit')) {
				global $ilToolbar;
				if ($ilToolbar instanceof ilToolbarGUI) {
					$ilUserTakeOverPlugin = ilUserTakeOverPlugin::getInstance();
					$link = 'goto.php?target=usr_takeover_' . $_GET['obj_id'];
					// TODO: Refactor in ILIAS 5.0: ilLinkButton::getInstance(); and $ilToolbar->addButtonInstance();
					$ilToolbar->addButton($ilUserTakeOverPlugin->txt('take_over_user_view'), $link, '', '', 'take_over_user_view');
					self::setLoaded('user_take_over');
				}
			}

			//////////////TOP BAR /////////////
			if ($a_comp == 'Services/MainMenu') {
				global $ilCtrl;
				$plugin = new ilUserTakeOverPlugin();
				$template = $plugin->getTemplate("tpl.MMUserTakeOver.html", false, false);
				$template->setVariable("SEARCHUSERLINK", $ilCtrl->getLinkTargetByClass(array("ilUIPluginRouterGUI", "ilUserTakeOverConfigGUI"), "searchUsers"));
				$template->setVariable("TAKEOVERPREFIX", "goto.php?target=usr_takeover_");
				$template->setVariable("LOADING_TEXT", $plugin->txt("loading"));
				$template->setVariable("NO_RESULTS", $plugin->txt("no_results"));
				self::setLoaded('user_take_over');
				$html = $template->get();

				// add list in ILIAS 5 and newer
				if (ilComponent::isVersionGreaterString(ILIAS_VERSION_NUMERIC, "4.9.999")) {
					$html = '<li>' . $html . '</li>';
				}
				return array( "mode" => ilUIHookPluginGUI::PREPEND, "html" => $html );
			}
		}
	}


	public function gotoHook() {
		if (preg_match("/usr_takeover_(.*)/uim", $_GET['target'], $matches)) {
			usrtoHelper::getInstance()->takeOver((int)$matches[1]);
		}
		if (preg_match("/usr_takeback/uim", $_GET['target'], $matches)) {
			usrtoHelper::getInstance()->switchBack();
		}
	}
}