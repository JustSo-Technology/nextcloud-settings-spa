<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="legacy-settings-form">
		<NcLoadingIcon v-if="loading" :size="32" class="loading-spinner" />
		<NcNoteCard v-if="error" type="error">
			{{ error }}
		</NcNoteCard>
		<div
			v-show="!loading && !error"
			ref="container"
			class="legacy-content"
			v-html="sanitizedHtml" />
	</div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import DOMPurify from 'dompurify'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import logger from '../logger.ts'

const props = defineProps<{
	htmlContent: string
	scripts?: string[]
}>()

const emit = defineEmits<{
	(e: 'loaded'): void
	(e: 'error', error: string): void
}>()

const container = ref<HTMLElement | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const loadedScripts = ref<Set<string>>(new Set())

/**
 * Sanitize HTML content to prevent XSS attacks
 * Allows forms, inputs, and common UI elements
 */
const sanitizedHtml = computed(() => {
	if (!props.htmlContent) {
		return ''
	}

	// Configure DOMPurify to allow form elements and common attributes
	return DOMPurify.sanitize(props.htmlContent, {
		ADD_TAGS: ['form', 'input', 'select', 'textarea', 'button', 'label', 'fieldset', 'legend'],
		ADD_ATTR: [
			'action', 'method', 'type', 'name', 'value', 'placeholder', 'checked', 'selected',
			'disabled', 'readonly', 'required', 'pattern', 'min', 'max', 'step', 'multiple',
			'for', 'form', 'autocomplete', 'autofocus', 'data-*',
		],
		ALLOW_DATA_ATTR: true,
	})
})

/**
 * Load a script dynamically and track it
 */
function loadScript(src: string): Promise<void> {
	return new Promise((resolve, reject) => {
		// Check if already loaded
		if (loadedScripts.value.has(src)) {
			resolve()
			return
		}

		// Check if script exists in document
		const existing = document.querySelector(`script[src="${CSS.escape(src)}"]`)
		if (existing) {
			loadedScripts.value.add(src)
			resolve()
			return
		}

		const script = document.createElement('script')
		script.src = src
		script.async = false // Maintain execution order

		script.onload = () => {
			loadedScripts.value.add(src)
			resolve()
		}

		script.onerror = () => {
			const errorMsg = `Failed to load script: ${src}`
			logger.error(errorMsg)
			reject(new Error(errorMsg))
		}

		document.head.appendChild(script)
	})
}

/**
 * Load all required scripts in order
 */
async function loadScripts(): Promise<void> {
	if (!props.scripts || props.scripts.length === 0) {
		return
	}

	for (const src of props.scripts) {
		await loadScript(src)
	}
}

/**
 * Initialize legacy form content
 */
async function initialize(): Promise<void> {
	loading.value = true
	error.value = null

	try {
		// Wait for DOM to update with sanitized HTML
		await new Promise(resolve => setTimeout(resolve, 0))

		// Load required scripts
		await loadScripts()

		// Dispatch custom event for legacy forms that might need initialization
		if (container.value) {
			container.value.dispatchEvent(new CustomEvent('legacy-form-ready', { bubbles: true }))
		}

		// Also dispatch on window for global handlers
		window.dispatchEvent(new CustomEvent('legacy-settings-loaded'))

		loading.value = false
		emit('loaded')
	} catch (err) {
		const errorMessage = err instanceof Error ? err.message : t('settings', 'Failed to load settings form')
		error.value = errorMessage
		loading.value = false
		emit('error', errorMessage)
		logger.error('Failed to initialize legacy form', { error: err })
	}
}

// Initialize on mount
onMounted(() => {
	initialize()
})

// Re-initialize when content changes
watch(() => props.htmlContent, () => {
	initialize()
})

// Cleanup on unmount
onBeforeUnmount(() => {
	// Remove any event listeners or cleanup if needed
	if (container.value) {
		// Clear container to help garbage collection
		container.value.innerHTML = ''
	}
})
</script>

<style scoped lang="scss">
.legacy-settings-form {
	position: relative;
	min-height: 100px;

	.loading-spinner {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
	}

	.legacy-content {
		// Ensure legacy forms render correctly
		:deep(form) {
			margin-bottom: 1rem;
		}

		:deep(input[type="text"]),
		:deep(input[type="password"]),
		:deep(input[type="email"]),
		:deep(input[type="number"]),
		:deep(select),
		:deep(textarea) {
			// Use Nextcloud's input styling
			width: 100%;
			max-width: 400px;
		}

		:deep(.section) {
			margin-bottom: 2rem;
		}

		:deep(h2) {
			font-size: 1.25rem;
			font-weight: 600;
			margin-bottom: 1rem;
		}
	}
}
</style>
