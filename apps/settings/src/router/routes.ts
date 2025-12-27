/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { RouteConfig } from 'vue-router'

import { loadState } from '@nextcloud/initial-state'

const appstoreEnabled = loadState<boolean>('settings', 'appstoreEnabled', true)

// Dynamic loading
const AppStore = () => import(/* webpackChunkName: 'settings-apps-view' */'../views/AppStore.vue')
const AppStoreNavigation = () => import(/* webpackChunkName: 'settings-apps-view' */'../views/AppStoreNavigation.vue')
const AppStoreSidebar = () => import(/* webpackChunkName: 'settings-apps-view' */'../views/AppStoreSidebar.vue')

const UserManagement = () => import(/* webpackChunkName: 'settings-users' */'../views/UserManagement.vue')
const UserManagementNavigation = () => import(/* webpackChunkName: 'settings-users' */'../views/UserManagementNavigation.vue')

// Settings SPA views
const PersonalSettings = () => import(/* webpackChunkName: 'settings-spa' */'../views/PersonalSettings.vue')
const AdminSettings = () => import(/* webpackChunkName: 'settings-spa' */'../views/AdminSettings.vue')
const SettingsNavigation = () => import(/* webpackChunkName: 'settings-spa' */'../views/SettingsNavigation.vue')

const routes: RouteConfig[] = [
	{
		name: 'users',
		path: '/:index(index.php/)?settings/users',
		components: {
			default: UserManagement,
			navigation: UserManagementNavigation,
		},
		props: true,
		children: [
			{
				path: ':selectedGroup',
				name: 'group',
			},
		],
	},
	{
		path: '/:index(index.php/)?settings/apps',
		name: 'apps',
		redirect: {
			name: 'apps-category',
			params: {
				category: appstoreEnabled ? 'discover' : 'installed',
			},
		},
		components: {
			default: AppStore,
			navigation: AppStoreNavigation,
			sidebar: AppStoreSidebar,
		},
		children: [
			{
				path: ':category',
				name: 'apps-category',
				children: [
					{
						path: ':id',
						name: 'apps-details',
					},
				],
			},
		],
	},
	// Personal Settings SPA
	{
		path: '/:index(index.php/)?settings/user/:section?',
		name: 'personal-settings',
		components: {
			default: PersonalSettings,
			navigation: SettingsNavigation,
		},
		props: {
			default: (route) => ({
				section: route.params.section || 'personal-info',
			}),
		},
	},
	// Admin Settings SPA
	{
		path: '/:index(index.php/)?settings/admin/:section?',
		name: 'admin-settings',
		components: {
			default: AdminSettings,
			navigation: SettingsNavigation,
		},
		props: {
			default: (route) => ({
				section: route.params.section || 'server',
			}),
		},
	},
]

export default routes
