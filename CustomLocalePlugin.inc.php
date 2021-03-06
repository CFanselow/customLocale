<?php

/**
 * @file plugins/generic/customLocale/CustomLocalePlugin.inc.php
 *
 * Copyright (c) 2016 Language Science Press
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CustomLocalePlugin
 *
 */

define('CUSTOM_LOCALE_DIR', 'customLocale');
import('lib.pkp.classes.plugins.GenericPlugin');

class CustomLocalePlugin extends GenericPlugin {

	function register($category, $path) {

		if (parent::register($category, $path)) {
			if ($this->getEnabled()) {

				// Add custom locale data for already registered locale files.
				$locale = AppLocale::getLocale();
				$localeFiles = AppLocale::getLocaleFiles($locale);

				$press = Request::getPress();
				$pressId = $press->getId();

				$publicFilesDir = Config::getVar('files', 'public_files_dir');
				$customLocalePathBase = $publicFilesDir . DIRECTORY_SEPARATOR . 'presses' . DIRECTORY_SEPARATOR . $pressId . DIRECTORY_SEPARATOR . CUSTOM_LOCALE_DIR . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR;

				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				foreach ($localeFiles as $localeFile) {
					$customLocalePath = $customLocalePathBase . $localeFile->getFilename();
					if ($fileManager->fileExists($customLocalePath)) {
						AppLocale::registerLocaleFile($locale, $customLocalePath, false);
					}
				}

				// Add custom locale data for all locale files registered after this plugin
				HookRegistry::register('PKPLocale::registerLocaleFile', array(&$this, 'addCustomLocale'));
				HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));
				HookRegistry::register('Templates::Management::Settings::website', array($this, 'callbackShowWebsiteSettingsTabs'));
				HookRegistry::register('LoadHandler', array($this, 'handleLoadRequest'));
			}

			return true;
		}
		return false;
	}

	/**
	 * Permit requests to the custom locale grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	function setupGridHandler($hookName, $args) {

		$component =& $args[0];

		if ($component == 'plugins.generic.customLocale.controllers.grid.CustomLocaleGridHandler') {
			// Allow the custom locale grid handler to get the plugin object
			import($component);
			CustomLocaleGridHandler::setPlugin($this);
			return true;
		} 
		return false;
	}

	function handleLoadRequest($hookName, $args) {

		$request = $this -> getRequest();
		$press   = $request -> getPress();		

		$templateMgr = TemplateManager::getManager($request);

		// get url path components
		$page =& $args[0];
		$op =& $args[1];
		$tail = "/".implode("/",$request->getRequestedArgs());

		if ($page=="management" && $op=="settings" && $tail=="/printCustomLocaleChanges") {

			$op = 'printCustomLocaleChanges';
			define('HANDLER_CLASS', 'CustomLocaleHandler');
			define('CUSTOMLOCALE_PLUGIN_NAME', $this->getName());
			$this->import('CustomLocaleHandler');

		}
		return false;	
	}


	/**
	 * Display verbs for the management interface.
	 * @return array Management verbs
	 */ 
	function getManagementVerbs() {

		$verbs = parent::getManagementVerbs();

		if ($this->getEnabled()) {
			$verbs[] = array('index', __('plugins.generic.customLocale.customize'));
			$verbs[] = array('printChanges', __('plugins.generic.customLocale.printChanges'));
		}

		return $verbs;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */ 
	function getActions($request, $actionArgs) {
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'customize',
					new RedirectAction($dispatcher->url(
						$request, ROUTE_PAGE,
						null, 'management', 'settings', 'website',
						array('uid' => uniqid()), // Force reload
						'customLocale' // Anchor for tab
					)),
					__('plugins.generic.customLocale.customize'),
					null
				),
				new LinkAction(
					'printChanges',
					new RedirectAction($dispatcher->url(
						$request, ROUTE_PAGE,
						null, 'management', 'settings', 'printCustomLocaleChanges',
						array('uid' => uniqid()), // Force reload
						null // Anchor for tab
					)),
					__('plugins.generic.customLocale.printChanges'),
					null
				),
			):array(),
			parent::getActions($request, $actionArgs)
		);
	}

	function addCustomLocale($hookName, $args) {

		$locale =& $args[0];
		$localeFilename =& $args[1];		
		$request =& Registry::get('request');
		$press = $request->getPress();
		$pressId = $press->getId();

		$publicFilesDir = Config::getVar('files', 'public_files_dir');
		$customLocalePath = $publicFilesDir . DIRECTORY_SEPARATOR . 'presses' . DIRECTORY_SEPARATOR . $pressId . DIRECTORY_SEPARATOR . CUSTOM_LOCALE_DIR . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $localeFilename;

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		if ($fileManager->fileExists($customLocalePath)) {
			AppLocale::registerLocaleFile($locale, $customLocalePath, false);
		}

		return true;
	}

	function getDisplayName() {
		return __('plugins.generic.customLocale.name');
	}

	function getDescription() {
		return __('plugins.generic.customLocale.description');
	}

	/**
	 * Extend the website settings tabs to include custom locale
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 * @return boolean Hook handling status
	 */
	function callbackShowWebsiteSettingsTabs($hookName, $args) {
		$output =& $args[2];
		$request =& Registry::get('request');
		$dispatcher = $request->getDispatcher();

		// Add a new tab for custom locale
		$output .= '<li><a name="customLocale" href="' . $dispatcher->url($request, ROUTE_COMPONENT, null, 'plugins.generic.customLocale.controllers.grid.CustomLocaleGridHandler', 'index') . '">' . __('plugins.generic.customLocale.customLocale') . '</a></li>';

		// Permit other plugins to continue interacting with this hook
		return false;
	}

	/**
	 * @copydoc PKPPlugin::getTemplatePath
	 */
	function getTemplatePath() {
		return parent::getTemplatePath() . 'templates/';
	}

}

?>
