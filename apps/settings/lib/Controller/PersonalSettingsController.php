<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\Controller;

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
class PersonalSettingsController extends Controller {
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
		$this->subAdmin = $subAdmin;
		$this->groupManager = $groupManager;
		$this->declarativeSettingsManager = $declarativeSettingsManager;
		$this->initialState = $initialState;
	}

	/**
	 * @NoSubAdminRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(string $section): TemplateResponse {
		// Check if SPA mode is enabled
		$spaEnabled = $this->config->getSystemValueBool('settings_spa_enabled', false);

		if (!$spaEnabled) {
			// Fall back to existing behavior
			return $this->getIndexResponse('personal', $section);
		}

		return $this->getSpaResponse('personal', $section);
	}

	/**
	 * Get SPA response with InitialState data
	 */
	private function getSpaResponse(string $type, string $section): TemplateResponse {
		$user = $this->userSession->getUser();
		assert($user !== null, 'No user logged in for settings');

		// Load declarative schemas
		$this->declarativeSettingsManager->loadSchemas();

		// Get sections for navigation (filtered by permissions)
		$personalSections = $this->formatPersonalSections($type, $section);
		$adminSections = $this->formatAdminSections($type, $section);

		// Get initial section content
		$declarativeSettings = $this->declarativeSettingsManager->getFormsWithValues($user, $type, $section);

		// Mask sensitive values
		foreach ($declarativeSettings as &$form) {
			foreach ($form['fields'] as &$field) {
				if (isset($field['sensitive']) && $field['sensitive'] === true && !empty($field['value'])) {
					$field['value'] = 'dummySecret';
				}
			}
		}
		unset($form, $field);

		// Get legacy settings
		$settings = $this->settingsManager->getPersonalSettings($section);
		$settings = array_merge(...array_values($settings));

		// Render legacy HTML
		$legacyHtml = '';
		foreach ($settings as $setting) {
			if ($setting instanceof ISettings) {
				$form = $setting->getForm();
				$legacyHtml .= $form->renderAs('')->render();
			}
		}

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
		if ($section === 'theming') {
			$this->navigationManager->setActiveEntry('accessibility_settings');
		} else {
			$this->navigationManager->setActiveEntry('settings');
		}

		// Load SPA scripts - reuse existing apps-users-management entry
		Util::addScript('settings', 'vue-settings-apps-users-management');
		Util::addStyle('settings', 'settings');

		// Get page title
		$activeSection = $this->settingsManager->getSection($type, $section);
		$pageTitle = $activeSection ? $activeSection->getName() : 'Personal settings';

		return new TemplateResponse('settings', 'settings/empty', [
			'pageTitle' => $pageTitle,
		]);
	}
}
