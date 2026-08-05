export function buildPurchaseListParams({
	showOnlyMine,
	canViewAll,
	status,
	page,
	pageSize,
}) {
	const normalizedPage = Math.max(1, Number(page) || 1)
	const normalizedPageSize = Math.max(1, Number(pageSize) || 20)
	const params = {
		todas: canViewAll && !showOnlyMine ? 1 : 0,
		limit: normalizedPageSize,
		offset: (normalizedPage - 1) * normalizedPageSize,
	}

	if (status && status !== 'todos') {
		params.status = status
	}

	return params
}
