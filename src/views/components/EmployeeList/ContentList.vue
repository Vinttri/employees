<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('employees', 'Search employees...')">
					</div>
					<div class="button-container">
						<NcActions>
							<template #icon>
								<Cog :size="20" />
							</template>

							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('employees', 'Export list') }}
							</NcActionButton>

							<NcActionSeparator />

							<NcActionButton @click="$refs.file.click()">
								<template #icon>
									<Upload :size="20" />
								</template>
								{{ t('employees', 'Import data from template') }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</div>

		<VirtualList
			ref="scroller"
			class="contacts-list"
			data-key="id_employees"
			:data-sources="filteredList"
			:data-component="EmployeeListItem"
			:estimate-size="60" />

		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">
	</AppContentList>
</template>

<script>
import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcActionSeparator,
} from '@nextcloud/vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import EmployeeListItem from './EmployeeListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

export default {
	name: 'ContentList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
		Cog,
		Upload,
		DatabaseExport,
		NcActionSeparator,
	},

	props: {
		employees: {
			type: Array,
			required: true,
		},
		searchQuery: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			EmployeeListItem,
			query: '',
		}
	},

	computed: {
		filteredList() {
			return (Array.isArray(this.employees) ? this.employees : [])
				.filter(item => this.matchSearch(item.displayname, item.uid))
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		// expone t al template y métodos
		t,

		matchSearch(displayname, uid) {
			try {
				if (this.query.trim() !== '') {
					return displayname.toString().toLowerCase().includes(this.query.trim().toLowerCase())
				}
			} catch (error) {
				if (this.query.trim() !== '') {
					return uid.toString().toLowerCase().includes(this.query.trim().toLowerCase())
				}
			}
			return true
		},

		Exportar() {
			axios.get(generateUrl('/apps/employees/ExportListEmpleados'), { responseType: 'blob' })
				.then((response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'employees.xlsx')
					document.body.appendChild(link)
					link.click()
				})
				.catch((err) => {
					showError(t('employees', 'An error occurred {error}, please report to the administrator', { error: String(err) }))
				})
		},

		async importar() {
			const file = this.$refs.file?.files?.[0]
			if (!file) return

			const formData = new FormData()
			formData.append('fileXLSX', file)

			try {
				await axios.post(generateUrl('/apps/employees/ImportListEmpleados'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.$bus?.emit('getall')
				showSuccess(t('employees', 'Database updated successfully'))
			} catch (err) {
				showError(t('employees', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Make virtual scroller scrollable
.contacts-list {
	max-height: calc(100vh - var(--header-height) - 48px);
	overflow: auto;
}

// Add empty header to contacts-list that solves overlapping of contacts with app-navigation-toogle
.contacts-list__header {
	min-height: 48px;
}

// Search field
.search-contacts-field {
	padding: 5px 10px 5px 50px;
	margin-top: 4px;

	> input {
		width: 100%;
	}
}

.content-list {
	overflow-y: auto;
	padding: 0 4px;
}

.container-search {
	display: flex;
}
.input-container {
	flex: 1;
	margin-right: 5px;
}
.input-container input {
	width: 100%;
}
.button-container button {
	width: 100%;
}
</style>
