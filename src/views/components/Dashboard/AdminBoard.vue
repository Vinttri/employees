<!-- eslint-disable object-curly-newline -->
<template>
	<div class="dashboard">
		<!-- KPIs -->
		<section class="kpis">
			<div class="kpi">
				<div class="kpi-label">
					{{ t('employees', 'Employees') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.totalEmpleados }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('employees', 'Areas') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.totalAreas }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('employees', 'Absences today') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.ausenciasHoy }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('employees', 'Anniversaries (30 days)') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.aniversariosMes }}
				</div>
			</div>
		</section>

		<!-- Acciones rápidas -->
		<section class="quick">
			<h3>{{ t('employees', 'Quick actions') }}</h3>
			<div class="quick-grid">
				<button class="nc-btn" @click="go('employees')">
					{{ t('employees', 'View employees') }}
				</button>
				<button class="nc-btn" @click="go('employees/nuevo')">
					{{ t('employees', 'New employee') }}
				</button>
				<button class="nc-btn" @click="go('areas')">
					{{ t('employees', 'Areas and positions') }}
				</button>
				<button class="nc-btn" @click="go('Absence')">
					{{ t('employees', 'Manage absences') }}
				</button>
				<button class="nc-btn" @click="go('reportes')">
					{{ t('employees', 'Reports') }}
				</button>
				<button class="nc-btn" @click="go('config')">
					{{ t('employees', 'Settings') }}
				</button>
			</div>
		</section>

		<!-- Próximos anniversaries -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('employees', 'Upcoming anniversaries (30 days)') }}</h3>
				<button class="nc-link" @click="go('anniversaries')">
					{{ t('employees', 'View all') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('employees', 'Loading...') }}
			</div>
			<ul v-else-if="anniversaries.length" class="list">
				<li v-for="a in anniversaries" :key="a.id" class="item">
					<div class="item-main">
						<strong>{{ a.name }}</strong>
						<span class="muted">· {{ a.area }}</span>
					</div>
					<div class="item-meta">
						<span class="pill">{{ a.date }}</span>
						<span class="muted">{{ t('employees', '{years} years', { years: a.years }) }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('employees', 'No upcoming anniversaries.') }}
			</div>
		</section>

		<!-- Ausencias hoy -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('employees', 'Today absences') }}</h3>
				<button class="nc-link" @click="go('Absence')">
					{{ t('employees', 'Manage') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('employees', 'Loading...') }}
			</div>
			<ul v-else-if="ausenciasHoy.length" class="list">
				<li v-for="x in ausenciasHoy" :key="x.id" class="item">
					<div class="item-main">
						<strong>{{ x.name }}</strong>
						<span class="muted">· {{ x.type }}</span>
					</div>
					<div class="item-meta">
						<span class="pill">{{ x.de }} → {{ x.hasta }}</span>
						<span class="muted">{{ x.area }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('employees', 'Nobody is absent today.') }}
			</div>
		</section>

		<!-- Últimos changes -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('employees', 'Latest changes') }}</h3>
				<button class="nc-link" @click="go('actividad')">
					{{ t('employees', 'View activity') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('employees', 'Loading...') }}
			</div>
			<ul v-else-if="actividad.length" class="list">
				<li v-for="e in actividad" :key="e.id" class="item">
					<div class="item-main">
						<strong>{{ e.title }}</strong>
						<span class="muted">· {{ e.usuario }}</span>
					</div>
					<div class="item-meta">
						<span class="muted">{{ e.date }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('employees', 'No recent activity.') }}
			</div>
		</section>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	methods: {
		t,
	},
}
</script>
