<template>
	<div class="mensaje-Team">
		<h2>{{ t('employees', 'Team information') }}</h2>
		<p>{{ t('employees', 'Here you will find information about your teams.') }}</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TeamsMessage',
	methods: { t },
}
</script>
