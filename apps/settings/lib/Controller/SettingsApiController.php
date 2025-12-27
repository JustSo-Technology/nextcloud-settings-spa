<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Settings\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Settings\IDeclarativeManager;
use OCP\Settings\IManager as ISettingsManager;
use OCP\Settings\ISettings;

/**
 * API Controller for Settings SPA
 *
 * Provides JSON endpoints for fetching settings sections and content.
 * Security: Replicates permission checks from CommonSettingsTrait to ensure
 * users only receive settings they are authorized to access.
 */
class SettingsApiController extends Controller {
	use CommonSettingsTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		INavigationManager $navigationManager,
		ISettingsManager $settingsManager,
		IUserSession $userSession,
		IGroupManager $groupManager,
		ISubAdmin $subAdmin,
		IDeclarativeManager $declarativeSettingsManager,
		IInitialState $initialState,
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
	 * Get section content for SPA navigation
	 *
	 * Returns sections for navigation and content for the requested section.
	 * Security checks are performed to ensure users only see authorized settings.
	 *
	 * @param string $type Either 'personal' or 'admin'
	 * @param string $section The section ID to load
	 * @return JSONResponse
	 *
	 * @NoSubAdminRequired - Permission checks are done within the method
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSection(string $type, string $section): JSONResponse {
		// 1. Must be logged in
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				['error' => 'Not logged in'],
				Http::STATUS_UNAUTHORIZED
			);
		}

		// 2. Validate type parameter
		if (!in_array($type, ['personal', 'admin'], true)) {
			return new JSONResponse(
				['error' => 'Invalid type. Must be "personal" or "admin"'],
				Http::STATUS_BAD_REQUEST
			);
		}

		// 3. Load declarative schemas
		$this->declarativeSettingsManager->loadSchemas();

		// 4. Get settings and check permissions
		if ($type === 'personal') {
			$settings = $this->settingsManager->getPersonalSettings($section);
			$settings = array_merge(...array_values($settings));
		} else {
			// For admin sections: check permissions BEFORE any content loading
			$settings = $this->settingsManager->getAllowedAdminSettings($section, $user);
			$declarativeForms = $this->declarativeSettingsManager->getFormsWithValues($user, $type, $section);

			if (empty($settings) && empty($declarativeForms)) {
				// CRITICAL: Must match PHP behavior - deny access
				return new JSONResponse(
					['error' => 'Access denied'],
					Http::STATUS_FORBIDDEN
				);
			}

			$settings = array_merge(...array_values($settings));
		}

		// 5. Get declarative forms with sensitive value masking
		$declarativeSettings = $this->declarativeSettingsManager->getFormsWithValues($user, $type, $section);
		foreach ($declarativeSettings as &$form) {
			foreach ($form['fields'] as &$field) {
				if (isset($field['sensitive']) && $field['sensitive'] === true && !empty($field['value'])) {
					$field['value'] = 'dummySecret';
				}
			}
		}
		unset($form, $field);

		// 6. Render legacy ISettings HTML
		$legacyHtml = '';
		$requiredScripts = [];
		foreach ($settings as $setting) {
			if ($setting instanceof ISettings) {
				$form = $setting->getForm();
				$legacyHtml .= $form->renderAs('')->render();
			}
		}

		// 7. Get section info
		$activeSection = $this->settingsManager->getSection($type, $section);
		$sectionInfo = null;
		if ($activeSection !== null) {
			$sectionInfo = [
				'id' => $activeSection->getID(),
				'name' => $activeSection->getName(),
				'type' => $type,
			];
		}

		// 8. Get filtered navigation sections (only sections user can access)
		$personalSections = $this->formatPersonalSections($type, $section);
		$adminSections = $this->formatAdminSections($type, $section);

		return new JSONResponse([
			'sections' => [
				'personal' => $personalSections,
				'admin' => $adminSections,
			],
			'content' => [
				'declarative' => $declarativeSettings,
				'legacyHtml' => $legacyHtml,
				'scripts' => $requiredScripts,
			],
			'sectionInfo' => $sectionInfo,
		]);
	}

	/**
	 * Get navigation sections only (lightweight endpoint)
	 *
	 * Returns only the navigation sections without content.
	 * Useful for refreshing navigation after permission changes.
	 *
	 * @return JSONResponse
	 *
	 * @NoSubAdminRequired
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSections(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				['error' => 'Not logged in'],
				Http::STATUS_UNAUTHORIZED
			);
		}

		$this->declarativeSettingsManager->loadSchemas();

		// Empty string for current section since we're just getting nav
		$personalSections = $this->formatPersonalSections('personal', '');
		$adminSections = $this->formatAdminSections('admin', '');

		return new JSONResponse([
			'sections' => [
				'personal' => $personalSections,
				'admin' => $adminSections,
			],
		]);
	}
}
