<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="personal-settings">
			<NcLoadingIcon v-if="loading" :size="64" class="loading-icon" />
			<NcEmptyContent v-else-if="error"
				:name="t('settings', 'Failed to load settings')"
				:description="error">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAlertCircle" />
				</template>
				<template #action>
					<NcButton type="primary" @click="loadSection">
						{{ t('settings', 'Retry') }}
					</NcButton>
				</template>
			</NcEmptyContent>
			<template v-else>
				<!-- Declarative settings forms -->
				<DeclarativeSection
					v-for="form in declarativeForms"
					:key="form.id"
					:form="form" />

				<!-- Legacy ISettings HTML -->
				<LegacySettingsForm
					v-if="legacyHtml"
					:html-content="legacyHtml"
					:scripts="legacyScripts"
					@loaded="onLegacyLoaded" />
			</template>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router/composables'
import { translate as t } from '@nextcloud/l10n'
import { mdiAlertCircle } from '@mdi/js'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import DeclarativeSection from '../components/DeclarativeSettings/DeclarativeSection.vue'
import LegacySettingsForm from '../components/LegacySettingsForm.vue'
import { useSettingsStore } from '../store/settings-store.ts'

const route = useRoute()
const settingsStore = useSettingsStore()

const section = computed(() => (route.params.section as string) || 'personal-info')

const loading = computed(() => settingsStore.isLoading('personal', section.value))
const error = computed(() => settingsStore.getError('personal', section.value))

const sectionContent = computed(() => settingsStore.getSectionContent('personal', section.value))
const declarativeForms = computed(() => sectionContent.value?.declarative ?? [])
const legacyHtml = computed(() => sectionContent.value?.legacyHtml ?? '')
const legacyScripts = computed(() => sectionContent.value?.scripts ?? [])

async function loadSection() {
	await settingsStore.loadSectionContent('personal', section.value)
}

function onLegacyLoaded() {
	// Legacy form has finished loading scripts
}

onMounted(() => {
	loadSection()
})

watch(section, () => {
	loadSection()
})
</script>

<style scoped lang="scss">
.personal-settings {
	padding: 20px;
	max-width: 900px;
	margin: 0 auto;
}

.loading-icon {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 200px;
}
</style>
