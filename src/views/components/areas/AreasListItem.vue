<template>
	<div class="contacts-list__item-wrapper">
		<ListItem
			:key="source.id_employees"
			:compact="true"
			class="list-item-style envelope"
			:name="source.name"
			:counter-number="source.employee_count"
			@click.prevent="showDetails(source)">
			<template #name>
				{{ source.name }}
			</template>
			<template v-if="source.id_parent" #subname>
				<small>{{ t('employees', 'Parent area') }}: {{ source.id_parent }}</small>
			</template>
		</ListItem>
	</div>
</template>

<script>
import {
	NcListItem as ListItem,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AreasListItem',

	components: {
		ListItem,
	},

	props: {
		index: {
			type: Number,
			required: true,
		},
		source: {
			type: Object,
			required: true,
		},
		reloadBus: {
			type: Object,
			required: true,
		},
	},

	methods: {
		t, // exponer i18n a la plantilla
		showDetails(data) {
			this.$root.$emit('send-data-areas', data)
			this.$root.$emit('show', false)
		},
	},
}
</script>

<style lang="scss" scoped>
.envelope {
	.app-content-list-item-icon {
		height: 40px; // evita espacio extra bajo el avatar
	}

	&__subtitle {
		display: flex;
		gap: 4px;

		&__subject {
			color: var(--color-main-text);
			line-height: 130%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}

.list-item-style {
	list-style: none;
}
</style>

<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * {
		cursor: move !important;
	}

	&[draggable='false'] .avatardiv * {
		cursor: not-allowed !important;
	}
}
</style>
