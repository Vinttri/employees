<template>
	<div class="mensaje-areas">
		<h2>{{ t('employees', 'Areas information') }}</h2>
		<p>{{ t('employees', 'Here you will find information about your areas.') }}</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AreasMessage',
	methods: { t },
}
</script>
