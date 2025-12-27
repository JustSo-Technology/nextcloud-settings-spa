/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { IDeclarativeForm, ISettingsSection, ISectionContent } from '../settings-types.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import logger from '../logger.ts'

type SettingsType = 'personal' | 'admin'

interface SettingsState {
	sections: {
		personal: ISettingsSection[]
		admin: ISettingsSection[]
	}
	content: Record<string, ISectionContent>
	loading: Record<string, boolean>
	errors: Record<string, string | null>
	errorCodes: Record<string, number | null>
	initialized: boolean
}

/**
 * PHP API returns sections with 'anchor' and 'section-name' keys
 * Transform to our TypeScript interface with 'id' and 'name'
 */
interface PhpSection {
	anchor: string
	'section-name': string
	active: boolean
	icon: string
}

function transformSection(phpSection: PhpSection): ISettingsSection {
	return {
		id: phpSection.anchor,
		name: phpSection['section-name'],
		priority: 0,
		icon: phpSection.icon,
	}
}

function transformSections(phpSections: PhpSection[]): ISettingsSection[] {
	return phpSections.map(transformSection)
}

const showApiError = () => showError(t('settings', 'An error occurred during the request. Unable to proceed.'))

/**
 * Generate a cache key for section content
 */
function contentKey(type: SettingsType, section: string): string {
	return `${type}-${section}`
}

/**
 * Load initial sections from InitialState
 * The controller provides sections under the 'sections' key
 * Data comes in PHP format (anchor/section-name) and needs transformation
 */
function loadInitialSections(): { personal: ISettingsSection[], admin: ISettingsSection[] } {
	try {
		const sections = loadState<{ personal: PhpSection[], admin: PhpSection[] }>(
			'settings',
			'sections',
			{ personal: [], admin: [] },
		)
		return {
			personal: transformSections(sections.personal || []),
			admin: transformSections(sections.admin || []),
		}
	} catch {
		return { personal: [], admin: [] }
	}
}

/**
 * Load initial section content from InitialState
 */
function loadInitialContent(): ISectionContent | null {
	try {
		return loadState<ISectionContent>('settings', 'sectionContent', null)
	} catch {
		return null
	}
}

export const useSettingsStore = defineStore('settings-spa', {
	state: (): SettingsState => {
		const initialSections = loadInitialSections()
		const initialContent = loadInitialContent()
		const settingsType = loadState<string>('settings', 'settingsType', '')
		const currentSection = loadState<string>('settings', 'currentSection', '')

		// Pre-populate content cache with initial content if available
		const content: Record<string, ISectionContent> = {}
		if (initialContent && settingsType && currentSection) {
			content[contentKey(settingsType as SettingsType, currentSection)] = initialContent
		}

		return {
			sections: initialSections,
			content,
			loading: {},
			errors: {},
			errorCodes: {},
			initialized: !!initialContent,
		}
	},

	getters: {
		/**
		 * Check if a section is currently loading
		 */
		isLoading: (state) => (type: SettingsType, section: string): boolean => {
			return state.loading[contentKey(type, section)] ?? false
		},

		/**
		 * Get error for a section
		 */
		getError: (state) => (type: SettingsType, section: string): string | null => {
			return state.errors[contentKey(type, section)] ?? null
		},

		/**
		 * Get cached section content
		 */
		getSectionContent: (state) => (type: SettingsType, section: string): ISectionContent | null => {
			return state.content[contentKey(type, section)] ?? null
		},

		/**
		 * Check if section content is cached
		 */
		hasCachedContent: (state) => (type: SettingsType, section: string): boolean => {
			return contentKey(type, section) in state.content
		},

		/**
		 * Check if section has access denied error (HTTP 403)
		 * Uses error code instead of translated text for reliable detection
		 */
		isAccessDenied: (state) => (type: SettingsType, section: string): boolean => {
			return state.errorCodes[contentKey(type, section)] === 403
		},
	},

	actions: {
		/**
		 * Load sections for navigation
		 */
		async loadSections(force = false): Promise<void> {
			if (this.initialized && !force) {
				return
			}

			// Sections are already loaded via InitialState on page load
			// This action is for refreshing if needed
			if (force) {
				try {
					const response = await axios.get<{
						sections: { personal: PhpSection[], admin: PhpSection[] }
					}>(generateUrl('/settings/api/spa/sections'))

					// Transform from PHP format to TypeScript format
					this.sections.personal = transformSections(response.data.sections?.personal ?? [])
					this.sections.admin = transformSections(response.data.sections?.admin ?? [])
				} catch (error) {
					logger.error('Failed to load sections', { error })
					showApiError()
				}
			}

			this.initialized = true
		},

		/**
		 * Load content for a specific section
		 */
		async loadSectionContent(type: SettingsType, section: string, force = false): Promise<void> {
			const key = contentKey(type, section)

			// Return cached content if available
			if (!force && this.content[key]) {
				return
			}

			// Check if already loading
			if (this.loading[key]) {
				return
			}

			this.loading[key] = true
			this.errors[key] = null
			this.errorCodes[key] = null

			try {
				const response = await axios.get<{
					content: ISectionContent
					sections?: {
						personal: PhpSection[]
						admin: PhpSection[]
					}
				}>(generateUrl(`/settings/api/spa/section/${type}/${section}`))

				this.content[key] = response.data.content

				// Update sections if provided (navigation might have changed)
				// Transform from PHP format to TypeScript format
				if (response.data.sections) {
					if (response.data.sections.personal) {
						this.sections.personal = transformSections(response.data.sections.personal)
					}
					if (response.data.sections.admin) {
						this.sections.admin = transformSections(response.data.sections.admin)
					}
				}
			} catch (error) {
				logger.error('Failed to load section content', { type, section, error })

				if (axios.isAxiosError(error)) {
					const status = error.response?.status
					this.errorCodes[key] = status ?? null

					if (status === 403) {
						this.errors[key] = t('settings', 'Access denied')
					} else if (status === 404) {
						this.errors[key] = t('settings', 'Section not found')
					} else {
						this.errors[key] = t('settings', 'Failed to load settings')
						showApiError()
					}
				} else {
					this.errors[key] = t('settings', 'An unexpected error occurred')
					showApiError()
				}
			} finally {
				this.loading[key] = false
			}
		},

		/**
		 * Set initial content from InitialState (used on first page load)
		 */
		setInitialContent(type: SettingsType, section: string, content: ISectionContent): void {
			const key = contentKey(type, section)
			this.content[key] = content
		},

		/**
		 * Clear cached content for a section
		 */
		clearSectionContent(type: SettingsType, section: string): void {
			const key = contentKey(type, section)
			delete this.content[key]
			delete this.errors[key]
			delete this.errorCodes[key]
		},

		/**
		 * Clear all cached content
		 */
		clearAllContent(): void {
			this.content = {}
			this.errors = {}
			this.errorCodes = {}
			this.loading = {}
		},

		/**
		 * Prefetch content for a section (e.g., on hover)
		 */
		async prefetchSectionContent(type: SettingsType, section: string): Promise<void> {
			const key = contentKey(type, section)

			// Don't prefetch if already cached or loading
			if (this.content[key] || this.loading[key]) {
				return
			}

			// Load in background without showing errors
			try {
				const response = await axios.get<{ content: ISectionContent }>(
					generateUrl(`/settings/api/spa/section/${type}/${section}`),
				)
				this.content[key] = response.data.content
			} catch {
				// Silently fail for prefetch
			}
		},
	},
})
