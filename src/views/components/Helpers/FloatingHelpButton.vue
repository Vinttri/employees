<template>
	<div>
		<NcModal v-if="open"
			size="large"
			:name="title"
			@close="$emit('update:open', false)">
			<slot />
		</NcModal>

		<div class="floating-help-button">
			<NcActions>
				<NcActionButton @click="$emit('update:open', true)">
					<template #icon>
						<component :is="icon" :size="24" />
					</template>
					{{ title }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { NcModal, NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'FloatingHelpButton',
	components: { NcModal, NcActions, NcActionButton },
	props: {
		open: { type: Boolean, default: false },
		title: { type: String, required: true },
		icon: { type: [Object, Function], required: true },
	},
}
</script>

<style scoped lang="scss">
.floating-help-button {
	position: fixed;
	right: 16px;
	bottom: 16px;
	z-index: 90;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0;
	background: transparent;
	border: 0;
	box-shadow: none;
}
</style>
