import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const utilitySource = await readFile(new URL('../../src/utils/purchaseDetailView.js', import.meta.url), 'utf8')
const utilityUrl = `data:text/javascript;base64,${Buffer.from(utilitySource).toString('base64')}`
const { getPurchaseDetailActions, isPurchaseDetailReadOnly } = await import(utilityUrl)

assert.equal(isPurchaseDetailReadOnly(), true)

const draftActions = getPurchaseDetailActions({
	request: { id_request: 10, id_user: 'owner', status: 'borrador' },
	currentUserId: 'owner',
})
assert.equal(draftActions.edit, true)
assert.equal(draftActions.send, true)
assert.equal(draftActions.cancel, true)
assert.equal(draftActions.approve, false)

const approvalActions = getPurchaseDetailActions({
	request: { id_request: 11, id_user: 'owner', status: 'pendiente_autorizacion' },
	currentUserId: 'other',
	flow: { can_approve: true, can_reject: true },
})
assert.equal(approvalActions.approve, true)
assert.equal(approvalActions.reject, true)
assert.equal(approvalActions.edit, false)

const documentActions = getPurchaseDetailActions({
	request: { id_request: 12, status: 'autorizada' },
	permissions: { can_process_purchase: true },
})
assert.equal(documentActions.savePdf, true)
assert.equal(documentActions.uploadSigned, true)

const componentSource = await readFile(
	new URL('../../src/views/components/Purchases/PurchaseRequestDetails.vue', import.meta.url),
	'utf8',
)
assert.doesNotMatch(componentSource, /<(?:input|textarea|NcTextField|NcTextArea)\b/)
assert.doesNotMatch(componentSource, /\bv-model(?:\.|=)/)
