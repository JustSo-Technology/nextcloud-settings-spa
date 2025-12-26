<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Settings\IDeclarativeManager;
use OCP\Settings\IManager as ISettingsManager;
use OCP\Settings\ISettings;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class AdminSettingsController extends Controller {
	use CommonSettingsTrait;

	public function __construct(
		$appName,
		IRequest $request,
		INavigationManager $navigationManager,
		ISettingsManager $settingsManager,
		IUserSession $userSession,
		IGroupManager $groupManager,
		ISubAdmin $subAdmin,
		IDeclarativeManager $declarativeSettingsManager,
		IInitialState $initialState,
		private IConfig $config,
	) {
		parent::__construct($appName, $request);
		$this->navigationManager = $navigationManager;
		$this->settingsManager = $settingsManager;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->subAdmin = $subAdmin;
		$this->declarativeSettingsManager = $declarativeSettingsManager;
		$this->initialState = $initialState;
	}

	/**
	 * @NoSubAdminRequired
	 * We are checking the permissions in the getSettings method. If there is no allowed
	 * settings for the given section. The user will be greeted by an error message.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(string $section): TemplateResponse {
		// Check if SPA mode is enabled
		$spaEnabled = $this->config->getSystemValueBool('settings_spa_enabled', false);

		if (!$spaEnabled) {
			// Fall back to existing behavior
			return $this->getIndexResponse('admin', $section);
		}

		return $this->getSpaResponse('admin', $section);
	}

	/**
	 * Get SPA response with InitialState data
	 *
	 * @throws NotAdminException if user has no access to any settings in this section
	 */
	private function getSpaResponse(string $type, string $section): TemplateResponse {
		$user = $this->userSession->getUser();
		assert($user !== null, 'No user logged in for settings');

		// Load declarative schemas
		$this->declarativeSettingsManager->loadSchemas();

		// Get initial section content with permission check
		$declarativeSettings = $this->declarativeSettingsManager->getFormsWithValues($user, $type, $section);

		// Get legacy settings - getAllowedAdminSettings handles permission filtering
		$settings = $this->settingsManager->getAllowedAdminSettings($section, $user);

		// CRITICAL: Check permissions before rendering anything
		if (empty($settings) && empty($declarativeSettings)) {
			throw new NotAdminException('Logged in user does not have permission to access these settings.');
		}

		$settings = array_merge(...array_values($settings));

		// Mask sensitive values
		foreach ($declarativeSettings as &$form) {
			foreach ($form['fields'] as &$field) {
				if (isset($field['sensitive']) && $field['sensitive'] === true && !empty($field['value'])) {
					$field['value'] = 'dummySecret';
				}
			}
		}
		unset($form, $field);

		// Render legacy HTML
		$legacyHtml = '';
		foreach ($settings as $setting) {
			if ($setting instanceof ISettings) {
				$form = $setting->getForm();
				$legacyHtml .= $form->renderAs('')->render();
			}
		}

		// Get sections for navigation (filtered by permissions)
		$personalSections = $this->formatPersonalSections($type, $section);
		$adminSections = $this->formatAdminSections($type, $section);

		// Provide all data via InitialState for SPA
		$this->initialState->provideInitialState('settingsType', $type);
		$this->initialState->provideInitialState('currentSection', $section);
		$this->initialState->provideInitialState('sections', [
			'personal' => $personalSections,
			'admin' => $adminSections,
		]);
		$this->initialState->provideInitialState('sectionContent', [
			'declarative' => $declarativeSettings,
			'legacyHtml' => $legacyHtml,
			'scripts' => [],
		]);

		// Set navigation entry
		$this->navigationManager->setActiveEntry('admin_settings');

		// Load SPA scripts - reuse existing apps-users-management entry
		Util::addScript('settings', 'vue-settings-apps-users-management');
		Util::addStyle('settings', 'settings');

		// Get page title
		$activeSection = $this->settingsManager->getSection($type, $section);
		$pageTitle = $activeSection ? $activeSection->getName() : 'Admin settings';

		return new TemplateResponse('settings', 'settings/empty', [
			'pageTitle' => $pageTitle,
		]);
	}
}
