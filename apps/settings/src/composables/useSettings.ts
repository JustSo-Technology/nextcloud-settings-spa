/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'
import type { ISettingsSection, ISectionContent } from '../settings-types.ts'

import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router/composables'
import { useSettingsStore } from '../store/settings-store.ts'

type SettingsType = 'personal' | 'admin'

interface UseSettingsOptions {
	type: SettingsType
	defaultSection?: string
}

interface UseSettingsReturn {
	// Current section
	section: Ref<string>
	sectionInfo: Ref<ISettingsSection | null>

	// Loading state
	loading: Ref<boolean>
	error: Ref<string | null>

	// Content
	content: Ref<ISectionContent | null>
	declarativeForms: Ref<ISectionContent['declarative']>
	legacyHtml: Ref<string>
	legacyScripts: Ref<string[]>

	// Navigation
	sections: Ref<ISettingsSection[]>

	// Actions
	loadSection: () => Promise<void>
	refreshSection: () => Promise<void>
	prefetchSection: (sectionId: string) => Promise<void>
}

/**
 * Composable for managing settings section data
 *
 * @param options - Configuration options
 * @returns Settings state and actions
 */
export function useSettings(options: UseSettingsOptions): UseSettingsReturn {
	const { type, defaultSection } = options

	const route = useRoute()
	const store = useSettingsStore()

	// Current section from route or default
	const section = computed<string>(() => {
		const routeSection = route.params.section as string | undefined
		return routeSection || defaultSection || (type === 'personal' ? 'personal-info' : 'server')
	})

	// Section info from navigation
	const sectionInfo = computed<ISettingsSection | null>(() => {
		const sections = type === 'personal' ? store.sections.personal : store.sections.admin
		return sections.find(s => s.id === section.value) ?? null
	})

	// Loading and error state
	const loading = computed(() => store.isLoading(type, section.value))
	const error = computed(() => store.getError(type, section.value))

	// Section content
	const content = computed(() => store.getSectionContent(type, section.value))
	const declarativeForms = computed(() => content.value?.declarative ?? [])
	const legacyHtml = computed(() => content.value?.legacyHtml ?? '')
	const legacyScripts = computed(() => content.value?.scripts ?? [])

	// Navigation sections
	const sections = computed(() => {
		return type === 'personal' ? store.sections.personal : store.sections.admin
	})

	/**
	 * Load the current section content
	 */
	async function loadSection(): Promise<void> {
		await store.loadSectionContent(type, section.value)
	}

	/**
	 * Force refresh the current section content
	 */
	async function refreshSection(): Promise<void> {
		await store.loadSectionContent(type, section.value, true)
	}

	/**
	 * Prefetch a section for faster navigation
	 */
	async function prefetchSection(sectionId: string): Promise<void> {
		await store.prefetchSectionContent(type, sectionId)
	}

	// Auto-load section content when section changes
	const autoLoad = ref(true)
	if (autoLoad.value) {
		watch(section, () => {
			loadSection()
		}, { immediate: true })
	}

	return {
		section,
		sectionInfo,
		loading,
		error,
		content,
		declarativeForms,
		legacyHtml,
		legacyScripts,
		sections,
		loadSection,
		refreshSection,
		prefetchSection,
	}
}

/**
 * Composable for personal settings
 */
export function usePersonalSettings() {
	return useSettings({
		type: 'personal',
		defaultSection: 'personal-info',
	})
}

/**
 * Composable for admin settings
 */
export function useAdminSettings() {
	return useSettings({
		type: 'admin',
		defaultSection: 'server',
	})
}
