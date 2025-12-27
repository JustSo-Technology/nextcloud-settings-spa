/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface IRichObjectParameter {
	[index: string]: string
	type: string
}

export type IRichObjectParameters = Record<string, IRichObjectParameter>

export interface ISetupCheck {
	name: string
	severity: 'success' | 'info' | 'warning' | 'error'
	description: string
	descriptionParameters: IRichObjectParameters
	linkToDoc?: string
}

/**
 * Settings section for navigation
 */
export interface ISettingsSection {
	id: string
	name: string
	priority: number
	icon?: string
	type?: 'personal' | 'admin'
}

/**
 * Declarative form field definition
 */
export interface IDeclarativeFormField {
	id: string
	type: 'text' | 'password' | 'email' | 'number' | 'checkbox' | 'select' | 'multi-select' | 'radio'
	label: string
	description?: string
	placeholder?: string
	value: unknown
	default?: unknown
	options?: Array<{ value: string; label: string }>
	required?: boolean
	sensitive?: boolean
}

/**
 * Declarative form definition
 */
export interface IDeclarativeForm {
	id: string
	title: string
	description?: string
	section: string
	priority: number
	fields: IDeclarativeFormField[]
	app: string
}

/**
 * Section content returned by API
 */
export interface ISectionContent {
	declarative: IDeclarativeForm[]
	legacyHtml: string
	scripts: string[]
}
