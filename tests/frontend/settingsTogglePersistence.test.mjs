import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const source = fs.readFileSync(path.join(root, 'src/views/Settings/ListSettings.vue'), 'utf8')

const settings = {
	onChangeGuardadoNotas: ['guardado_notes', 'automatic_save_note'],
	onChangeacumular_vacaciones: ['acumular_vacaciones', 'acumular_vacaciones'],
	onChangemodulo_savings: ['modulo_savings', 'modulo_savings'],
	onChangemodulo_ausencias: ['modulo_ausencias', 'modulo_ausencias'],
	onChangemodulo_ausencias_readonly: ['modulo_ausencias_readonly', 'ausencias_readonly'],
	onChangemodulo_clients: ['modulo_clients', 'modulo_clients'],
	onChangemodulo_reporte_tiempos: ['modulo_reporte_tiempos', 'modulo_reporte_tiempos'],
	onChangemodulo_inventario: ['modulo_inventario', 'modulo_inventario'],
	onChangemodulo_soporte: ['modulo_soporte', 'modulo_soporte'],
	onChangemodulo_purchases: ['modulo_purchases', 'modulo_purchases'],
}

for (const [handler, [property, key]] of Object.entries(settings)) {
	assert.ok(source.includes(`${handler}(checked) {`), `missing checked payload for ${handler}`)
	assert.ok(source.includes(`return this.updateBooleanSetting('${property}', '${key}', checked`), `missing persistence mapping for ${handler}`)
}

assert.match(source, /const nextValue = checked === true/)
assert.match(source, /data: nextValue \? 'true' : 'false'/)
assert.match(source, /this\[propertyName\] = previousValue/)
assert.doesNotMatch(source, /this\.modulo_ausencias = !this\.modulo_ausencias/)
assert.match(source, /if \(settingName === 'automatic_save_note'\)/)

console.log('SETTINGS_TOGGLE_PERSISTENCE_OK settings=10')
