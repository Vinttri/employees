<template>
	<div class="contacts-list__item-wrapper">
		<ListItem
			:key="source.id_team"
			:compact="true"
			class="list-item-style envelope"
			:name="source.name"
			:counter-number="source.employee_count"
			@click.prevent="showDetails(source)">
			<template #icon>
				<div class="app-content-list-item-icon">
					<NcAvatar
						:display-name="source.team_leader_id || ''"
						:user="source.team_leader_id || ''"
						:show-user-status="false"
						:size="40" />
				</div>
			</template>
			<template #name>
				{{ source.name }}
			</template>
			<template v-if="source.team_leader_id" #subname>
				<small>{{ t('employees', 'Lead') }}: {{ source.team_leader_id }}</small>
			</template>
		</ListItem>
	</div>
</template>

<script>
import { NcListItem as ListItem, NcAvatar } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TeamsListItem',

	components: {
		ListItem,
		NcAvatar,
	},

	props: {
		index: { type: Number, required: true },
		source: { type: Object, required: true },
		reloadBus: { type: Object, required: true },
	},

	methods: {
		t,
		showDetails(data) {
			this.$root.$emit('send-data-team', data)
			this.$root.$emit('show', false)
		},
	},
}
</script>

<style lang="scss" scoped>
.envelope {
	.app-content-list-item-icon {
		height: 40px; // evita salto visual bajo el avatar
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
.list-item-style { list-style: none; }
</style>

<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * { cursor: move !important; }
	&[draggable='false'] .avatardiv * { cursor: not-allowed !important; }
}
</style>
