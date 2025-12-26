<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation>
		<template #list>
			<!-- Personal Settings -->
			<NcAppNavigationCaption v-if="showPersonalSections" :name="t('settings', 'Personal')" />
			<NcAppNavigationItem
				v-for="section in personalSections"
				:key="`personal-${section.id}`"
				:name="section.name"
				:to="{ name: 'personal-settings', params: { section: section.id } }"
				:active="isActiveSection('personal', section.id)">
				<template #icon>
					<NcIconSvgWrapper v-if="section.icon" :path="section.icon" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiCog" :size="20" />
				</template>
			</NcAppNavigationItem>

			<!-- Admin Settings -->
			<NcAppNavigationCaption v-if="showAdminSections" :name="t('settings', 'Administration')" />
			<NcAppNavigationItem
				v-for="section in adminSections"
				:key="`admin-${section.id}`"
				:name="section.name"
				:to="{ name: 'admin-settings', params: { section: section.id } }"
				:active="isActiveSection('admin', section.id)">
				<template #icon>
					<NcIconSvgWrapper v-if="section.icon" :path="section.icon" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiCog" :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router/composables'
import { translate as t } from '@nextcloud/l10n'
import { mdiCog } from '@mdi/js'

import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useSettingsStore } from '../store/settings-store.ts'

const route = useRoute()
const settingsStore = useSettingsStore()

const personalSections = computed(() => settingsStore.sections.personal)
const adminSections = computed(() => settingsStore.sections.admin)

const showPersonalSections = computed(() => personalSections.value.length > 0)
const showAdminSections = computed(() => adminSections.value.length > 0)

const currentType = computed(() => {
	if (route.name === 'personal-settings') return 'personal'
	if (route.name === 'admin-settings') return 'admin'
	return null
})

const currentSection = computed(() => route.params.section as string || null)

function isActiveSection(type: string, sectionId: string): boolean {
	return currentType.value === type && currentSection.value === sectionId
}

onMounted(() => {
	settingsStore.loadSections()
})
</script>

<style scoped lang="scss">
// Navigation styles handled by NcAppNavigation
</style>
